<?php
/**
 * AI Storyline Class
 *
 * Handles the REST endpoint that generates narrative popups
 * on first upgrade unlock and on Hard Fork (prestige).
 *
 * New in 1.0.0.
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CMT_AI_Storyline {

    /**
     * Transient key prefix for cached stories.
     */
    const CACHE_PREFIX = 'cmt_ai_story_';

    /**
     * Transient TTL for cached stories (24 hours).
     */
    const CACHE_TTL = DAY_IN_SECONDS;

    /**
     * Supported AI providers and their human labels.
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
     * Model IDs per provider.
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
     * Constructor
     */
    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    /**
     * Register REST API routes.
     */
    public function register_routes() {
        register_rest_route( 'cmt/v1', '/storyline', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_storyline_request' ),
            'permission_callback' => '__return_true',
            'args'                => array(
                'event_type' => array(
                    'required' => true,
                    'type'     => 'string',
                    'enum'     => array( 'upgrade', 'prestige' ),
                ),
                'upgrade_id' => array(
                    'required' => false,
                    'type'     => 'string',
                ),
                'upgrade_name' => array(
                    'required' => false,
                    'type'     => 'string',
                ),
                'prestige_level' => array(
                    'required' => false,
                    'type'     => 'integer',
                ),
            ),
        ) );
    }

    /**
     * Handle a storyline request.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_storyline_request( $request ) {
        if ( ! get_option( 'cmt_ai_storyline_enabled', false ) ) {
            return new WP_Error(
                'cmt_ai_disabled',
                'AI Storyline is disabled.',
                array( 'status' => 403 )
            );
        }

        $api_key = get_option( 'cmt_ai_api_key', '' );
        if ( empty( $api_key ) ) {
            return new WP_Error(
                'cmt_ai_no_key',
                'AI provider API key is not configured.',
                array( 'status' => 500 )
            );
        }

        $event_type  = $request->get_param( 'event_type' );
        $upgrade_id  = sanitize_text_field( (string) $request->get_param( 'upgrade_id' ) );
        $upgrade_name = sanitize_text_field( (string) $request->get_param( 'upgrade_name' ) );
        $prestige_level = intval( $request->get_param( 'prestige_level' ) );

        if ( $event_type === 'upgrade' ) {
            $cache_key  = self::CACHE_PREFIX . md5( 'upgrade_' . $upgrade_id );
            $user_prompt = sprintf(
                'The player has just unlocked "%s" for the first time. Write a short punchy in-character reaction. Under 2 sentences.',
                $upgrade_name
            );
        } else {
            $cache_key  = self::CACHE_PREFIX . md5( 'prestige_' . $prestige_level );
            $user_prompt = sprintf(
                'The player has just completed Hard Fork #%d. Write a short punchy in-character reaction referencing they reset but gained permanent power. Under 2 sentences.',
                $prestige_level
            );
        }

        $cached = get_transient( $cache_key );
        if ( false !== $cached && '' !== $cached ) {
            return rest_ensure_response( array(
                'success' => true,
                'message' => $cached,
                'cached'  => true,
            ) );
        }

        $provider = get_option( 'cmt_ai_provider', 'anthropic' );
        if ( ! isset( self::$providers[ $provider ] ) ) {
            $provider = 'anthropic';
        }

        if ( 'anthropic' === $provider ) {
            $result = $this->call_anthropic( $api_key, $user_prompt );
        } else {
            $result = $this->call_openai_compatible( $provider, $api_key, $user_prompt );
        }

        if ( is_wp_error( $result ) ) {
            error_log( '[CMT AI Storyline] Provider error: ' . $result->get_error_message() );
            return new WP_Error(
                'cmt_ai_provider_error',
                'Could not generate story right now.',
                array( 'status' => 502 )
            );
        }

        set_transient( $cache_key, $result, self::CACHE_TTL );

        return rest_ensure_response( array(
            'success' => true,
            'message' => $result,
            'cached'  => false,
        ) );
    }

    /**
     * Call the Anthropic Messages API.
     *
     * @param string $api_key
     * @param string $user_prompt
     * @return string|WP_Error
     */
    private function call_anthropic( $api_key, $user_prompt ) {
        $system_prompt = get_option( 'cmt_ai_system_prompt', self::default_system_prompt() );

        $body = array(
            'model'      => self::$models['anthropic'],
            'max_tokens' => 150,
            'system'     => $system_prompt,
            'messages'   => array(
                array(
                    'role'    => 'user',
                    'content' => $user_prompt,
                ),
            ),
        );

        $response = wp_remote_post( self::$endpoints['anthropic'], array(
            'timeout' => 15,
            'headers' => array(
                'Content-Type'       => 'application/json',
                'x-api-key'          => $api_key,
                'anthropic-version'  => '2023-06-01',
            ),
            'body'    => wp_json_encode( $body ),
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( 200 !== (int) $code ) {
            return new WP_Error(
                'cmt_ai_http_error',
                'Anthropic returned HTTP ' . intval( $code )
            );
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $data['content'][0]['text'] ) ) {
            return new WP_Error( 'cmt_ai_empty', 'Empty response from Anthropic.' );
        }

        return sanitize_text_field( $data['content'][0]['text'] );
    }

    /**
     * Call an OpenAI-compatible chat completions API (OpenAI, xAI).
     *
     * @param string $provider   'openai_4o', 'openai_gpt5', or 'xai'
     * @param string $api_key
     * @param string $user_prompt
     * @return string|WP_Error
     */
    private function call_openai_compatible( $provider, $api_key, $user_prompt ) {
        $system_prompt = get_option( 'cmt_ai_system_prompt', $this->default_system_prompt() );

        $messages = array(
            array( 'role' => 'system', 'content' => $system_prompt ),
            array( 'role' => 'user',   'content' => $user_prompt   ),
        );

        // GPT-5 family: no temperature, no top_p, uses max_completion_tokens not max_tokens.
        // GPT-4o and xAI: standard parameters.
        if ( $provider === 'openai_gpt5' ) {
            $payload = array(
                'model'                 => self::$models[ $provider ],
                'max_completion_tokens' => 150,
                'messages'              => $messages,
            );
        } else {
            $payload = array(
                'model'       => self::$models[ $provider ],
                'max_tokens'  => 150,
                'temperature' => 0.8,
                'top_p'       => 1,
                'messages'    => $messages,
            );
        }

        $response = wp_remote_post(
            self::$endpoints[ $provider ],
            array(
                'timeout' => 15,
                'headers' => array(
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $api_key,
                ),
                'body' => wp_json_encode( $payload ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            return new WP_Error(
                $provider . '_http_error',
                strtoupper( $provider ) . ' API returned HTTP ' . $code
            );
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $data['choices'][0]['message']['content'] ) ) {
            return new WP_Error( $provider . '_empty', 'Provider returned an empty response.' );
        }

        return sanitize_text_field( $data['choices'][0]['message']['content'] );
    }

    /**
     * Default system prompt used when the admin hasn't customized one.
     *
     * @return string
     */
    public static function default_system_prompt() {
        return "You are a gritty, street-smart crypto hacker narrating a player's rise through the digital underground. Your tone is punchy, slightly irreverent, and full of dark tech slang. Keep responses to 1-2 short sentences. No hashtags. No emojis. No markdown.";
    }

    /**
     * Get the media URL for a given event.
     * Returns empty string if no URL is configured.
     *
     * @param string   $event_type  'upgrade' or 'prestige'
     * @param string   $upgrade_id  Upgrade ID (only for upgrade events)
     * @param int|null $prestige_level  Prestige level (only for prestige events)
     * @return string  URL or empty string
     */
    public static function get_media_url( $event_type, $upgrade_id = '', $prestige_level = null ) {
        if ( $event_type === 'upgrade' ) {
            $key = 'cmt_ai_media_upgrade_' . sanitize_key( $upgrade_id );
            return (string) get_option( $key, '' );
        }

        if ( $event_type === 'prestige' ) {
            if ( $prestige_level === 5 ) {
                $milestone = get_option( 'cmt_ai_media_prestige_5', '' );
                if ( ! empty( $milestone ) ) {
                    return (string) $milestone;
                }
            }
            if ( $prestige_level === 10 ) {
                $milestone = get_option( 'cmt_ai_media_prestige_10', '' );
                if ( ! empty( $milestone ) ) {
                    return (string) $milestone;
                }
            }
            return (string) get_option( 'cmt_ai_media_prestige_general', '' );
        }

        return '';
    }

    /**
     * Flush all cached AI stories.
     *
     * Intentionally uses a direct query rather than iterating known keys —
     * the set of cache keys is generated from content hashes, so there is
     * no persistent registry to walk. Options cache is invalidated via
     * wp_cache_flush_group for the options group after the delete.
     */
    public static function flush_cache() {
        global $wpdb;

        $like_value   = $wpdb->esc_like( '_transient_' . self::CACHE_PREFIX ) . '%';
        $like_timeout = $wpdb->esc_like( '_transient_timeout_' . self::CACHE_PREFIX ) . '%';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional cache management: sweep transients by prefix.
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                $like_value,
                $like_timeout
            )
        );

        wp_cache_flush();
    }
}
