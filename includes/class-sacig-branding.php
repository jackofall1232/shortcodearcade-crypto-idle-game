<?php
/**
 * Branding Class
 *
 * Manages white-label customization settings for the game.
 * Allows site admins to set custom colors, coin image, game title,
 * currency name, and footer text.
 *
 * @package Shortcode_Arcade_Crypto_Idle_Game
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SACIG_Branding {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_color_picker' ) );
	}

	/**
	 * Register branding settings, section, and fields.
	 */
	public function register_settings() {
		register_setting(
			'sacig_branding_group',
			'sacig_branding_enabled',
			array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
			)
		);
		register_setting(
			'sacig_branding_group',
			'sacig_custom_coin',
			array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'esc_url_raw',
			)
		);
		register_setting(
			'sacig_branding_group',
			'sacig_color_primary',
			array(
				'type'              => 'string',
				'default'           => '#00ffff',
				'sanitize_callback' => 'sanitize_hex_color',
			)
		);
		register_setting(
			'sacig_branding_group',
			'sacig_color_secondary',
			array(
				'type'              => 'string',
				'default'           => '#ff00ff',
				'sanitize_callback' => 'sanitize_hex_color',
			)
		);
		register_setting(
			'sacig_branding_group',
			'sacig_color_accent',
			array(
				'type'              => 'string',
				'default'           => '#ffff00',
				'sanitize_callback' => 'sanitize_hex_color',
			)
		);
		register_setting(
			'sacig_branding_group',
			'sacig_game_title',
			array(
				'type'              => 'string',
				'default'           => 'Crypto Arcade',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
		register_setting(
			'sacig_branding_group',
			'sacig_game_subtitle',
			array(
				'type'              => 'string',
				'default'           => 'Click. Mine. Dominate.',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
		register_setting(
			'sacig_branding_group',
			'sacig_currency_name',
			array(
				'type'              => 'string',
				'default'           => 'Satoshis',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
		register_setting(
			'sacig_branding_group',
			'sacig_currency_symbol',
			array(
				'type'              => 'string',
				'default'           => '&#x20BF;',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
		register_setting(
			'sacig_branding_group',
			'sacig_footer_text',
			array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		add_settings_section(
			'sacig_branding_section',
			__( 'Branding Settings', 'shortcodearcade-crypto-idle-game' ),
			array( $this, 'render_section_description' ),
			'shortcodearcade-crypto-idle-game-branding'
		);

		add_settings_field(
			'sacig_branding_enabled',
			__( 'Enable Branding', 'shortcodearcade-crypto-idle-game' ),
			array( $this, 'render_enable_branding_field' ),
			'shortcodearcade-crypto-idle-game-branding',
			'sacig_branding_section'
		);
		add_settings_field(
			'sacig_custom_coin',
			__( 'Custom Coin Image', 'shortcodearcade-crypto-idle-game' ),
			array( $this, 'render_custom_coin_field' ),
			'shortcodearcade-crypto-idle-game-branding',
			'sacig_branding_section'
		);
		add_settings_field(
			'sacig_brand_colors',
			__( 'Color Scheme', 'shortcodearcade-crypto-idle-game' ),
			array( $this, 'render_color_fields' ),
			'shortcodearcade-crypto-idle-game-branding',
			'sacig_branding_section'
		);
		add_settings_field(
			'sacig_game_title',
			__( 'Game Title', 'shortcodearcade-crypto-idle-game' ),
			array( $this, 'render_game_title_field' ),
			'shortcodearcade-crypto-idle-game-branding',
			'sacig_branding_section'
		);
		add_settings_field(
			'sacig_game_subtitle',
			__( 'Game Subtitle', 'shortcodearcade-crypto-idle-game' ),
			array( $this, 'render_game_subtitle_field' ),
			'shortcodearcade-crypto-idle-game-branding',
			'sacig_branding_section'
		);
		add_settings_field(
			'sacig_currency_name',
			__( 'Currency Name', 'shortcodearcade-crypto-idle-game' ),
			array( $this, 'render_currency_name_field' ),
			'shortcodearcade-crypto-idle-game-branding',
			'sacig_branding_section'
		);
		add_settings_field(
			'sacig_currency_symbol',
			__( 'Currency Symbol', 'shortcodearcade-crypto-idle-game' ),
			array( $this, 'render_currency_symbol_field' ),
			'shortcodearcade-crypto-idle-game-branding',
			'sacig_branding_section'
		);
		add_settings_field(
			'sacig_footer_text',
			__( 'Footer Text', 'shortcodearcade-crypto-idle-game' ),
			array( $this, 'render_footer_text_field' ),
			'shortcodearcade-crypto-idle-game-branding',
			'sacig_branding_section'
		);
	}

	/**
	 * Retrieve all current branding settings.
	 *
	 * @return array
	 */
	public static function get_branding_settings() {
		return array(
			'enabled'         => (bool) get_option( 'sacig_branding_enabled', false ),
			'custom_coin'     => get_option( 'sacig_custom_coin', '' ),
			'colors'          => array(
				'primary'   => get_option( 'sacig_color_primary', '#00ffff' ),
				'secondary' => get_option( 'sacig_color_secondary', '#ff00ff' ),
				'accent'    => get_option( 'sacig_color_accent', '#ffff00' ),
			),
			'game_title'      => get_option( 'sacig_game_title', 'Crypto Arcade' ),
			'game_subtitle'   => get_option( 'sacig_game_subtitle', 'Click. Mine. Dominate.' ),
			'currency_name'   => get_option( 'sacig_currency_name', 'Satoshis' ),
			'currency_symbol' => get_option( 'sacig_currency_symbol', '&#x20BF;' ),
			'footer_text'     => get_option( 'sacig_footer_text', '' ),
		);
	}

	/**
	 * Render the branding settings page.
	 */
	public function render_settings_page() {
		?>
		<div class="wrap sacig-arcade-wrap">
			<div class="sacig-arcade-header">
				<div class="sacig-arcade-logo">&#x20BF;</div>
				<h1 class="sacig-arcade-title"><?php esc_html_e( 'Branding', 'shortcodearcade-crypto-idle-game' ); ?></h1>
				<p class="sacig-arcade-subtitle"><?php esc_html_e( 'Customize the game to match your brand', 'shortcodearcade-crypto-idle-game' ); ?></p>
			</div>
			<div class="sacig-admin-container">
				<div class="sacig-admin-main">
					<div class="sacig-arcade-card">
						<form action="options.php" method="post">
							<?php
							settings_fields( 'sacig_branding_group' );
							do_settings_sections( 'shortcodearcade-crypto-idle-game-branding' );
							submit_button( __( 'Save Branding', 'shortcodearcade-crypto-idle-game' ) );
							?>
						</form>
					</div>
				</div>
				<div class="sacig-admin-sidebar">
					<div class="sacig-sidebar-box">
						<h3>&#x1F3A8; <?php esc_html_e( 'Branding Tips', 'shortcodearcade-crypto-idle-game' ); ?></h3>
						<ul>
							<li><?php esc_html_e( 'Use high-contrast colors for visibility', 'shortcodearcade-crypto-idle-game' ); ?></li>
							<li><?php esc_html_e( 'SVG images scale perfectly on all devices', 'shortcodearcade-crypto-idle-game' ); ?></li>
							<li><?php esc_html_e( 'Keep titles short for mobile displays', 'shortcodearcade-crypto-idle-game' ); ?></li>
							<li><?php esc_html_e( 'Test changes on your game page after saving', 'shortcodearcade-crypto-idle-game' ); ?></li>
						</ul>
					</div>
					<div class="sacig-sidebar-box">
						<h3>&#x1F3AF; <?php esc_html_e( 'Preview Changes', 'shortcodearcade-crypto-idle-game' ); ?></h3>
						<p><?php esc_html_e( 'After saving, visit your game page to see changes. Changes apply to: game title, coin image, color scheme, currency labels, footer text.', 'shortcodearcade-crypto-idle-game' ); ?></p>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the settings section description.
	 */
	public function render_section_description() {
		echo '<p>' . esc_html__( 'Customize colors, imagery, and text to make the game match your brand.', 'shortcodearcade-crypto-idle-game' ) . '</p>';
	}

	/**
	 * Render the enable-branding checkbox field.
	 */
	public function render_enable_branding_field() {
		$enabled = (bool) get_option( 'sacig_branding_enabled', false );
		?>
		<label>
			<input type="hidden" name="sacig_branding_enabled" value="0">
			<input type="checkbox" name="sacig_branding_enabled" value="1" <?php checked( $enabled, true ); ?>>
			<?php esc_html_e( 'Enable custom branding', 'shortcodearcade-crypto-idle-game' ); ?>
		</label>
		<p class="description"><?php esc_html_e( 'When enabled, your custom branding settings replace the defaults.', 'shortcodearcade-crypto-idle-game' ); ?></p>
		<?php
	}

	/**
	 * Render the custom coin image field.
	 */
	public function render_custom_coin_field() {
		$coin = get_option( 'sacig_custom_coin', '' );
		?>
		<input type="hidden" id="sacig_custom_coin" name="sacig_custom_coin" value="<?php echo esc_url( $coin ); ?>">
		<div id="sacig-coin-preview" class="sacig-coin-preview">
			<?php if ( $coin ) : ?>
				<img src="<?php echo esc_url( $coin ); ?>" alt="<?php esc_attr_e( 'Custom coin preview', 'shortcodearcade-crypto-idle-game' ); ?>" style="max-width:96px;height:auto;">
			<?php else : ?>
				<span class="sacig-coin-placeholder"><span class="dashicons dashicons-format-image"></span> <?php esc_html_e( 'No custom coin image set', 'shortcodearcade-crypto-idle-game' ); ?></span>
			<?php endif; ?>
		</div>
		<p>
			<button type="button" id="sacig-upload-coin" class="button button-secondary"><?php esc_html_e( 'Select Image', 'shortcodearcade-crypto-idle-game' ); ?></button>
			<button type="button" class="button sacig-remove-coin"<?php echo $coin ? '' : ' style="display:none;"'; ?>><?php esc_html_e( 'Remove', 'shortcodearcade-crypto-idle-game' ); ?></button>
		</p>
		<p class="description"><?php esc_html_e( 'Upload a custom coin/token image (SVG, PNG, or JPG). Recommended: 512x512px.', 'shortcodearcade-crypto-idle-game' ); ?></p>
		<?php
	}

	/**
	 * Render the three color picker fields.
	 */
	public function render_color_fields() {
		$primary   = get_option( 'sacig_color_primary', '#00ffff' );
		$secondary = get_option( 'sacig_color_secondary', '#ff00ff' );
		$accent    = get_option( 'sacig_color_accent', '#ffff00' );
		?>
		<div class="sacig-color-picker-group">
			<div class="sacig-color-field">
				<label for="sacig_color_primary"><strong><?php esc_html_e( 'Primary Color', 'shortcodearcade-crypto-idle-game' ); ?></strong></label><br>
				<input type="text" id="sacig_color_primary" name="sacig_color_primary" class="sacig-color-picker" value="<?php echo esc_attr( $primary ); ?>" data-default-color="#00ffff">
				<p class="description"><?php esc_html_e( 'Main neon highlights', 'shortcodearcade-crypto-idle-game' ); ?></p>
			</div>
			<div class="sacig-color-field">
				<label for="sacig_color_secondary"><strong><?php esc_html_e( 'Secondary Color', 'shortcodearcade-crypto-idle-game' ); ?></strong></label><br>
				<input type="text" id="sacig_color_secondary" name="sacig_color_secondary" class="sacig-color-picker" value="<?php echo esc_attr( $secondary ); ?>" data-default-color="#ff00ff">
				<p class="description"><?php esc_html_e( 'Accent and glow effects', 'shortcodearcade-crypto-idle-game' ); ?></p>
			</div>
			<div class="sacig-color-field">
				<label for="sacig_color_accent"><strong><?php esc_html_e( 'Accent Color', 'shortcodearcade-crypto-idle-game' ); ?></strong></label><br>
				<input type="text" id="sacig_color_accent" name="sacig_color_accent" class="sacig-color-picker" value="<?php echo esc_attr( $accent ); ?>" data-default-color="#ffff00">
				<p class="description"><?php esc_html_e( 'Gold/value indicators', 'shortcodearcade-crypto-idle-game' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the game title field.
	 */
	public function render_game_title_field() {
		$value = get_option( 'sacig_game_title', 'Crypto Arcade' );
		?>
		<input type="text" id="sacig_game_title" name="sacig_game_title" class="regular-text" maxlength="50" value="<?php echo esc_attr( $value ); ?>">
		<p class="description"><?php esc_html_e( 'Main title at the top of the game. Max 50 characters.', 'shortcodearcade-crypto-idle-game' ); ?></p>
		<?php
	}

	/**
	 * Render the game subtitle field.
	 */
	public function render_game_subtitle_field() {
		$value = get_option( 'sacig_game_subtitle', 'Click. Mine. Dominate.' );
		?>
		<input type="text" id="sacig_game_subtitle" name="sacig_game_subtitle" class="regular-text" maxlength="80" value="<?php echo esc_attr( $value ); ?>">
		<p class="description"><?php esc_html_e( 'Tagline shown under the title. Max 80 characters.', 'shortcodearcade-crypto-idle-game' ); ?></p>
		<?php
	}

	/**
	 * Render the currency name field.
	 */
	public function render_currency_name_field() {
		$value = get_option( 'sacig_currency_name', 'Satoshis' );
		?>
		<input type="text" id="sacig_currency_name" name="sacig_currency_name" class="regular-text" maxlength="20" value="<?php echo esc_attr( $value ); ?>">
		<p class="description"><?php esc_html_e( 'In-game currency name (e.g. Satoshis, Coins, Gems). Max 20 characters.', 'shortcodearcade-crypto-idle-game' ); ?></p>
		<?php
	}

	/**
	 * Render the currency symbol field.
	 */
	public function render_currency_symbol_field() {
		$value = get_option( 'sacig_currency_symbol', '&#x20BF;' );
		?>
		<input type="text" id="sacig_currency_symbol" name="sacig_currency_symbol" class="regular-text" maxlength="5" value="<?php echo esc_attr( $value ); ?>">
		<p class="description"><?php echo wp_kses_post( __( 'Currency symbol shown next to values (e.g. &#x20BF; &#x24; &#x20AC;). Max 5 characters.', 'shortcodearcade-crypto-idle-game' ) ); ?></p>
		<?php
	}

	/**
	 * Render the footer text field.
	 */
	public function render_footer_text_field() {
		$value = get_option( 'sacig_footer_text', '' );
		?>
		<input type="text" id="sacig_footer_text" name="sacig_footer_text" class="large-text" maxlength="120" value="<?php echo esc_attr( $value ); ?>">
		<p class="description"><?php echo wp_kses_post( __( 'Text shown in the game footer. Supports {year} and {title} variables.', 'shortcodearcade-crypto-idle-game' ) ); ?></p>
		<?php
	}

	/**
	 * Enqueue the WordPress color picker (and media uploader for the coin field).
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public function enqueue_color_picker( $hook ) {
		if ( strpos( $hook, 'shortcodearcade-crypto-idle-game' ) === false ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
		// Required for the custom coin image "Select Image" button.
		wp_enqueue_media();
	}

	/**
	 * Sanitize a checkbox value to a strict boolean.
	 *
	 * Public because WordPress invokes registered sanitize callbacks from
	 * global scope; a private method would fatal when called via the
	 * sanitize_option_{$option} filter.
	 *
	 * @param mixed $input Raw value.
	 * @return bool
	 */
	public function sanitize_checkbox( $input ) {
		return (bool) $input;
	}
}
