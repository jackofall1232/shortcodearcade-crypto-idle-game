<?php
/**
 * Admin Settings Class
 *
 * Handles the admin settings pages for Shortcode Arcade Crypto Idle Game.
 * Manages cloud saves, leaderboard configuration, branding, login pages,
 * and the arcade-themed admin UI.
 *
 * @package Shortcode_Arcade_Crypto_Idle_Game
 * @since 0.4.6
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class SACIG_Admin {

    /**
     * Branding feature instance.
     *
     * @var SACIG_Branding|null
     */
    private $branding;

    /**
     * Login pages feature instance.
     *
     * @var SACIG_Login_Pages|null
     */
    private $login_pages;

    /**
     * AI storyline feature instance.
     *
     * @var SACIG_AI_Storyline|null
     */
    private $ai_storyline;

    /**
     * Difficulty intensity multipliers keyed by difficulty slug.
     *
     * @var array
     */
    private static $difficulty_map = array(
        'easy'   => 0.6,
        'medium' => 0.8,
        'hard'   => 1.0,
    );

    /**
     * Constructor
     *
     * @param SACIG_Branding|null     $branding     Shared branding instance.
     * @param SACIG_Login_Pages|null  $login_pages  Shared login pages instance.
     * @param SACIG_AI_Storyline|null $ai_storyline Shared AI storyline instance.
     */
    public function __construct( $branding = null, $login_pages = null, $ai_storyline = null ) {
        $this->branding     = $branding;
        $this->login_pages  = $login_pages;
        $this->ai_storyline = $ai_storyline;

        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('admin_head', array($this, 'output_menu_color_css'));
    }

    /**
     * Add admin menu pages
     *
     * Registers a top-level "Crypto Arcade" menu with five subpages.
     */
    public function add_admin_menu() {
        $icon_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">'
            . '<circle cx="10" cy="10" r="9" fill="#f7931a"/>'
            . '<circle cx="10" cy="10" r="7.5" fill="#f9a922"/>'
            . '<text x="10" y="14" text-anchor="middle" '
            . 'font-size="10" font-weight="900" fill="#ffffff" '
            . 'font-family="Arial,sans-serif">&#x20BF;</text>'
            . '</svg>';

        $icon = 'data:image/svg+xml;base64,' . base64_encode( $icon_svg );

        add_menu_page(
            __( 'Crypto Arcade', 'shortcodearcade-crypto-idle-game' ),
            __( 'Crypto Arcade', 'shortcodearcade-crypto-idle-game' ),
            'manage_options',
            'shortcodearcade-crypto-idle-game',
            array($this, 'render_game_settings_page'),
            $icon,
            30
        );

        add_submenu_page(
            'shortcodearcade-crypto-idle-game',
            __( 'Game Settings', 'shortcodearcade-crypto-idle-game' ),
            __( 'Game Settings', 'shortcodearcade-crypto-idle-game' ),
            'manage_options',
            'shortcodearcade-crypto-idle-game',
            array($this, 'render_game_settings_page')
        );

        add_submenu_page(
            'shortcodearcade-crypto-idle-game',
            __( 'Branding', 'shortcodearcade-crypto-idle-game' ),
            __( 'Branding', 'shortcodearcade-crypto-idle-game' ),
            'manage_options',
            'shortcodearcade-crypto-idle-game-branding',
            array($this, 'render_branding_page')
        );

        add_submenu_page(
            'shortcodearcade-crypto-idle-game',
            __( 'AI Storyline — Crypto Arcade', 'shortcodearcade-crypto-idle-game' ),
            __( 'AI Storyline', 'shortcodearcade-crypto-idle-game' ),
            'manage_options',
            'shortcodearcade-crypto-idle-game-ai',
            array( $this, 'render_ai_storyline_page' )
        );

        add_submenu_page(
            'shortcodearcade-crypto-idle-game',
            __( 'Login Pages', 'shortcodearcade-crypto-idle-game' ),
            __( 'Login Pages', 'shortcodearcade-crypto-idle-game' ),
            'manage_options',
            'shortcodearcade-crypto-idle-game-login',
            array($this, 'render_login_page')
        );

        add_submenu_page(
            'shortcodearcade-crypto-idle-game',
            __( 'Leaderboard', 'shortcodearcade-crypto-idle-game' ),
            __( 'Leaderboard', 'shortcodearcade-crypto-idle-game' ),
            'manage_options',
            'shortcodearcade-crypto-idle-game-leaderboard',
            array($this, 'render_leaderboard_page')
        );

        add_submenu_page(
            'shortcodearcade-crypto-idle-game',
            __( 'About', 'shortcodearcade-crypto-idle-game' ),
            __( 'About', 'shortcodearcade-crypto-idle-game' ),
            'manage_options',
            'shortcodearcade-crypto-idle-game-about',
            array($this, 'render_about_page')
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        // --- General / game settings group (existing) ---
        register_setting('sacig_settings_group', 'sacig_enable_cloud_saves', array(
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => array($this, 'sanitize_checkbox')
        ));

        register_setting('sacig_settings_group', 'sacig_enable_leaderboard', array(
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => array($this, 'sanitize_checkbox')
        ));

        register_setting('sacig_settings_group', 'sacig_leaderboard_limit', array(
            'type' => 'integer',
            'default' => 10,
            'sanitize_callback' => array($this, 'sanitize_leaderboard_limit')
        ));

        // Add settings section
        add_settings_section(
            'sacig_main_section',
            'Game Settings',
            array($this, 'render_section_description'),
            'shortcodearcade-crypto-idle-game'
        );

        // Add settings fields
        add_settings_field(
            'sacig_enable_cloud_saves',
            'Enable Cloud Saves',
            array($this, 'render_cloud_saves_field'),
            'shortcodearcade-crypto-idle-game',
            'sacig_main_section'
        );

        add_settings_field(
            'sacig_enable_leaderboard',
            'Enable Leaderboard',
            array($this, 'render_leaderboard_field'),
            'shortcodearcade-crypto-idle-game',
            'sacig_main_section'
        );

        add_settings_field(
            'sacig_leaderboard_limit',
            'Leaderboard Size',
            array($this, 'render_leaderboard_limit_field'),
            'shortcodearcade-crypto-idle-game',
            'sacig_main_section'
        );

        // --- Gameplay settings (difficulty & anti-bot) ---
        register_setting( 'sacig_settings_group', 'sacig_difficulty', array(
            'type'              => 'string',
            'default'           => 'medium',
            'sanitize_callback' => array( $this, 'sanitize_difficulty' ),
        ) );

        register_setting( 'sacig_settings_group', 'sacig_allow_player_difficulty', array(
            'type'              => 'boolean',
            'default'           => false,
            'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
        ) );

        register_setting( 'sacig_settings_group', 'sacig_button_mode', array(
            'type'              => 'integer',
            'default'           => 1,
            'sanitize_callback' => array( $this, 'sanitize_button_mode' ),
        ) );

        register_setting( 'sacig_settings_group', 'sacig_movement_trigger', array(
            'type'              => 'string',
            'default'           => 'none',
            'sanitize_callback' => array( $this, 'sanitize_movement_trigger' ),
        ) );

        register_setting( 'sacig_settings_group', 'sacig_enable_self_reset', array(
            'type'              => 'boolean',
            'default'           => true,
            'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
        ) );

        add_settings_section(
            'sacig_gameplay_section',
            __( 'Gameplay Settings', 'shortcodearcade-crypto-idle-game' ),
            array( $this, 'render_gameplay_section_description' ),
            'shortcodearcade-crypto-idle-game'
        );

        add_settings_field( 'sacig_difficulty', __( 'Difficulty Level', 'shortcodearcade-crypto-idle-game' ),
            array( $this, 'render_difficulty_field' ), 'shortcodearcade-crypto-idle-game', 'sacig_gameplay_section' );

        add_settings_field( 'sacig_allow_player_difficulty', __( 'Allow Player Difficulty', 'shortcodearcade-crypto-idle-game' ),
            array( $this, 'render_allow_player_difficulty_field' ), 'shortcodearcade-crypto-idle-game', 'sacig_gameplay_section' );

        add_settings_field( 'sacig_button_mode', __( 'Button Mode', 'shortcodearcade-crypto-idle-game' ),
            array( $this, 'render_button_mode_field' ), 'shortcodearcade-crypto-idle-game', 'sacig_gameplay_section' );

        add_settings_field( 'sacig_movement_trigger', __( 'Movement Trigger', 'shortcodearcade-crypto-idle-game' ),
            array( $this, 'render_movement_trigger_field' ), 'shortcodearcade-crypto-idle-game', 'sacig_gameplay_section' );

        add_settings_field( 'sacig_enable_self_reset', __( 'Enable Self-Reset', 'shortcodearcade-crypto-idle-game' ),
            array( $this, 'render_self_reset_field' ), 'shortcodearcade-crypto-idle-game', 'sacig_gameplay_section' );

        // --- Ad space settings ---
        register_setting( 'sacig_settings_group', 'sacig_ad_enabled', array(
            'type'              => 'boolean',
            'default'           => false,
            'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
        ) );

        register_setting( 'sacig_settings_group', 'sacig_ad_html', array(
            'type'              => 'string',
            'default'           => '',
            'sanitize_callback' => array( $this, 'sanitize_ad_html' ),
        ) );

        add_settings_section(
            'sacig_ad_section',
            __( 'Ad Space', 'shortcodearcade-crypto-idle-game' ),
            array( $this, 'render_ad_section_description' ),
            'shortcodearcade-crypto-idle-game'
        );

        add_settings_field( 'sacig_ad_enabled', __( 'Enable Ad Space', 'shortcodearcade-crypto-idle-game' ),
            array( $this, 'render_ad_enabled_field' ), 'shortcodearcade-crypto-idle-game', 'sacig_ad_section' );

        add_settings_field( 'sacig_ad_html', __( 'Ad HTML', 'shortcodearcade-crypto-idle-game' ),
            array( $this, 'render_ad_html_field' ), 'shortcodearcade-crypto-idle-game', 'sacig_ad_section',
            array( 'label_for' => 'sacig_ad_html' ) );

        // Branding settings are registered by SACIG_Branding (class-sacig-branding.php).
        // Login page settings are registered by SACIG_Login_Pages (class-sacig-login-pages.php).
        // AI storyline settings are registered by SACIG_AI_Storyline (class-sacig-ai-storyline.php).

        // --- Leaderboard display settings group ---
        register_setting('sacig_leaderboard_group', 'sacig_leaderboard_title', array(
            'type' => 'string',
            'default' => 'Leaderboard',
            'sanitize_callback' => 'sanitize_text_field'
        ));
        register_setting('sacig_leaderboard_group', 'sacig_leaderboard_show_avatars', array(
            'type' => 'boolean',
            'default' => true,
            'sanitize_callback' => array($this, 'sanitize_checkbox')
        ));
        register_setting('sacig_leaderboard_group', 'sacig_leaderboard_highlight_color', array(
            'type' => 'string',
            'default' => '#7c3aed',
            'sanitize_callback' => 'sanitize_hex_color'
        ));
    }

    /**
     * Sanitize checkbox
     */
    public function sanitize_checkbox($input) {
        return (bool) $input;
    }

    /**
     * Sanitize leaderboard limit
     */
    public function sanitize_leaderboard_limit($input) {
        $value = intval($input);
        return max(5, min(100, $value)); // Between 5 and 100
    }

    /**
     * Render section description
     */
    public function render_section_description() {
        echo '<p>' . esc_html__( 'Configure cloud saves and leaderboard features for Shortcode Arcade Crypto Idle Game.', 'shortcodearcade-crypto-idle-game' ) . '</p>';
    }

    /**
     * Render cloud saves field
     */
    public function render_cloud_saves_field() {
        $value = get_option('sacig_enable_cloud_saves', false);
        ?>
        <label>
            <input type="hidden" name="sacig_enable_cloud_saves" value="0">
            <input type="checkbox" name="sacig_enable_cloud_saves" value="1" <?php checked($value, true); ?>>
            Save game progress to WordPress user accounts
        </label>
        <p class="description">
            <strong>Requires:</strong> Users must be logged in to play. Game saves will be stored in your WordPress database.
        </p>
        <?php
    }

    /**
     * Render leaderboard field
     */
    public function render_leaderboard_field() {
        $cloud_enabled = get_option('sacig_enable_cloud_saves', false);
        $value = get_option('sacig_enable_leaderboard', false);
        $disabled = !$cloud_enabled;
        ?>
        <label>
            <input type="hidden" name="sacig_enable_leaderboard" value="0">
            <input type="checkbox" name="sacig_enable_leaderboard" value="1"
                <?php checked($value, true); ?>
                <?php disabled($disabled); ?>>
            Display leaderboard on your site
        </label>
        <p class="description">
            <?php if ($disabled): ?>
                <span class="sacig-warning"><span class="dashicons dashicons-warning"></span> Cloud Saves must be enabled first</span><br>
            <?php endif; ?>
            Use shortcode: <code>[sacig_crypto_idle_leaderboard]</code>
        </p>
        <?php
    }

    /**
     * Render leaderboard limit field
     */
    public function render_leaderboard_limit_field() {
        $value = get_option('sacig_leaderboard_limit', 10);
        ?>
        <input type="number" name="sacig_leaderboard_limit" value="<?php echo esc_attr($value); ?>"
            min="5" max="100" step="1">
        <p class="description">Number of top players to display (5-100)</p>
        <?php
    }

    /**
     * Sanitize difficulty value.
     *
     * @param string $input Raw input.
     * @return string
     */
    public function sanitize_difficulty( $input ) {
        $allowed = array( 'easy', 'medium', 'hard' );
        return in_array( $input, $allowed, true ) ? $input : 'medium';
    }

    /**
     * Sanitize button mode value (1-3).
     *
     * @param mixed $input Raw input.
     * @return int
     */
    public function sanitize_button_mode( $input ) {
        $value = intval( $input );
        return max( 1, min( 3, $value ) );
    }

    /**
     * Sanitize movement trigger value.
     *
     * @param string $input Raw input.
     * @return string
     */
    public function sanitize_movement_trigger( $input ) {
        $allowed = array( 'none', 'click', 'timer', 'both' );
        return in_array( $input, $allowed, true ) ? $input : 'none';
    }

    /**
     * Sanitize ad HTML, allowing ad-network markup (script/iframe/ins).
     *
     * @param string $input Raw HTML.
     * @return string
     */
    public function sanitize_ad_html( $input ) {
        if ( empty( $input ) ) {
            return '';
        }

        // Admins with the unfiltered_html capability may save raw ad markup
        // (e.g. AdSense inline scripts) without wp_kses stripping it. This mirrors
        // how WordPress core gates raw HTML/script in post content.
        if ( current_user_can( 'unfiltered_html' ) ) {
            return $input;
        }

        $allowed = wp_kses_allowed_html( 'post' );

        $allowed['iframe'] = array(
            'src'             => true,
            'width'           => true,
            'height'          => true,
            'frameborder'     => true,
            'scrolling'       => true,
            'allowfullscreen' => true,
            'style'           => true,
            'class'           => true,
            'id'              => true,
        );

        $allowed['script'] = array(
            'src'            => true,
            'type'           => true,
            'async'          => true,
            'defer'          => true,
            'id'             => true,
            'crossorigin'    => true,
            'data-ad-client' => true,
        );

        $allowed['ins'] = array(
            'class'                      => true,
            'style'                      => true,
            'data-ad-client'             => true,
            'data-ad-slot'               => true,
            'data-ad-format'             => true,
            'data-full-width-responsive' => true,
        );

        return wp_kses( $input, $allowed );
    }

    /**
     * Render the gameplay section description.
     */
    public function render_gameplay_section_description() {
        echo '<p>' . esc_html__( 'Configure anti-bot protection and difficulty settings.', 'shortcodearcade-crypto-idle-game' ) . '</p>';
    }

    /**
     * Render the difficulty select field.
     */
    public function render_difficulty_field() {
        $value   = get_option( 'sacig_difficulty', 'medium' );
        $allow   = (bool) get_option( 'sacig_allow_player_difficulty', false );
        $options = array(
            'easy'   => __( 'Easy (0.6x intensity)', 'shortcodearcade-crypto-idle-game' ),
            'medium' => __( 'Medium (0.8x intensity)', 'shortcodearcade-crypto-idle-game' ),
            'hard'   => __( 'Hard (1.0x intensity)', 'shortcodearcade-crypto-idle-game' ),
        );
        ?>
        <select name="sacig_difficulty">
            <?php foreach ( $options as $key => $label ) : ?>
                <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $value, $key ); ?>><?php echo esc_html( $label ); ?></option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            <?php
            echo $allow
                ? esc_html__( 'Default difficulty for new players.', 'shortcodearcade-crypto-idle-game' )
                : esc_html__( 'Applies to all players.', 'shortcodearcade-crypto-idle-game' );
            ?>
        </p>
        <?php
    }

    /**
     * Render the allow-player-difficulty checkbox.
     */
    public function render_allow_player_difficulty_field() {
        $value = (bool) get_option( 'sacig_allow_player_difficulty', false );
        $cloud = (bool) get_option( 'sacig_enable_cloud_saves', false );
        ?>
        <label>
            <input type="hidden" name="sacig_allow_player_difficulty" value="0">
            <input type="checkbox" name="sacig_allow_player_difficulty" value="1" <?php checked( $value, true ); ?>>
            <?php esc_html_e( 'Players can choose Easy, Medium, or Hard in-game. Creates separate leaderboards per difficulty.', 'shortcodearcade-crypto-idle-game' ); ?>
        </label>
        <?php if ( ! $cloud ) : ?>
            <p class="description"><span class="sacig-warning"><span class="dashicons dashicons-warning"></span> <?php esc_html_e( 'Without cloud saves, difficulty is stored locally only.', 'shortcodearcade-crypto-idle-game' ); ?></span></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render the button mode select field.
     */
    public function render_button_mode_field() {
        $value   = (int) get_option( 'sacig_button_mode', 1 );
        $options = array(
            1 => __( 'Standard (1 button)', 'shortcodearcade-crypto-idle-game' ),
            2 => __( '1 real + 1 decoy', 'shortcodearcade-crypto-idle-game' ),
            3 => __( '1 real + 2 decoys', 'shortcodearcade-crypto-idle-game' ),
        );
        ?>
        <select name="sacig_button_mode">
            <?php foreach ( $options as $key => $label ) : ?>
                <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $value, $key ); ?>><?php echo esc_html( $label ); ?></option>
            <?php endforeach; ?>
        </select>
        <p class="description"><?php esc_html_e( 'Anti-bot protection: players must find the real button among decoys.', 'shortcodearcade-crypto-idle-game' ); ?></p>
        <?php
    }

    /**
     * Render the movement trigger select field.
     */
    public function render_movement_trigger_field() {
        $value    = get_option( 'sacig_movement_trigger', 'none' );
        $disabled = ( 1 === (int) get_option( 'sacig_button_mode', 1 ) );
        $options  = array(
            'none'  => __( 'None', 'shortcodearcade-crypto-idle-game' ),
            'click' => __( 'Click', 'shortcodearcade-crypto-idle-game' ),
            'timer' => __( 'Timer', 'shortcodearcade-crypto-idle-game' ),
            'both'  => __( 'Both', 'shortcodearcade-crypto-idle-game' ),
        );
        ?>
        <select name="sacig_movement_trigger" <?php disabled( $disabled ); ?>>
            <?php foreach ( $options as $key => $label ) : ?>
                <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $value, $key ); ?>><?php echo esc_html( $label ); ?></option>
            <?php endforeach; ?>
        </select>
        <p class="description"><?php esc_html_e( 'When buttons swap positions. Requires 2+ button mode.', 'shortcodearcade-crypto-idle-game' ); ?></p>
        <?php
    }

    /**
     * Render the self-reset checkbox.
     */
    public function render_self_reset_field() {
        $value = (bool) get_option( 'sacig_enable_self_reset', true );
        ?>
        <label>
            <input type="hidden" name="sacig_enable_self_reset" value="0">
            <input type="checkbox" name="sacig_enable_self_reset" value="1" <?php checked( $value, true ); ?>>
            <?php esc_html_e( 'Allow players to reset their run while keeping prestige level and best score.', 'shortcodearcade-crypto-idle-game' ); ?>
        </label>
        <?php
    }

    /**
     * Render the ad section description.
     */
    public function render_ad_section_description() {
        echo '<p>' . esc_html__( 'Optionally display an ad unit within the game. Ad HTML is shown to all visitors.', 'shortcodearcade-crypto-idle-game' ) . '</p>';
    }

    /**
     * Render the ad-enabled checkbox.
     */
    public function render_ad_enabled_field() {
        $value = (bool) get_option( 'sacig_ad_enabled', false );
        ?>
        <label>
            <input type="hidden" name="sacig_ad_enabled" value="0">
            <input type="checkbox" name="sacig_ad_enabled" value="1" <?php checked( $value, true ); ?>>
            <?php esc_html_e( 'Show ad HTML within the game shortcode output.', 'shortcodearcade-crypto-idle-game' ); ?>
        </label>
        <?php
    }

    /**
     * Render the ad HTML textarea.
     */
    public function render_ad_html_field() {
        $value = get_option( 'sacig_ad_html', '' );
        ?>
        <textarea id="sacig_ad_html" name="sacig_ad_html" rows="6" class="large-text"><?php echo esc_textarea( $value ); ?></textarea>
        <p class="description"><?php esc_html_e( 'Paste ad network code (AdSense, etc.). Supports script, iframe, and ins tags.', 'shortcodearcade-crypto-idle-game' ); ?></p>
        <?php
    }

    /**
     * Get gameplay settings for passing to the front-end game.
     *
     * @return array
     */
    public static function get_gameplay_settings() {
        $difficulty = get_option( 'sacig_difficulty', 'medium' );
        $intensity  = isset( self::$difficulty_map[ $difficulty ] )
            ? self::$difficulty_map[ $difficulty ] : 0.8;

        return array(
            'difficulty'            => $difficulty,
            'difficultyIntensity'   => $intensity,
            'allowPlayerDifficulty' => (bool) get_option( 'sacig_allow_player_difficulty', false ),
            'buttonMode'            => (int) get_option( 'sacig_button_mode', 1 ),
            'movementTrigger'       => get_option( 'sacig_movement_trigger', 'none' ),
            'enableSelfReset'       => (bool) get_option( 'sacig_enable_self_reset', true ),
        );
    }

    /**
     * Output the arcade-themed page header.
     *
     * @param string $subtitle Page subtitle text.
     */
    private function render_arcade_header($subtitle) {
        ?>
        <div class="sacig-arcade-header">
            <div class="sacig-arcade-logo">&#x20BF;</div>
            <h1 class="sacig-arcade-title">Crypto Arcade</h1>
            <p class="sacig-arcade-subtitle"><?php echo esc_html($subtitle); ?></p>
        </div>
        <?php
    }

    /**
     * Render the Game Settings page (general settings).
     */
    public function render_game_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Check if settings were saved.
        if (isset($_GET['settings-updated'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            // Check if database table needs to be created.
            $cloud_enabled = get_option('sacig_enable_cloud_saves', false);
            if ($cloud_enabled) {
                $this->maybe_create_table();
            }

            add_settings_error(
                'sacig_messages',
                'sacig_message',
                __( 'Settings Saved', 'shortcodearcade-crypto-idle-game' ),
                'updated'
            );
        }

        settings_errors('sacig_messages');
        ?>
        <div class="wrap sacig-arcade-wrap">
            <?php $this->render_arcade_header( __( 'Cloud saves, leaderboard & core gameplay', 'shortcodearcade-crypto-idle-game' ) ); ?>

            <div class="sacig-admin-container">
                <div class="sacig-admin-main">
                    <div class="sacig-arcade-card">
                        <form action="options.php" method="post">
                            <?php
                            settings_fields('sacig_settings_group');
                            do_settings_sections('shortcodearcade-crypto-idle-game');
                            submit_button('Save Settings');
                            ?>
                        </form>
                    </div>

                    <div class="sacig-info-box">
                        <h3><span class="dashicons dashicons-shortcode"></span> Shortcodes</h3>
                        <p><strong>Game:</strong> <code>[sacig_crypto_idle_game]</code></p>
                        <?php if (get_option('sacig_enable_leaderboard')): ?>
                            <p><strong>Leaderboard:</strong> <code>[sacig_crypto_idle_leaderboard]</code></p>
                        <?php endif; ?>
                    </div>

                    <?php if ( get_option( 'sacig_enable_cloud_saves' ) ) : ?>
                    <div class="sacig-info-box">
                        <h3><span class="dashicons dashicons-cloud"></span> Cloud Saves Status</h3>
                        <?php
                        global $wpdb;

                        $table_name = esc_sql( $wpdb->prefix . 'sacig_saves' );

                        // Direct query required: aggregate COUNT on custom table; no WP core API exists for custom table statistics.
                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                        $count = (int) $wpdb->get_var(
                            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                            "SELECT COUNT(*) FROM {$table_name}"
                        );
                        ?>
                        <p><strong>Total Saved Games:</strong> <?php echo esc_html( $count ); ?></p>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="sacig-admin-sidebar">
                    <div class="sacig-sidebar-box">
                        <h3><span class="dashicons dashicons-info"></span> About</h3>
                        <p><strong>Shortcode Arcade Crypto Idle Game</strong></p>
                        <p>Version: <?php echo esc_html(SACIG_VERSION); ?></p>
                        <p>An idle clicker game with Elo-balanced progression.</p>
                    </div>

                    <div class="sacig-sidebar-box">
                        <h3><span class="dashicons dashicons-book"></span> Documentation</h3>
                        <ul>
                            <li><strong>Local Saves:</strong> Uses browser localStorage (default)</li>
                            <li><strong>Cloud Saves:</strong> Requires user login, stores in WordPress DB</li>
                            <li><strong>Leaderboard:</strong> Shows top players with prestige-weighted scoring</li>
                        </ul>
                    </div>

                    <div class="sacig-sidebar-box">
                        <h3><span class="dashicons dashicons-warning"></span> Important Notes</h3>
                        <ul>
                            <li>Uses standard WordPress user accounts for login.</li>
                            <li>Cloud saves require users to be logged in.</li>
                            <li>Leaderboards require cloud saves to be enabled.</li>
                            <li>All data is stored in your WordPress database.</li>
                            <li>Player data is private to your site only.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render the Branding settings page.
     *
     * Delegates to SACIG_Branding, which owns the branding settings.
     */
    public function render_branding_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (isset($_GET['settings-updated'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            add_settings_error('sacig_messages', 'sacig_message', __( 'Branding Saved', 'shortcodearcade-crypto-idle-game' ), 'updated');
        }
        settings_errors('sacig_messages');

        $branding = $this->branding instanceof SACIG_Branding ? $this->branding : new SACIG_Branding();
        $branding->render_settings_page();
    }

    /**
     * Render the AI Storyline settings page.
     *
     * Delegates to SACIG_AI_Storyline, which owns the AI settings.
     */
    public function render_ai_storyline_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Handle cache-flushed notice.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( isset( $_GET['cache-flushed'] ) && '1' === $_GET['cache-flushed'] ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'AI story cache cleared.', 'shortcodearcade-crypto-idle-game' ) . '</p></div>';
        }

        // Saving settings clears the cache so milestone stories regenerate with new options.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( isset( $_GET['settings-updated'] ) ) {
            SACIG_AI_Storyline::flush_cache();
        }

        $ai = $this->ai_storyline instanceof SACIG_AI_Storyline ? $this->ai_storyline : new SACIG_AI_Storyline();
        $ai->render_settings_page();
    }

    /**
     * Render the Login Pages settings page.
     *
     * Delegates to SACIG_Login_Pages, which owns the login settings.
     */
    public function render_login_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (isset($_GET['settings-updated'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            add_settings_error('sacig_messages', 'sacig_message', __( 'Login Settings Saved', 'shortcodearcade-crypto-idle-game' ), 'updated');
        }
        settings_errors('sacig_messages');

        $login = $this->login_pages instanceof SACIG_Login_Pages ? $this->login_pages : new SACIG_Login_Pages();
        $login->render_settings_page();
    }

    /**
     * Render the Leaderboard display settings page.
     */
    public function render_leaderboard_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (isset($_GET['settings-updated'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            add_settings_error('sacig_messages', 'sacig_message', __( 'Leaderboard Settings Saved', 'shortcodearcade-crypto-idle-game' ), 'updated');
        }
        settings_errors('sacig_messages');

        $title        = get_option('sacig_leaderboard_title', 'Leaderboard');
        $show_avatars = get_option('sacig_leaderboard_show_avatars', true);
        $highlight    = get_option('sacig_leaderboard_highlight_color') ?: '#7c3aed';
        $limit        = get_option('sacig_leaderboard_limit', 10);
        $enabled      = get_option('sacig_enable_leaderboard', false);
        ?>
        <div class="wrap sacig-arcade-wrap">
            <?php $this->render_arcade_header( __( 'Control how rankings are displayed', 'shortcodearcade-crypto-idle-game' ) ); ?>

            <div class="sacig-admin-container">
                <div class="sacig-admin-main">
                    <?php if (!$enabled): ?>
                    <div class="sacig-info-box">
                        <h3><span class="dashicons dashicons-warning"></span> Leaderboard Disabled</h3>
                        <p>Enable Cloud Saves and the Leaderboard on the <strong>Game Settings</strong> page to display rankings. You can still configure display options below.</p>
                    </div>
                    <?php endif; ?>

                    <div class="sacig-arcade-card">
                        <form action="options.php" method="post">
                            <?php settings_fields('sacig_leaderboard_group'); ?>
                            <table class="form-table" role="presentation">
                                <tr>
                                    <th scope="row"><label for="sacig_leaderboard_title">Leaderboard Title</label></th>
                                    <td>
                                        <input type="text" id="sacig_leaderboard_title" name="sacig_leaderboard_title" class="regular-text" value="<?php echo esc_attr($title); ?>">
                                        <p class="description">Heading shown above the leaderboard table.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">Player Avatars</th>
                                    <td>
                                        <label>
                                            <input type="hidden" name="sacig_leaderboard_show_avatars" value="0">
                                            <input type="checkbox" name="sacig_leaderboard_show_avatars" value="1" <?php checked($show_avatars, true); ?>>
                                            Show player avatars in the leaderboard
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="sacig_leaderboard_highlight_color">Highlight Color</label></th>
                                    <td>
                                        <input type="color" id="sacig_leaderboard_highlight_color" name="sacig_leaderboard_highlight_color" value="<?php echo esc_attr($highlight); ?>">
                                        <p class="description">Used to highlight the current player's row.</p>
                                    </td>
                                </tr>
                            </table>
                            <?php submit_button('Save Leaderboard Settings'); ?>
                        </form>
                    </div>

                    <div class="sacig-info-box">
                        <h3><span class="dashicons dashicons-shortcode"></span> Shortcode</h3>
                        <p><code>[sacig_crypto_idle_leaderboard]</code></p>
                        <p>Currently showing the top <strong><?php echo esc_html($limit); ?></strong> players (set the size on the Game Settings page).</p>
                    </div>
                </div>

                <div class="sacig-admin-sidebar">
                    <div class="sacig-sidebar-box">
                        <h3><span class="dashicons dashicons-awards"></span> Ranking</h3>
                        <ul>
                            <li>Players are ranked using a prestige-weighted score.</li>
                            <li>Scores update each time a player saves to the cloud.</li>
                            <li>Only logged-in players appear on the leaderboard.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render the About page.
     */
    public function render_about_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap sacig-arcade-wrap">
            <?php $this->render_arcade_header( __( 'Plugin info & shortcode reference', 'shortcodearcade-crypto-idle-game' ) ); ?>

            <div class="sacig-admin-container">
                <div class="sacig-admin-main">
                    <div class="sacig-arcade-card">
                        <h2>Shortcode Arcade Crypto Idle Game</h2>
                        <p>Version <?php echo esc_html(SACIG_VERSION); ?> &mdash; a crypto-themed idle clicker with balanced progression, prestige mechanics, cloud saves, and leaderboards. Every feature is free and fully unlocked.</p>
                    </div>

                    <div class="sacig-arcade-card">
                        <h2>Shortcode Reference</h2>
                        <table class="widefat striped">
                            <thead>
                                <tr><th>Shortcode</th><th>Description</th></tr>
                            </thead>
                            <tbody>
                                <tr><td><code>[sacig_crypto_idle_game]</code></td><td>Displays the idle clicker game.</td></tr>
                                <tr><td><code>[sacig_crypto_idle_game ad_code="..."]</code></td><td>Displays the game with custom ad code.</td></tr>
                                <tr><td><code>[sacig_crypto_idle_leaderboard]</code></td><td>Displays the player leaderboard (requires cloud saves).</td></tr>
                                <tr><td><code>[sacig_crypto_idle_login]</code></td><td>Displays a branded login form.</td></tr>
                                <tr><td><code>[sacig_crypto_idle_register]</code></td><td>Displays a branded registration form.</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="sacig-arcade-card sacig-info-card">
                        <h3>&#x1F916; WordPress 7.0 AI Client</h3>
                        <p>WordPress 7.0 introduces a native AI Client API and Connections Screen for central API key management. A future update will optionally integrate with this system so the game can use your site's centrally managed AI provider keys.</p>
                    </div>
                </div>

                <div class="sacig-admin-sidebar">
                    <div class="sacig-arcade-card sacig-info-card">
                        <h3>&#x1F4AC; Ask Adam</h3>
                        <p>Need a hand setting up your arcade? <strong>Ask Adam</strong> is the Shortcode Arcade AI assistant that answers your WordPress and plugin questions in plain language.</p>
                        <p><a href="https://shortcodearcade.com/ask-adam" target="_blank" rel="noopener noreferrer" class="button sacig-arcade-cta">Chat with Ask Adam</a></p>
                    </div>

                    <div class="sacig-sidebar-box">
                        <h3><span class="dashicons dashicons-admin-links"></span> Resources</h3>
                        <ul>
                            <li><a href="https://shortcodearcade.com" target="_blank" rel="noopener noreferrer">Shortcode Arcade</a></li>
                            <li><a href="https://github.com/jackofall1232/shortcodearcade-crypto-idle-game" target="_blank" rel="noopener noreferrer">GitHub Repository</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Maybe create database table for cloud saves
     */
    private function maybe_create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sacig_saves';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            user_id bigint(20) UNSIGNED NOT NULL,
            save_data longtext NOT NULL,
            base_click_power decimal(20,6) DEFAULT 1,
            base_passive_income decimal(20,6) DEFAULT 0,
            prestige_level int DEFAULT 0,
            total_satoshis decimal(30,6) DEFAULT 0,
            rank_score decimal(30,6) DEFAULT 0,
            best_rank_score decimal(30,6) DEFAULT 0,
            best_rank_score_easy decimal(30,6) DEFAULT 0,
            best_rank_score_medium decimal(30,6) DEFAULT 0,
            best_rank_score_hard decimal(30,6) DEFAULT 0,
            difficulty varchar(10) DEFAULT 'medium',
            last_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (user_id),
            KEY rank_score (rank_score),
            KEY best_rank_score (best_rank_score),
            KEY difficulty (difficulty),
            KEY last_updated (last_updated)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }


    /**
     * Output inline CSS to recolor the admin menu icon on our pages.
     */
    public function output_menu_color_css() {
        ?>
        <style>
        /* Remove WP grey filter so gold Bitcoin color shows in sidebar */
        #adminmenu .toplevel_page_shortcodearcade-crypto-idle-game .wp-menu-image img {
            filter: none !important;
            opacity: 1 !important;
        }

        /* Purple neon left border on active submenu items */
        #adminmenu .toplevel_page_shortcodearcade-crypto-idle-game .wp-submenu li.current a {
            color: #a855f7;
            border-left: 3px solid #a855f7;
            padding-left: 9px;
        }

        #adminmenu .toplevel_page_shortcodearcade-crypto-idle-game .wp-submenu a:hover {
            color: #e879f9;
        }
        </style>
        <?php
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our plugin's admin pages.
        if (strpos($hook, 'shortcodearcade-crypto-idle-game') === false) {
            return;
        }

        wp_enqueue_style(
            'sacig-admin-fonts',
            'https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@300;400;600&display=swap',
            array(),
            null // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Google Fonts CDN handles versioning; null omits the ?ver= query string which would otherwise break the Google Fonts URL format.
        );

        wp_enqueue_style(
            'sacig-admin-css',
            SACIG_PLUGIN_URL . 'assets/css/sacig-admin.css',
            array(),
            SACIG_VERSION
        );

        wp_enqueue_script(
            'sacig-admin-js',
            SACIG_PLUGIN_URL . 'assets/js/sacig-admin.js',
            array('jquery'),
            SACIG_VERSION,
            true
        );

        // Localize strings for JavaScript
        wp_localize_script(
            'sacig-admin-js',
            'sacigAdminStrings',
            array(
                'confirmDisableCloudTitle' => __( 'Warning: Disabling Cloud Saves', 'shortcodearcade-crypto-idle-game' ),
                'confirmDisableCloudBody' => __( 'Disabling cloud saves will prevent users from saving game progress to your WordPress database. Players will only be able to use local browser storage.', 'shortcodearcade-crypto-idle-game' ),
                'confirmDisableCloudQuestion' => __( 'Are you sure you want to disable cloud saves?', 'shortcodearcade-crypto-idle-game' ),
                'unsavedChangesWarning' => __( 'You have unsaved changes. Are you sure you want to leave?', 'shortcodearcade-crypto-idle-game' ),
                'copiedLabel' => __( 'Copied!', 'shortcodearcade-crypto-idle-game' ),
                'tooltipCloudSaves' => __( 'Saves game data to WordPress database. Requires users to be logged in.', 'shortcodearcade-crypto-idle-game' ),
                'tooltipLeaderboard' => __( 'Display top players using the [sacig_crypto_idle_leaderboard] shortcode.', 'shortcodearcade-crypto-idle-game' ),
                'selectCoinImage' => __( 'Select Coin Image', 'shortcodearcade-crypto-idle-game' ),
                'useThisImage' => __( 'Use this image', 'shortcodearcade-crypto-idle-game' ),
                'noCoinImage' => __( 'No custom coin image set', 'shortcodearcade-crypto-idle-game' ),
            )
        );
    }
}
