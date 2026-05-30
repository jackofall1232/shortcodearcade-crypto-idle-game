<?php
/**
 * AI Storyline Class
 *
 * Generates short AI narrative popups on upgrade and prestige milestones.
 * Optional and disabled by default. Supports Anthropic Claude, OpenAI
 * (GPT-4o Mini / GPT-5 Mini), and xAI Grok providers with a 24-hour shared
 * response cache. Designed to align with the forthcoming WordPress 7.0 AI
 * Client API for centralized key management.
 *
 * @package Shortcode_Arcade_Crypto_Idle_Game
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SACIG_AI_Storyline {

	const CACHE_PREFIX = 'sacig_ai_story_';
	const CACHE_TTL    = DAY_IN_SECONDS;

	/**
	 * Human-readable provider labels.
	 *
	 * @var array
	 */
	public static $providers = array(
		'anthropic'   => 'Claude Haiku 4.5 (Anthropic)',
		'openai_4o'   => 'GPT-4o Mini (OpenAI)',
		'openai_gpt5' => 'GPT-5 Mini (OpenAI)',
		'xai'         => 'Grok 4.1 Fast — Non-Reasoning (xAI)',
	);

	/**
	 * Model identifiers per provider.
	 *
	 * @var array
	 */
	public static $models = array(
		'anthropic'   => 'claude-haiku-4-5-20251001',
		'openai_4o'   => 'gpt-4o-mini',
		'openai_gpt5' => 'gpt-5-mini-2025-08-07',
		'xai'         => 'grok-4-1-fast-non-reasoning',
	);

	/**
	 * API endpoints per provider.
	 *
	 * @var array
	 */
	public static $endpoints = array(
		'anthropic'   => 'https://api.anthropic.com/v1/messages',
		'openai_4o'   => 'https://api.openai.com/v1/chat/completions',
		'openai_gpt5' => 'https://api.openai.com/v1/chat/completions',
		'xai'         => 'https://api.x.ai/v1/chat/completions',
	);

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'admin_post_sacig_flush_ai_cache', array( $this, 'handle_flush_cache' ) );
	}

	/**
	 * Register the storyline REST route.
	 */
	public function register_routes() {
		register_rest_route(
			'sacig/v1',
			'/storyline',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_storyline_request' ),
				'permission_callback' => array( $this, 'check_storyline_permission' ),
				'args'                => array(
					'event_type'     => array(
						'required'          => true,
						'type'              => 'string',
						'enum'              => array( 'upgrade', 'prestige' ),
						'sanitize_callback' => 'sanitize_key',
					),
					'upgrade_id'     => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'upgrade_name'   => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'prestige_level' => array(
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Permission callback for the storyline route.
	 *
	 * The endpoint triggers paid provider calls, so it must not be open to the
	 * public. We require the standard WordPress REST nonce (X-WP-Nonce), which
	 * ties the request to a page served by this site and blocks anonymous
	 * scripts from draining the API key.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return true|WP_Error
	 */
	public function check_storyline_permission( $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( $nonce && wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return true;
		}
		return new WP_Error(
			'sacig_rest_forbidden',
			__( 'Invalid or missing security token.', 'shortcodearcade-crypto-idle-game' ),
			array( 'status' => 401 )
		);
	}

	/**
	 * Simple per-user / per-IP rate limit for the storyline endpoint.
	 *
	 * @return true|WP_Error
	 */
	private function check_rate_limit() {
		$user_id = get_current_user_id();
		if ( $user_id ) {
			$bucket = 'u' . $user_id;
		} else {
			$ip     = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
			$bucket = 'ip' . md5( $ip );
		}
		$key   = 'sacig_ai_rl_' . $bucket;
		$count = (int) get_transient( $key );
		if ( $count >= 30 ) {
			return new WP_Error( 'sacig_ai_rate_limited', __( 'Too many requests. Please slow down.', 'shortcodearcade-crypto-idle-game' ), array( 'status' => 429 ) );
		}
		set_transient( $key, $count + 1, 5 * MINUTE_IN_SECONDS );
		return true;
	}

	/**
	 * Handle a storyline generation request.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_storyline_request( $request ) {
		if ( ! get_option( 'sacig_ai_storyline_enabled', false ) ) {
			return new WP_Error( 'sacig_ai_disabled', __( 'AI storyline is disabled.', 'shortcodearcade-crypto-idle-game' ), array( 'status' => 403 ) );
		}

		$api_key = get_option( 'sacig_ai_api_key', '' );
		if ( empty( $api_key ) ) {
			return new WP_Error( 'sacig_ai_no_key', __( 'No API key configured.', 'shortcodearcade-crypto-idle-game' ), array( 'status' => 403 ) );
		}

		$event_type     = $request->get_param( 'event_type' );
		$upgrade_id     = (string) $request->get_param( 'upgrade_id' );
		$upgrade_name   = (string) $request->get_param( 'upgrade_name' );
		$prestige_level = (int) $request->get_param( 'prestige_level' );

		// Require a concrete upgrade id so distinct upgrades don't collapse into one cache key/prompt.
		if ( 'upgrade' === $event_type && '' === $upgrade_id ) {
			return new WP_Error( 'sacig_ai_bad_request', __( 'An upgrade_id is required for upgrade events.', 'shortcodearcade-crypto-idle-game' ), array( 'status' => 400 ) );
		}

		// Throttle requests to protect the paid provider key from abuse.
		$rate_check = $this->check_rate_limit();
		if ( is_wp_error( $rate_check ) ) {
			return $rate_check;
		}

		if ( 'prestige' === $event_type ) {
			$cache_key   = self::CACHE_PREFIX . md5( 'prestige_' . $prestige_level );
			$user_prompt = sprintf( 'The player just completed Hard Fork (prestige) level %d. Narrate this moment.', $prestige_level );
		} else {
			$cache_key   = self::CACHE_PREFIX . md5( 'upgrade_' . $upgrade_id );
			$user_prompt = sprintf( 'The player just unlocked the "%s" upgrade for the first time. Narrate this moment.', $upgrade_name );
		}

		$cached = get_transient( $cache_key );
		if ( false !== $cached ) {
			return rest_ensure_response(
				array(
					'story'  => $cached,
					'cached' => true,
				)
			);
		}

		$provider = get_option( 'sacig_ai_provider', 'anthropic' );
		if ( ! isset( self::$models[ $provider ] ) ) {
			$provider = 'anthropic';
		}

		$system_prompt = get_option( 'sacig_ai_system_prompt', '' );
		if ( empty( $system_prompt ) ) {
			$system_prompt = self::default_system_prompt();
		}

		if ( 'anthropic' === $provider ) {
			$result = $this->call_anthropic( $api_key, $system_prompt, $user_prompt );
		} else {
			$result = $this->call_openai_compatible( $provider, $api_key, $system_prompt, $user_prompt );
		}

		if ( is_wp_error( $result ) ) {
			// Silent fallback: log for the admin, return a 502 so the frontend skips the popup.
			error_log( 'SACIG AI Storyline error: ' . $result->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return new WP_Error( 'sacig_ai_failed', __( 'Story generation failed.', 'shortcodearcade-crypto-idle-game' ), array( 'status' => 502 ) );
		}

		set_transient( $cache_key, $result, self::CACHE_TTL );
		self::remember_cache_key( $cache_key );

		return rest_ensure_response(
			array(
				'story'  => $result,
				'cached' => false,
			)
		);
	}

	/**
	 * Call the Anthropic Messages API.
	 *
	 * @param string $api_key       API key.
	 * @param string $system_prompt System prompt.
	 * @param string $user_prompt   User prompt.
	 * @return string|WP_Error
	 */
	private function call_anthropic( $api_key, $system_prompt, $user_prompt ) {
		$response = wp_remote_post(
			self::$endpoints['anthropic'],
			array(
				'timeout' => 20,
				'headers' => array(
					'Content-Type'      => 'application/json',
					'x-api-key'         => $api_key,
					'anthropic-version' => '2023-06-01',
				),
				'body'    => wp_json_encode(
					array(
						'model'      => self::$models['anthropic'],
						'max_tokens' => 150,
						'system'     => $system_prompt,
						'messages'   => array(
							array(
								'role'    => 'user',
								'content' => $user_prompt,
							),
						),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}
		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error( 'sacig_anthropic_http', 'Anthropic API returned HTTP ' . wp_remote_retrieve_response_code( $response ) );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		// Sanitize the provider response before caching/returning it as defense against a poisoned API response.
		$text = isset( $data['content'][0]['text'] ) ? sanitize_text_field( trim( $data['content'][0]['text'] ) ) : '';
		if ( '' === $text ) {
			return new WP_Error( 'sacig_anthropic_empty', 'Anthropic API returned an empty response.' );
		}

		return $text;
	}

	/**
	 * Call an OpenAI-compatible chat completions API (OpenAI / xAI).
	 *
	 * @param string $provider      Provider key.
	 * @param string $api_key       API key.
	 * @param string $system_prompt System prompt.
	 * @param string $user_prompt   User prompt.
	 * @return string|WP_Error
	 */
	private function call_openai_compatible( $provider, $api_key, $system_prompt, $user_prompt ) {
		$payload = array(
			'model'    => self::$models[ $provider ],
			'messages' => array(
				array(
					'role'    => 'system',
					'content' => $system_prompt,
				),
				array(
					'role'    => 'user',
					'content' => $user_prompt,
				),
			),
		);

		// GPT-5 family uses max_completion_tokens and does NOT support temperature/top_p.
		if ( 'openai_gpt5' === $provider ) {
			$payload['max_completion_tokens'] = 150;
		} else {
			// GPT-4o Mini and xAI Grok use max_tokens with temperature/top_p.
			$payload['max_tokens']  = 150;
			$payload['temperature'] = 0.8;
			$payload['top_p']       = 1;
		}

		$response = wp_remote_post(
			self::$endpoints[ $provider ],
			array(
				'timeout' => 20,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}
		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error( 'sacig_openai_http', 'AI API returned HTTP ' . wp_remote_retrieve_response_code( $response ) );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		// Sanitize the provider response before caching/returning it as defense against a poisoned API response.
		$text = isset( $data['choices'][0]['message']['content'] ) ? sanitize_text_field( trim( $data['choices'][0]['message']['content'] ) ) : '';
		if ( '' === $text ) {
			return new WP_Error( 'sacig_openai_empty', 'AI API returned an empty response.' );
		}

		return $text;
	}

	/**
	 * Option name for the index of cached story keys.
	 */
	const CACHE_INDEX = 'sacig_ai_story_index';

	/**
	 * Record a cache key in the index so it can be flushed later.
	 *
	 * @param string $key Transient key.
	 */
	private static function remember_cache_key( $key ) {
		$index = get_option( self::CACHE_INDEX, array() );
		if ( ! is_array( $index ) ) {
			$index = array();
		}
		if ( ! in_array( $key, $index, true ) ) {
			$index[] = $key;
			update_option( self::CACHE_INDEX, $index, false );
		}
	}

	/**
	 * Delete all cached AI stories.
	 *
	 * Uses delete_transient() on the tracked key index so it works correctly
	 * with external object caches (Redis/Memcached) and never clears the
	 * site-wide object cache.
	 */
	public static function flush_cache() {
		$index = get_option( self::CACHE_INDEX, array() );
		if ( is_array( $index ) ) {
			foreach ( $index as $key ) {
				delete_transient( $key );
			}
		}
		delete_option( self::CACHE_INDEX );
	}

	/**
	 * Admin-post handler for the "Clear Story Cache" button.
	 */
	public function handle_flush_cache() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'shortcodearcade-crypto-idle-game' ) );
		}
		check_admin_referer( 'sacig_flush_ai_cache', 'sacig_flush_nonce' );

		self::flush_cache();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'          => 'shortcodearcade-crypto-idle-game-ai',
					'cache-flushed' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Default narrator system prompt.
	 *
	 * @return string
	 */
	public static function default_system_prompt() {
		return 'You are a gritty, street-smart crypto hacker narrating a player\'s rise through the digital underground. Your tone is punchy, slightly irreverent, and full of dark tech slang. Keep responses to 1-2 short sentences. No hashtags. No emojis. No markdown.';
	}

	/**
	 * Register AI storyline settings.
	 */
	public function register_settings() {
		register_setting(
			'sacig_ai_group',
			'sacig_ai_storyline_enabled',
			array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
			)
		);
		register_setting(
			'sacig_ai_group',
			'sacig_ai_provider',
			array(
				'type'              => 'string',
				'default'           => 'anthropic',
				'sanitize_callback' => array( $this, 'sanitize_provider' ),
			)
		);
		register_setting(
			'sacig_ai_group',
			'sacig_ai_api_key',
			array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
		register_setting(
			'sacig_ai_group',
			'sacig_ai_system_prompt',
			array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_textarea_field',
			)
		);
		register_setting(
			'sacig_ai_group',
			'sacig_ai_media_prestige_general',
			array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'esc_url_raw',
			)
		);
		register_setting(
			'sacig_ai_group',
			'sacig_ai_media_prestige_5',
			array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'esc_url_raw',
			)
		);
		register_setting(
			'sacig_ai_group',
			'sacig_ai_media_prestige_10',
			array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'esc_url_raw',
			)
		);

		// Per-upgrade media URLs (one per upgrade tier, matching the JS upgrade IDs).
		$upgrade_media_ids = array(
			'betterClicker', 'cpuMiner', 'powerfulClicker', 'gpuRig', 'megaClicker',
			'asicMiner', 'ultraClicker', 'miningFarm', 'godClicker', 'datacenter',
		);
		foreach ( $upgrade_media_ids as $uid ) {
			register_setting(
				'sacig_ai_group',
				'sacig_ai_media_upgrade_' . $uid,
				array(
					'type'              => 'string',
					'default'           => '',
					'sanitize_callback' => 'esc_url_raw',
				)
			);
		}
	}

	/**
	 * Sanitize the provider option against the known provider list.
	 *
	 * @param string $input Raw value.
	 * @return string
	 */
	public function sanitize_provider( $input ) {
		$input = sanitize_key( $input );
		return isset( self::$providers[ $input ] ) ? $input : 'anthropic';
	}

	/**
	 * Sanitize a checkbox value to a strict boolean.
	 *
	 * @param mixed $input Raw value.
	 * @return bool
	 */
	public function sanitize_checkbox( $input ) {
		return (bool) $input;
	}

	/**
	 * Render the AI storyline settings page.
	 */
	public function render_settings_page() {
		$enabled         = (bool) get_option( 'sacig_ai_storyline_enabled', false );
		$provider        = get_option( 'sacig_ai_provider', 'anthropic' );
		$api_key         = get_option( 'sacig_ai_api_key', '' );
		$system_prompt   = get_option( 'sacig_ai_system_prompt', '' );
		$media_general   = get_option( 'sacig_ai_media_prestige_general', '' );
		$media_5         = get_option( 'sacig_ai_media_prestige_5', '' );
		$media_10        = get_option( 'sacig_ai_media_prestige_10', '' );
		?>
		<div class="wrap sacig-arcade-wrap">
			<div class="sacig-arcade-header">
				<div class="sacig-arcade-logo">&#x20BF;</div>
				<h1 class="sacig-arcade-title"><?php esc_html_e( 'AI Storyline', 'shortcodearcade-crypto-idle-game' ); ?></h1>
				<p class="sacig-arcade-subtitle"><?php esc_html_e( 'AI-generated narrative popups on upgrade and prestige events', 'shortcodearcade-crypto-idle-game' ); ?></p>
			</div>
			<div class="sacig-admin-container">
				<div class="sacig-admin-main">
					<form action="options.php" method="post">
						<?php settings_fields( 'sacig_ai_group' ); ?>

						<div class="sacig-arcade-card">
							<h2><?php esc_html_e( 'Configuration', 'shortcodearcade-crypto-idle-game' ); ?></h2>
							<table class="form-table" role="presentation">
								<tr>
									<th scope="row"><?php esc_html_e( 'Enable AI Storyline', 'shortcodearcade-crypto-idle-game' ); ?></th>
									<td>
										<label>
											<input type="hidden" name="sacig_ai_storyline_enabled" value="0">
											<input type="checkbox" name="sacig_ai_storyline_enabled" value="1" <?php checked( $enabled, true ); ?>>
											<?php esc_html_e( 'Enable AI-generated story popups', 'shortcodearcade-crypto-idle-game' ); ?>
										</label>
										<p class="description"><?php esc_html_e( 'Shows AI-generated narrative popups on first purchase of each upgrade tier and each Hard Fork (prestige). Does NOT trigger during contests.', 'shortcodearcade-crypto-idle-game' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="sacig_ai_provider"><?php esc_html_e( 'AI Provider', 'shortcodearcade-crypto-idle-game' ); ?></label></th>
									<td>
										<select id="sacig_ai_provider" name="sacig_ai_provider">
											<?php foreach ( self::$providers as $key => $label ) : ?>
												<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $provider, $key ); ?>><?php echo esc_html( $label ); ?></option>
											<?php endforeach; ?>
										</select>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="sacig_ai_api_key"><?php esc_html_e( 'API Key', 'shortcodearcade-crypto-idle-game' ); ?></label></th>
									<td>
										<input type="password" id="sacig_ai_api_key" name="sacig_ai_api_key" class="regular-text" value="<?php echo esc_attr( $api_key ); ?>" autocomplete="off">
										<p class="description"><?php esc_html_e( 'API key for your selected provider. Stored securely in WordPress options.', 'shortcodearcade-crypto-idle-game' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="sacig_ai_system_prompt"><?php esc_html_e( 'System Prompt', 'shortcodearcade-crypto-idle-game' ); ?></label></th>
									<td>
										<textarea id="sacig_ai_system_prompt" name="sacig_ai_system_prompt" rows="5" class="large-text"><?php echo esc_textarea( $system_prompt ); ?></textarea>
										<p class="description"><?php esc_html_e( "Controls the AI narrator's tone and style. Leave blank to use the default prompt.", 'shortcodearcade-crypto-idle-game' ); ?></p>
										<p class="description"><?php esc_html_e( 'Default prompt:', 'shortcodearcade-crypto-idle-game' ); ?> <code><?php echo esc_html( self::default_system_prompt() ); ?></code></p>
									</td>
								</tr>
							</table>
						</div>

						<div class="sacig-arcade-card">
							<h2><?php esc_html_e( 'Media Settings', 'shortcodearcade-crypto-idle-game' ); ?></h2>
							<table class="form-table" role="presentation">
								<tr>
									<th scope="row"><label for="sacig_ai_media_prestige_general"><?php esc_html_e( 'General Prestige Media', 'shortcodearcade-crypto-idle-game' ); ?></label></th>
									<td>
										<input type="url" id="sacig_ai_media_prestige_general" name="sacig_ai_media_prestige_general" class="regular-text" value="<?php echo esc_url( $media_general ); ?>" placeholder="https://example.com/prestige.mp4">
										<p class="description"><?php esc_html_e( 'Image or MP4 URL shown before the AI popup on any Hard Fork.', 'shortcodearcade-crypto-idle-game' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="sacig_ai_media_prestige_5"><?php esc_html_e( 'Prestige Level 5 Override', 'shortcodearcade-crypto-idle-game' ); ?></label></th>
									<td>
										<input type="url" id="sacig_ai_media_prestige_5" name="sacig_ai_media_prestige_5" class="regular-text" value="<?php echo esc_url( $media_5 ); ?>">
										<p class="description"><?php esc_html_e( 'Leave empty to use the general prestige media URL.', 'shortcodearcade-crypto-idle-game' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="sacig_ai_media_prestige_10"><?php esc_html_e( 'Prestige Level 10 Override', 'shortcodearcade-crypto-idle-game' ); ?></label></th>
									<td>
										<input type="url" id="sacig_ai_media_prestige_10" name="sacig_ai_media_prestige_10" class="regular-text" value="<?php echo esc_url( $media_10 ); ?>">
										<p class="description"><?php esc_html_e( 'Leave empty to use the general prestige media URL.', 'shortcodearcade-crypto-idle-game' ); ?></p>
									</td>
								</tr>
							</table>
						</div>

						<div class="sacig-arcade-card">
							<h2><?php esc_html_e( 'Upgrade Media', 'shortcodearcade-crypto-idle-game' ); ?></h2>
							<p class="description"><?php esc_html_e( 'Optional image or MP4 URL shown before the AI popup for each upgrade tier. Leave empty to skip media for that upgrade.', 'shortcodearcade-crypto-idle-game' ); ?></p>
							<table class="form-table" role="presentation">
								<?php
								$sacig_upgrade_media = array(
									'betterClicker'   => __( 'Better Pickaxe', 'shortcodearcade-crypto-idle-game' ),
									'cpuMiner'        => __( 'CPU Miner', 'shortcodearcade-crypto-idle-game' ),
									'powerfulClicker' => __( 'Diamond Pickaxe', 'shortcodearcade-crypto-idle-game' ),
									'gpuRig'          => __( 'GPU Mining Rig', 'shortcodearcade-crypto-idle-game' ),
									'megaClicker'     => __( 'Quantum Pickaxe', 'shortcodearcade-crypto-idle-game' ),
									'asicMiner'       => __( 'ASIC Miner', 'shortcodearcade-crypto-idle-game' ),
									'ultraClicker'    => __( 'Neutron Star Drill', 'shortcodearcade-crypto-idle-game' ),
									'miningFarm'      => __( 'Mining Farm', 'shortcodearcade-crypto-idle-game' ),
									'godClicker'      => __( 'Black Hole Extractor', 'shortcodearcade-crypto-idle-game' ),
									'datacenter'      => __( 'Data Center', 'shortcodearcade-crypto-idle-game' ),
								);
								foreach ( $sacig_upgrade_media as $sacig_uid => $sacig_uname ) :
									$sacig_opt = 'sacig_ai_media_upgrade_' . $sacig_uid;
									$sacig_val = get_option( $sacig_opt, '' );
									?>
									<tr>
										<th scope="row"><label for="<?php echo esc_attr( $sacig_opt ); ?>"><?php echo esc_html( $sacig_uname ); ?></label></th>
										<td>
											<input type="url" id="<?php echo esc_attr( $sacig_opt ); ?>" name="<?php echo esc_attr( $sacig_opt ); ?>" class="regular-text" value="<?php echo esc_url( $sacig_val ); ?>" placeholder="https://example.com/clip.mp4">
											<p class="description"><?php esc_html_e( 'Image or MP4 URL shown before the AI popup for this upgrade. Leave empty to skip.', 'shortcodearcade-crypto-idle-game' ); ?></p>
										</td>
									</tr>
								<?php endforeach; ?>
							</table>
						</div>

						<?php submit_button( __( 'Save AI Settings', 'shortcodearcade-crypto-idle-game' ) ); ?>
					</form>

					<div class="sacig-arcade-card">
						<h2><?php esc_html_e( 'Cache Management', 'shortcodearcade-crypto-idle-game' ); ?></h2>
						<p><?php esc_html_e( 'AI stories are cached for 24 hours per event. Same milestone = same story for all players. Saving settings clears the cache automatically.', 'shortcodearcade-crypto-idle-game' ); ?></p>
						<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
							<input type="hidden" name="action" value="sacig_flush_ai_cache">
							<?php wp_nonce_field( 'sacig_flush_ai_cache', 'sacig_flush_nonce' ); ?>
							<button type="submit" class="button button-secondary"><?php esc_html_e( 'Clear Story Cache Now', 'shortcodearcade-crypto-idle-game' ); ?></button>
						</form>
					</div>

					<div class="sacig-arcade-card sacig-info-card sacig-wp7-notice">
						<h3>&#x1F916; <?php esc_html_e( 'WordPress 7.0 AI Client', 'shortcodearcade-crypto-idle-game' ); ?></h3>
						<p><?php esc_html_e( 'WordPress 7.0 introduces a native AI Client API and Connections Screen for central API key management across plugins. A future update will optionally integrate with this system. For now, manage your AI provider keys above.', 'shortcodearcade-crypto-idle-game' ); ?></p>
					</div>
				</div>

				<div class="sacig-admin-sidebar">
					<div class="sacig-sidebar-box">
						<h3>&#x26A1; <?php esc_html_e( 'How It Works', 'shortcodearcade-crypto-idle-game' ); ?></h3>
						<ul>
							<li><?php esc_html_e( 'Player unlocks upgrade for first time → popup fires', 'shortcodearcade-crypto-idle-game' ); ?></li>
							<li><?php esc_html_e( 'Player completes Hard Fork → popup fires', 'shortcodearcade-crypto-idle-game' ); ?></li>
							<li><?php esc_html_e( 'Same upgrade again → no popup', 'shortcodearcade-crypto-idle-game' ); ?></li>
							<li><?php esc_html_e( 'Active contest → no popup', 'shortcodearcade-crypto-idle-game' ); ?></li>
							<li><?php esc_html_e( '24h shared cache: everyone sees same story per milestone', 'shortcodearcade-crypto-idle-game' ); ?></li>
						</ul>
					</div>
					<div class="sacig-sidebar-box">
						<h3>&#x1F4B0; <?php esc_html_e( 'API Cost Tips', 'shortcodearcade-crypto-idle-game' ); ?></h3>
						<ul>
							<li><?php esc_html_e( '10 upgrades + prestige events = max ~11 cached messages', 'shortcodearcade-crypto-idle-game' ); ?></li>
							<li><?php esc_html_e( 'At ~80 tokens/response, cost is minimal even at high traffic', 'shortcodearcade-crypto-idle-game' ); ?></li>
							<li><?php esc_html_e( 'Claude Haiku is the most cost-effective option', 'shortcodearcade-crypto-idle-game' ); ?></li>
							<li><?php esc_html_e( 'GPT-5 Mini has no temperature support — simpler but less creative variance', 'shortcodearcade-crypto-idle-game' ); ?></li>
						</ul>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}
