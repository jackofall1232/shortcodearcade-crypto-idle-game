<?php
/**
 * Admin Settings Class
 * 
 * Handles the admin settings page for Crypto Miner Tycoon
 * 
 * Version: 0.9.0 - Added player difficulty selection toggle
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class CMT_Admin {
    
    /**
     * Difficulty intensity map
     */
    private static $difficulty_map = array(
        'easy' => 0.6,
        'medium' => 0.8,
        'hard' => 1.0
    );
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('admin_post_cmt_flush_ai_cache', array($this, 'handle_flush_ai_cache'));
    }

    /**
     * Add top-level admin menu with subpages.
     * Changed in 1.0.0: Moved from Settings submenu to top-level menu.
     */
    public function add_admin_menu() {
        add_menu_page(
            'Crypto Miner Tycoon',
            'Crypto Miner',
            'manage_options',
            'crypto-miner-tycoon',
            array($this, 'render_settings_page'),
            'dashicons-chart-line',
            30
        );

        add_submenu_page(
            'crypto-miner-tycoon',
            'Game Settings — Crypto Miner Tycoon',
            'Game Settings',
            'manage_options',
            'crypto-miner-tycoon',
            array($this, 'render_settings_page')
        );

        add_submenu_page(
            'crypto-miner-tycoon',
            'Branding — Crypto Miner Tycoon',
            'Branding',
            'manage_options',
            'crypto-miner-tycoon-branding',
            array($this, 'render_branding_subpage')
        );

        add_submenu_page(
            'crypto-miner-tycoon',
            'AI Storyline — Crypto Miner Tycoon',
            'AI Storyline',
            'manage_options',
            'crypto-miner-tycoon-ai',
            array($this, 'render_ai_storyline_subpage')
        );

        add_submenu_page(
            'crypto-miner-tycoon',
            'Login Pages — Crypto Miner Tycoon',
            'Login Pages',
            'manage_options',
            'crypto-miner-tycoon-login',
            array($this, 'render_login_subpage')
        );
    }

    /**
     * Render Branding subpage (delegates to CMT_Pro_Branding).
     */
    public function render_branding_subpage() {
        if (class_exists('CMT_Pro_Branding')) {
            $branding = new CMT_Pro_Branding();
            $branding->render_settings_page();
        }
    }

    /**
     * Render Login Pages subpage (delegates to CMT_Contests_Admin).
     */
    public function render_login_subpage() {
        if (class_exists('CMT_Contests_Admin')) {
            $contests_admin = new CMT_Contests_Admin();
            $contests_admin->render_login_tab();
        }
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        // Register settings group
        register_setting('cmt_settings_group', 'cmt_enable_cloud_saves', array(
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => array($this, 'sanitize_checkbox')
        ));
        
        register_setting('cmt_settings_group', 'cmt_enable_leaderboard', array(
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => array($this, 'sanitize_checkbox')
        ));
        
        register_setting('cmt_settings_group', 'cmt_leaderboard_limit', array(
            'type' => 'integer',
            'default' => 10,
            'sanitize_callback' => array($this, 'sanitize_leaderboard_limit')
        ));
        
        // Gameplay settings (New in 0.8.0)
        register_setting('cmt_settings_group', 'cmt_difficulty', array(
            'type' => 'string',
            'default' => 'medium',
            'sanitize_callback' => array($this, 'sanitize_difficulty')
        ));
        
        // Player difficulty selection (New in 0.9.0)
        register_setting('cmt_settings_group', 'cmt_allow_player_difficulty', array(
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => array($this, 'sanitize_checkbox')
        ));
        
        register_setting('cmt_settings_group', 'cmt_button_mode', array(
            'type' => 'integer',
            'default' => 1,
            'sanitize_callback' => array($this, 'sanitize_button_mode')
        ));
        
        register_setting('cmt_settings_group', 'cmt_movement_trigger', array(
            'type' => 'string',
            'default' => 'none',
            'sanitize_callback' => array($this, 'sanitize_movement_trigger')
        ));
        
        register_setting('cmt_settings_group', 'cmt_enable_self_reset', array(
            'type' => 'boolean',
            'default' => true,
            'sanitize_callback' => array($this, 'sanitize_checkbox')
        ));
        
        // Add settings section
        add_settings_section(
            'cmt_main_section',
            'Game Settings',
            array($this, 'render_section_description'),
            'crypto-miner-tycoon'
        );
        
        // Add settings fields
        add_settings_field(
            'cmt_enable_cloud_saves',
            'Enable Cloud Saves',
            array($this, 'render_cloud_saves_field'),
            'crypto-miner-tycoon',
            'cmt_main_section'
        );
        
        add_settings_field(
            'cmt_enable_leaderboard',
            'Enable Leaderboard',
            array($this, 'render_leaderboard_field'),
            'crypto-miner-tycoon',
            'cmt_main_section'
        );
        
        add_settings_field(
            'cmt_leaderboard_limit',
            'Leaderboard Size',
            array($this, 'render_leaderboard_limit_field'),
            'crypto-miner-tycoon',
            'cmt_main_section'
        );
        
        // Gameplay settings section (New in 0.8.0)
        add_settings_section(
            'cmt_gameplay_section',
            'Gameplay Settings',
            array($this, 'render_gameplay_section_description'),
            'crypto-miner-tycoon'
        );
        
        add_settings_field(
            'cmt_difficulty',
            'Default Difficulty Level',
            array($this, 'render_difficulty_field'),
            'crypto-miner-tycoon',
            'cmt_gameplay_section'
        );
        
        // Player difficulty selection (New in 0.9.0)
        add_settings_field(
            'cmt_allow_player_difficulty',
            'Allow Player Difficulty Selection',
            array($this, 'render_allow_player_difficulty_field'),
            'crypto-miner-tycoon',
            'cmt_gameplay_section'
        );
        
        add_settings_field(
            'cmt_button_mode',
            'Button Mode',
            array($this, 'render_button_mode_field'),
            'crypto-miner-tycoon',
            'cmt_gameplay_section'
        );
        
        add_settings_field(
            'cmt_movement_trigger',
            'Movement Trigger',
            array($this, 'render_movement_trigger_field'),
            'crypto-miner-tycoon',
            'cmt_gameplay_section'
        );
        
        add_settings_field(
            'cmt_enable_self_reset',
            'Enable Self-Reset',
            array($this, 'render_self_reset_field'),
            'crypto-miner-tycoon',
            'cmt_gameplay_section'
        );

        $this->register_ai_settings();
        $this->register_ad_settings();
    }

    /**
     * Register Ad Space settings.
     */
    private function register_ad_settings() {
        register_setting( 'cmt_settings_group', 'cmt_ad_html', array(
            'type'              => 'string',
            'default'           => '',
            'sanitize_callback' => array( $this, 'sanitize_ad_html' ),
        ) );

        register_setting( 'cmt_settings_group', 'cmt_ad_enabled', array(
            'type'              => 'boolean',
            'default'           => false,
            'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
        ) );

        add_settings_section(
            'cmt_ad_section',
            'Ad Space',
            array( $this, 'render_ad_section_description' ),
            'crypto-miner-tycoon'
        );

        add_settings_field(
            'cmt_ad_enabled',
            'Ad Space',
            array( $this, 'render_ad_enabled_field' ),
            'crypto-miner-tycoon',
            'cmt_ad_section'
        );

        add_settings_field(
            'cmt_ad_html',
            'Ad HTML',
            array( $this, 'render_ad_html_field' ),
            'crypto-miner-tycoon',
            'cmt_ad_section',
            array( 'label_for' => 'cmt_ad_html' )
        );
    }

    /**
     * Render Ad Space section description.
     */
    public function render_ad_section_description() {
        echo '<p>Display an ad unit below the game. Supports AdSense, banner HTML, and iframes.</p>';
    }

    /**
     * Render the "Show ad space" checkbox.
     */
    public function render_ad_enabled_field() {
        $value = get_option( 'cmt_ad_enabled', false );
        ?>
        <label>
            <input type="checkbox" name="cmt_ad_enabled" value="1" <?php checked( $value, true ); ?>>
            Show ad space below the game
        </label>
        <p class="description">Uncheck to completely hide the ad container (no placeholder shown).</p>
        <?php
    }

    /**
     * Render the Ad HTML textarea.
     */
    public function render_ad_html_field() {
        $value = get_option( 'cmt_ad_html', '' );
        ?>
        <textarea id="cmt_ad_html"
                  name="cmt_ad_html"
                  class="large-text"
                  rows="6"
                  placeholder="Paste your ad code here (AdSense, banner HTML, iframe, etc.)"><?php echo esc_textarea( $value ); ?></textarea>
        <p class="description">
            Paste any ad code: Google AdSense snippet, banner image HTML, or an iframe.
            HTML is saved as-is and output with <code>wp_kses_post()</code>.
            Leave empty to hide the ad container even when the checkbox is enabled.
        </p>
        <?php
    }

    /**
     * Sanitize ad HTML — allows iframes and common ad network tags
     * that wp_kses_post strips by default.
     *
     * @param string $input Raw HTML input from admin form.
     * @return string Sanitized HTML.
     */
    public function sanitize_ad_html( $input ) {
        $allowed = array_merge(
            wp_kses_allowed_html( 'post' ),
            array(
                'iframe' => array(
                    'src'             => true,
                    'width'           => true,
                    'height'          => true,
                    'frameborder'     => true,
                    'scrolling'       => true,
                    'allowfullscreen' => true,
                    'style'           => true,
                    'class'           => true,
                    'id'              => true,
                    'title'           => true,
                ),
                'script' => array(
                    'src'   => true,
                    'async' => true,
                    'defer' => true,
                    'type'  => true,
                    'id'    => true,
                ),
                'ins' => array(
                    'class'                      => true,
                    'style'                      => true,
                    'data-ad-client'             => true,
                    'data-ad-slot'               => true,
                    'data-ad-format'             => true,
                    'data-full-width-responsive' => true,
                ),
            )
        );

        return wp_kses( $input, $allowed );
    }

    /**
     * Register AI Storyline settings.
     * New in 1.0.0.
     */
    private function register_ai_settings() {
        register_setting('cmt_ai_group', 'cmt_ai_storyline_enabled', array(
            'type'              => 'boolean',
            'default'           => false,
            'sanitize_callback' => array($this, 'sanitize_checkbox'),
        ));

        register_setting('cmt_ai_group', 'cmt_ai_provider', array(
            'type'              => 'string',
            'default'           => 'anthropic',
            'sanitize_callback' => array($this, 'sanitize_ai_provider'),
        ));

        register_setting('cmt_ai_group', 'cmt_ai_api_key', array(
            'type'              => 'string',
            'default'           => '',
            'sanitize_callback' => 'sanitize_text_field',
        ));

        register_setting('cmt_ai_group', 'cmt_ai_system_prompt', array(
            'type'              => 'string',
            'default'           => CMT_AI_Storyline::default_system_prompt(),
            'sanitize_callback' => 'sanitize_textarea_field',
        ));

        // Unlock media URLs — 10 upgrades + 3 prestige.
        $upgrade_ids = array(
            'betterClicker', 'cpuMiner', 'powerfulClicker', 'gpuRig', 'megaClicker',
            'asicMiner', 'ultraClicker', 'miningFarm', 'godClicker', 'datacenter',
        );
        foreach ($upgrade_ids as $uid) {
            register_setting('cmt_ai_group', 'cmt_ai_media_upgrade_' . $uid, array(
                'type'              => 'string',
                'default'           => '',
                'sanitize_callback' => 'esc_url_raw',
            ));
        }

        register_setting('cmt_ai_group', 'cmt_ai_media_prestige_general', array(
            'type'              => 'string',
            'default'           => '',
            'sanitize_callback' => 'esc_url_raw',
        ));
        register_setting('cmt_ai_group', 'cmt_ai_media_prestige_5', array(
            'type'              => 'string',
            'default'           => '',
            'sanitize_callback' => 'esc_url_raw',
        ));
        register_setting('cmt_ai_group', 'cmt_ai_media_prestige_10', array(
            'type'              => 'string',
            'default'           => '',
            'sanitize_callback' => 'esc_url_raw',
        ));
    }

    /**
     * Sanitize AI provider against the whitelist defined on CMT_AI_Storyline.
     */
    public function sanitize_ai_provider($value) {
        $allowed = array_keys(CMT_AI_Storyline::$providers);
        return in_array($value, $allowed, true) ? $value : 'anthropic';
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
        return max(5, min(100, $value));
    }
    
    /**
     * Sanitize difficulty
     */
    public function sanitize_difficulty($input) {
        $valid = array('easy', 'medium', 'hard');
        return in_array($input, $valid) ? $input : 'medium';
    }
    
    /**
     * Sanitize button mode
     */
    public function sanitize_button_mode($input) {
        $value = intval($input);
        return max(1, min(3, $value));
    }
    
    /**
     * Sanitize movement trigger
     */
    public function sanitize_movement_trigger($input) {
        $valid = array('none', 'click', 'timer', 'both');
        return in_array($input, $valid) ? $input : 'none';
    }
    
    /**
     * Render section description
     */
    public function render_section_description() {
        echo '<p>Configure cloud saves and leaderboard features.</p>';
    }
    
    /**
     * Render gameplay section description
     */
    public function render_gameplay_section_description() {
        echo '<p>Configure anti-bot protection and difficulty settings.</p>';
    }
    
    /**
     * Render cloud saves field
     */
    public function render_cloud_saves_field() {
        $value = get_option('cmt_enable_cloud_saves', false);
        ?>
        <label>
            <input type="checkbox" name="cmt_enable_cloud_saves" value="1" <?php checked($value, true); ?>>
            Save game progress to WordPress user accounts
            <span class="dashicons dashicons-info" title="Requires users to be logged in"></span>
        </label>
        <p class="description">
            <strong>Requires:</strong> Users must be logged in to play.
        </p>
        <?php
    }
    
    /**
     * Render leaderboard field
     */
    public function render_leaderboard_field() {
        $cloud_enabled = get_option('cmt_enable_cloud_saves', false);
        $value = get_option('cmt_enable_leaderboard', false);
        $disabled = !$cloud_enabled;
        ?>
        <label>
            <input type="checkbox" name="cmt_enable_leaderboard" value="1" 
                <?php checked($value, true); ?> 
                <?php disabled($disabled); ?>>
            Display leaderboard
            <span class="dashicons dashicons-info" title="Show competitive rankings"></span>
        </label>
        <p class="description">
            Use shortcode: <code>[crypto_miner_leaderboard]</code>
        </p>
        <?php
    }
    
    /**
     * Render leaderboard limit field
     */
    public function render_leaderboard_limit_field() {
        $value = get_option('cmt_leaderboard_limit', 10);
        ?>
        <input type="number" name="cmt_leaderboard_limit" value="<?php echo esc_attr($value); ?>" 
            min="5" max="100" step="1" style="width: 80px;">
        <p class="description">Number of top players to display (5-100)</p>
        <?php
    }
    
    /**
     * Render difficulty field
     * Updated in 0.9.0: Label changed to "Default" when player selection enabled
     */
    public function render_difficulty_field() {
        $value = get_option('cmt_difficulty', 'medium');
        $allow_player = get_option('cmt_allow_player_difficulty', false);
        ?>
        <select name="cmt_difficulty">
            <option value="easy" <?php selected($value, 'easy'); ?>>Easy (0.6x intensity)</option>
            <option value="medium" <?php selected($value, 'medium'); ?>>Medium (0.8x intensity)</option>
            <option value="hard" <?php selected($value, 'hard'); ?>>Hard (1.0x intensity)</option>
        </select>
        <p class="description">
            <?php if ($allow_player): ?>
                Default difficulty for new players. Existing players keep their chosen difficulty.
            <?php else: ?>
                Affects upgrade costs and chaos scaling speed. Applies to all players.
            <?php endif; ?>
        </p>
        <?php
    }
    
    /**
     * Render allow player difficulty field
     * New in 0.9.0
     */
    public function render_allow_player_difficulty_field() {
        $value = get_option('cmt_allow_player_difficulty', false);
        $cloud_enabled = get_option('cmt_enable_cloud_saves', false);
        $leaderboard_enabled = get_option('cmt_enable_leaderboard', false);
        ?>
        <label>
            <input type="checkbox" name="cmt_allow_player_difficulty" value="1" <?php checked($value, true); ?>>
            Let players choose their own difficulty level
        </label>
        <p class="description">
            Players can select Easy, Medium, or Hard from in-game UI.
            <?php if ($leaderboard_enabled): ?>
                <br><strong>&#x26A1; Creates separate leaderboards per difficulty</strong> - players only compete against others on the same difficulty.
            <?php endif; ?>
        </p>
        <?php if (!$cloud_enabled): ?>
            <p class="description cmt-warning">
                &#x26A0;&#xFE0F; Note: Without cloud saves, difficulty choice is stored locally and won't persist across devices.
            </p>
        <?php endif; ?>
        <?php
    }
    
    /**
     * Render button mode field
     */
    public function render_button_mode_field() {
        $value = get_option('cmt_button_mode', 1);
        ?>
        <select name="cmt_button_mode" id="cmt_button_mode">
            <option value="1" <?php selected($value, 1); ?>>1 Button (Standard)</option>
            <option value="2" <?php selected($value, 2); ?>>2 Buttons (1 real + 1 decoy)</option>
            <option value="3" <?php selected($value, 3); ?>>3 Buttons (1 real + 2 decoys)</option>
        </select>
        <p class="description">Anti-bot protection: Players must find the real button among decoys.</p>
        <?php
    }
    
    /**
     * Render movement trigger field
     */
    public function render_movement_trigger_field() {
        $value = get_option('cmt_movement_trigger', 'none');
        $button_mode = get_option('cmt_button_mode', 1);
        $disabled = ($button_mode == 1);
        ?>
        <select name="cmt_movement_trigger" id="cmt_movement_trigger" <?php disabled($disabled); ?>>
            <option value="none" <?php selected($value, 'none'); ?>>None</option>
            <option value="click" <?php selected($value, 'click'); ?>>On Click</option>
            <option value="timer" <?php selected($value, 'timer'); ?>>On Timer</option>
            <option value="both" <?php selected($value, 'both'); ?>>Both</option>
        </select>
        <p class="description">
            <?php if ($disabled): ?>
                <em>Requires 2+ button mode.</em>
            <?php else: ?>
                When buttons swap positions.
            <?php endif; ?>
        </p>
        <?php
    }
    
    /**
     * Render self-reset field
     */
    public function render_self_reset_field() {
        $value = get_option('cmt_enable_self_reset', true);
        ?>
        <label>
            <input type="checkbox" name="cmt_enable_self_reset" value="1" <?php checked($value, true); ?>>
            Allow players to reset their run
        </label>
        <p class="description">Players can start fresh while keeping prestige level and best leaderboard score.</p>
        <?php
    }
    
    /**
     * Get gameplay settings for frontend
     * Updated in 0.9.0: Added allowPlayerDifficulty
     */
    public static function get_gameplay_settings() {
        $difficulty = get_option('cmt_difficulty', 'medium');
        $intensity = isset(self::$difficulty_map[$difficulty]) ? self::$difficulty_map[$difficulty] : 0.8;
        
        return array(
            'difficulty' => $difficulty,
            'intensity' => $intensity,
            'buttonMode' => intval(get_option('cmt_button_mode', 1)),
            'movementTrigger' => get_option('cmt_movement_trigger', 'none'),
            'enableSelfReset' => (bool) get_option('cmt_enable_self_reset', true),
            'leaderboardLimit' => intval(get_option('cmt_leaderboard_limit', 10)),
            'allowPlayerDifficulty' => (bool) get_option('cmt_allow_player_difficulty', false)
        );
    }
    
    /**
     * Get intensity for a specific difficulty
     * New in 0.9.0: Helper for per-player difficulty
     */
    public static function get_difficulty_intensity($difficulty) {
        return isset(self::$difficulty_map[$difficulty]) ? self::$difficulty_map[$difficulty] : 0.8;
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
        
        if (isset($_GET['settings-updated'])) {
            $cloud_enabled = get_option('cmt_enable_cloud_saves', false);
            if ($cloud_enabled) {
                $this->maybe_create_table();
            }
            
            add_settings_error('cmt_messages', 'cmt_message', 'Settings Saved', 'updated');
        }
        
        settings_errors('cmt_messages');
        ?>
        <div class="wrap cmt-admin-wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?> <span class="cmt-pro-badge">PRO</span></h1>
            
            <nav class="nav-tab-wrapper cmt-tab-wrapper">
                <a href="?page=crypto-miner-tycoon&tab=general" 
                   class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-admin-generic"></span> General
                </a>
                <a href="?page=crypto-miner-tycoon&tab=branding" 
                   class="nav-tab <?php echo $active_tab === 'branding' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-art"></span> Branding <span class="cmt-tab-badge">PRO</span>
                </a>
                <a href="?page=crypto-miner-tycoon&tab=contests" 
                   class="nav-tab <?php echo $active_tab === 'contests' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-awards"></span> Contests <span class="cmt-tab-badge">PRO</span>
                </a>
                <a href="?page=crypto-miner-tycoon&tab=login" 
                   class="nav-tab <?php echo $active_tab === 'login' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-lock"></span> Login Pages <span class="cmt-tab-badge">PRO</span>
                </a>
            </nav>
            
            <div class="cmt-tab-content">
                <?php
                switch ($active_tab) {
                    case 'general':
                        $this->render_general_tab();
                        break;
                    case 'branding':
                        $this->render_branding_tab();
                        break;
                    case 'contests':
                        $this->render_contests_tab();
                        break;
                    case 'login':
                        $this->render_login_tab();
                        break;
                    default:
                        $this->render_general_tab();
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render General tab
     */
    private function render_general_tab() {
        ?>
        <div class="cmt-admin-container">
            <div class="cmt-admin-main">
                <form action="options.php" method="post">
                    <?php
                    settings_fields('cmt_settings_group');
                    do_settings_sections('crypto-miner-tycoon');
                    submit_button('Save Settings');
                    ?>
                </form>
                
                <div class="cmt-info-box">
                    <h3>&#x1F4CB; Shortcodes</h3>
                    <p><strong>Game:</strong> <code>[crypto_miner_tycoon]</code></p>
                    <?php if (get_option('cmt_enable_leaderboard')): ?>
                        <p><strong>Leaderboard:</strong> <code>[crypto_miner_leaderboard]</code></p>
                        <?php if (get_option('cmt_allow_player_difficulty')): ?>
                            <p class="description">&#x1F4A1; Leaderboard will show tabs for Easy/Medium/Hard when player difficulty is enabled.</p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                
                <?php if (get_option('cmt_enable_cloud_saves')): ?>
                    <div class="cmt-info-box">
                        <h3>&#x2601;&#xFE0F; Cloud Saves Status</h3>
                        <?php
                        global $wpdb;
                        $table_name = $wpdb->prefix . 'cmt_saves';
                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
                        
                        // Show difficulty breakdown if player difficulty enabled
                        $allow_player_diff = get_option('cmt_allow_player_difficulty', false);
                        ?>
                        <p><strong>Total Saved Games:</strong> <?php echo esc_html($count); ?></p>
                        
                        <?php if ($allow_player_diff && $count > 0): ?>
                            <?php
                            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                            $difficulty_counts = $wpdb->get_results(
                                "SELECT difficulty, COUNT(*) as count FROM {$table_name} GROUP BY difficulty ORDER BY FIELD(difficulty, 'easy', 'medium', 'hard')",
                                ARRAY_A
                            );
                            ?>
                            <p><strong>By Difficulty:</strong></p>
                            <ul style="margin-left: 20px;">
                                <?php foreach ($difficulty_counts as $row): ?>
                                    <li><?php echo esc_html(ucfirst($row['difficulty'])); ?>: <?php echo esc_html($row['count']); ?> players</li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="cmt-admin-sidebar">
                <div class="cmt-sidebar-box">
                    <h3>&#x2139;&#xFE0F; About</h3>
                    <p><strong>Crypto Miner Tycoon Pro</strong></p>
                    <p>Version: <?php echo esc_html(CMT_VERSION); ?></p>
                    <p>An idle clicker game with Elo-balanced progression.</p>
                </div>
                
                <div class="cmt-sidebar-box">
                    <h3>&#x1F3AE; Gameplay Features</h3>
                    <ul>
                        <li><strong>Multi-Button:</strong> Anti-bot protection</li>
                        <li><strong>Difficulty:</strong> Adjustable intensity</li>
                        <li><strong>Player Choice:</strong> Per-difficulty leaderboards</li>
                        <li><strong>Self-Reset:</strong> Fresh run option</li>
                        <li><strong>Miner Timeout:</strong> 48-hour idle limit</li>
                    </ul>
                </div>
                
                <div class="cmt-sidebar-box">
                    <h3>&#x1F4DA; Documentation</h3>
                    <ul>
                        <li><strong>Local Saves:</strong> Uses browser localStorage (default)</li>
                        <li><strong>Cloud Saves:</strong> Requires user login, stores in WordPress DB</li>
                        <li><strong>Leaderboard:</strong> Shows top players with prestige-weighted scoring</li>
                    </ul>
                </div>
                
                <div class="cmt-sidebar-box">
                    <h3>&#x26A0;&#xFE0F; Important Notes</h3>
                    <ul>
                        <li>Cloud saves require users to be logged in</li>
                        <li>Leaderboards require cloud saves to be enabled</li>
                        <li>All data is stored in your WordPress database</li>
                        <li>Player data is private to your site only</li>
                    </ul>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render Branding tab (Pro feature)
     */
    private function render_branding_tab() {
        ?>
        <div class="cmt-admin-container">
            <div class="cmt-admin-main">
                <form action="options.php" method="post">
                    <?php
                    settings_fields('cmt_branding_group');
                    do_settings_sections('crypto-miner-tycoon-branding');
                    submit_button('Save Branding Settings');
                    ?>
                </form>
            </div>
            
            <div class="cmt-admin-sidebar">
                <div class="cmt-sidebar-box cmt-pro-box">
                    <h3>&#x1F3A8; Custom Branding</h3>
                    <p>Make the game truly yours with custom branding.</p>
                    <p><strong>Tips:</strong></p>
                    <ul>
                        <li>Use high-contrast colors for better visibility</li>
                        <li>SVG images scale perfectly on all devices</li>
                        <li>Keep titles short for mobile displays</li>
                        <li>Test on different screen sizes</li>
                    </ul>
                </div>
                
                <div class="cmt-sidebar-box">
                    <h3>&#x1F3AF; Preview Changes</h3>
                    <p>After saving, view your game page to see the changes applied.</p>
                    <p><strong>Changes apply to:</strong></p>
                    <ul>
                        <li>Game title and subtitle</li>
                        <li>Coin/token image</li>
                        <li>Color scheme</li>
                        <li>Currency labels</li>
                        <li>Footer text</li>
                    </ul>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render Contests tab (Pro feature)
     */
    private function render_contests_tab() {
        CMT_Contests_Admin::render_contests_tab();
    }
    
    /**
     * Render Login Pages tab (Pro feature)
     */
    private function render_login_tab() {
        ?>
        <div class="cmt-admin-container">
            <div class="cmt-admin-main">
                <form action="options.php" method="post">
                    <?php
                    settings_fields('cmt_login_group');
                    do_settings_sections('crypto-miner-tycoon-login');
                    submit_button('Save Login Settings');
                    ?>
                </form>
                
                <div class="cmt-info-box">
                    <h3>&#x1F4CB; Available Shortcodes</h3>
                    <table class="widefat" style="margin-top: 10px;">
                        <thead>
                            <tr>
                                <th>Shortcode</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>[cmt_login_form]</code></td>
                                <td>Display login form</td>
                            </tr>
                            <tr>
                                <td><code>[cmt_register_form]</code></td>
                                <td>Display registration form</td>
                            </tr>
                            <tr>
                                <td><code>[cmt_forgot_password]</code></td>
                                <td>Display password reset form</td>
                            </tr>
                            <tr>
                                <td><code>[cmt_logout_link]</code></td>
                                <td>Display logout link (for logged-in users)</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <div class="cmt-info-box">
                    <h3>&#x1F680; Quick Setup Guide</h3>
                    <ol>
                        <li><strong>Create Pages:</strong> Create 3 new pages (Login, Register, Forgot Password)</li>
                        <li><strong>Add Shortcodes:</strong> Add the respective shortcode to each page</li>
                        <li><strong>Set URLs Above:</strong> Enter the page URLs in the settings above</li>
                        <li><strong>Enable Custom Login:</strong> Check the "Enable custom login system" box</li>
                        <li><strong>Test:</strong> Try logging out and accessing your new login page</li>
                    </ol>
                </div>
            </div>
            
            <div class="cmt-admin-sidebar">
                <div class="cmt-sidebar-box cmt-pro-box">
                    <h3>&#x1F3A8; Branded Login Experience</h3>
                    <p>Custom login pages create a seamless, professional experience.</p>
                    <p><strong>Features:</strong></p>
                    <ul>
                        <li>Matches game aesthetic</li>
                        <li>Custom branding applied</li>
                        <li>Mobile responsive</li>
                        <li>Smooth animations</li>
                        <li>Better user experience</li>
                    </ul>
                </div>
                
                <div class="cmt-sidebar-box">
                    <h3>&#x1F4A1; Pro Tips</h3>
                    <ul>
                        <li>Use custom URLs like /login instead of /wp-login.php</li>
                        <li>Set redirect to game page for better flow</li>
                        <li>Hide admin bar for cleaner player experience</li>
                        <li>Require TOS for legal compliance</li>
                    </ul>
                </div>
                
                <div class="cmt-sidebar-box">
                    <h3>&#x2699;&#xFE0F; WordPress Settings</h3>
                    <p>Don't forget to enable user registration in WordPress:</p>
                    <p><strong>Settings > General > Membership</strong></p>
                    <p>Check: "Anyone can register"</p>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render the AI Storyline subpage.
     * New in 1.0.0.
     */
    public function render_ai_storyline_subpage() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- settings-updated flag is set by options.php after a successful save.
        if (isset($_GET['settings-updated']) && $_GET['settings-updated']) {
            CMT_AI_Storyline::flush_cache();
            echo '<div class="notice notice-success is-dismissible"><p>AI Storyline settings saved. Story cache cleared.</p></div>';
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only flag set by handle_flush_ai_cache after nonce verification.
        if (isset($_GET['cache-flushed']) && $_GET['cache-flushed']) {
            echo '<div class="notice notice-success is-dismissible"><p>AI story cache flushed.</p></div>';
        }

        $enabled       = get_option('cmt_ai_storyline_enabled', false);
        $provider      = get_option('cmt_ai_provider', 'anthropic');
        $api_key       = get_option('cmt_ai_api_key', '');
        $system_prompt = get_option('cmt_ai_system_prompt', CMT_AI_Storyline::default_system_prompt());
        ?>
        <div class="wrap cmt-admin-wrap">
            <h1>
                <span class="dashicons dashicons-format-chat" style="font-size:28px;vertical-align:middle;margin-right:8px;"></span>
                AI Storyline
            </h1>
            <p class="description" style="font-size:14px;margin-bottom:20px;">
                When a player unlocks a new upgrade tier for the first time, or completes a Hard Fork (prestige),
                an AI-generated popup appears with a short narrative message. Messages are cached for 24 hours
                per event — everyone sees the same story for the same milestone.
            </p>

            <div class="cmt-admin-container">
                <div class="cmt-admin-main">
                    <form action="options.php" method="post">
                        <?php settings_fields('cmt_ai_group'); ?>

                        <div style="background:#fff;border:1px solid #c3c4c7;padding:20px;margin-bottom:20px;">
                            <h2>Configuration</h2>

                            <table class="form-table">
                                <tr>
                                    <th scope="row">Enable AI Storyline</th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="cmt_ai_storyline_enabled" value="1"
                                                <?php checked($enabled, true); ?>>
                                            Show AI-generated narrative popups on unlock events
                                        </label>
                                        <p class="description">
                                            Triggers on: first purchase of each upgrade tier, and each Hard Fork (prestige).
                                            <br>Does <strong>not</strong> trigger during contests.
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="cmt_ai_provider">AI Provider</label></th>
                                    <td>
                                        <select name="cmt_ai_provider" id="cmt_ai_provider">
                                            <?php foreach (CMT_AI_Storyline::$providers as $slug => $label): ?>
                                                <option value="<?php echo esc_attr($slug); ?>" <?php selected($provider, $slug); ?>>
                                                    <?php echo esc_html($label); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <p class="description">Each provider requires its own API key from the respective platform.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="cmt_ai_api_key">API Key</label></th>
                                    <td>
                                        <input type="password"
                                               id="cmt_ai_api_key"
                                               name="cmt_ai_api_key"
                                               value="<?php echo esc_attr($api_key); ?>"
                                               class="regular-text"
                                               autocomplete="new-password">
                                        <button type="button" class="button button-secondary"
                                                onclick="var f=document.getElementById('cmt_ai_api_key');f.type=f.type==='password'?'text':'password';this.textContent=f.type==='password'?'Show':'Hide';">
                                            Show
                                        </button>
                                        <p class="description">
                                            Stored securely in the database. Never exposed to players.
                                            <br>
                                            <strong>Anthropic:</strong> <a href="https://console.anthropic.com/settings/keys" target="_blank" rel="noopener">console.anthropic.com</a> &nbsp;|&nbsp;
                                            <strong>OpenAI:</strong> <a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener">platform.openai.com</a> &nbsp;|&nbsp;
                                            <strong>xAI:</strong> <a href="https://console.x.ai" target="_blank" rel="noopener">console.x.ai</a>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="cmt_ai_system_prompt">Narrative Tone (System Prompt)</label></th>
                                    <td>
                                        <textarea id="cmt_ai_system_prompt"
                                                  name="cmt_ai_system_prompt"
                                                  class="large-text"
                                                  rows="5"
                                                  maxlength="1000"><?php echo esc_textarea($system_prompt); ?></textarea>
                                        <p class="description">
                                            Sets the AI's voice and tone. Changes here clear the 24-hour message cache on save,
                                            so all players see fresh messages with the new tone.
                                            <br>
                                            <strong>Default:</strong> Gritty cyberpunk hacker narrator.
                                            <br>
                                            <strong>Examples:</strong> "You are a pirate ship captain narrating a treasure hunt." /
                                            "You are a wise wizard commenting on the player's magical progress."
                                        </p>
                                        <button type="button" class="button button-secondary" style="margin-top:8px;"
                                                onclick="document.getElementById('cmt_ai_system_prompt').value = <?php echo wp_json_encode(CMT_AI_Storyline::default_system_prompt()); ?>;">
                                            Reset to Default
                                        </button>
                                    </td>
                                </tr>
                            </table>

                            <?php submit_button('Save AI Settings'); ?>
                        </div>

                        <?php
                        $upgrade_ids = array(
                            'betterClicker', 'cpuMiner', 'powerfulClicker', 'gpuRig', 'megaClicker',
                            'asicMiner', 'ultraClicker', 'miningFarm', 'godClicker', 'datacenter',
                        );
                        $upgrade_defaults = array(
                            'Click Power',
                            'CPU Miner',
                            'GPU Rig',
                            'ASIC Miner',
                            'Mining Farm',
                            'Data Center',
                            'Quantum Computer',
                            'AI Trading Bot',
                            'Blockchain Network',
                            'Global Mining Empire',
                        );
                        ?>
                        <details open style="background:#fff;border:1px solid #c3c4c7;padding:0;margin-bottom:20px;">
                            <summary style="cursor:pointer;padding:15px 20px;font-size:1.3em;font-weight:600;background:#f6f7f7;border-bottom:1px solid #c3c4c7;">
                                Unlock Media (optional)
                            </summary>
                            <div style="padding:20px;">
                                <p class="description" style="margin-top:0;margin-bottom:15px;">
                                    Media plays before the AI message. Leave all fields empty to use AI text only.
                                    Supported: <code>.mp4</code> video, <code>.jpg</code> <code>.png</code> <code>.gif</code> <code>.webp</code> images.
                                </p>

                                <h3 style="margin-top:20px;">Upgrade Media</h3>
                                <table class="form-table">
                                    <?php foreach ($upgrade_ids as $index => $uid):
                                        $upgrade_number = $index + 1;
                                        $name_option    = 'cmt_pro_upgrade_' . $upgrade_number . '_name';
                                        $upgrade_name   = get_option($name_option, $upgrade_defaults[$index]);
                                        $media_option   = 'cmt_ai_media_upgrade_' . $uid;
                                        $media_value    = get_option($media_option, '');
                                    ?>
                                        <tr>
                                            <th scope="row">
                                                <label for="<?php echo esc_attr($media_option); ?>">
                                                    Upgrade <?php echo esc_html($upgrade_number); ?> &mdash; <?php echo esc_html($upgrade_name); ?>
                                                </label>
                                            </th>
                                            <td>
                                                <input type="url"
                                                       id="<?php echo esc_attr($media_option); ?>"
                                                       name="<?php echo esc_attr($media_option); ?>"
                                                       value="<?php echo esc_attr($media_value); ?>"
                                                       class="regular-text cmt-media-url-input"
                                                       placeholder="https://example.com/video.mp4 or image.jpg/png/gif/webp">
                                                <p class="description">MP4 video or image URL. Leave empty to skip media for this upgrade.</p>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </table>

                                <h3 style="margin-top:30px;">Prestige (Hard Fork) Media</h3>
                                <table class="form-table">
                                    <tr>
                                        <th scope="row">
                                            <label for="cmt_ai_media_prestige_general">General Hard Fork</label>
                                        </th>
                                        <td>
                                            <input type="url"
                                                   id="cmt_ai_media_prestige_general"
                                                   name="cmt_ai_media_prestige_general"
                                                   value="<?php echo esc_attr(get_option('cmt_ai_media_prestige_general', '')); ?>"
                                                   class="regular-text cmt-media-url-input"
                                                   placeholder="https://example.com/video.mp4 or image.jpg/png/gif/webp">
                                            <p class="description">Plays on every Hard Fork unless a milestone URL is set for that level.</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">
                                            <label for="cmt_ai_media_prestige_5">Hard Fork 5 (Milestone)</label>
                                        </th>
                                        <td>
                                            <input type="url"
                                                   id="cmt_ai_media_prestige_5"
                                                   name="cmt_ai_media_prestige_5"
                                                   value="<?php echo esc_attr(get_option('cmt_ai_media_prestige_5', '')); ?>"
                                                   class="regular-text cmt-media-url-input"
                                                   placeholder="https://example.com/video.mp4 or image.jpg/png/gif/webp">
                                            <p class="description">Overrides general media at exactly prestige level 5.</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">
                                            <label for="cmt_ai_media_prestige_10">Hard Fork 10 (Milestone)</label>
                                        </th>
                                        <td>
                                            <input type="url"
                                                   id="cmt_ai_media_prestige_10"
                                                   name="cmt_ai_media_prestige_10"
                                                   value="<?php echo esc_attr(get_option('cmt_ai_media_prestige_10', '')); ?>"
                                                   class="regular-text cmt-media-url-input"
                                                   placeholder="https://example.com/video.mp4 or image.jpg/png/gif/webp">
                                            <p class="description">Overrides general media at exactly prestige level 10.</p>
                                        </td>
                                    </tr>
                                </table>

                                <?php submit_button('Save Media Settings'); ?>
                            </div>
                        </details>
                    </form>

                    <div style="background:#fff;border:1px solid #c3c4c7;padding:20px;">
                        <h2>Cache</h2>
                        <p>AI messages are cached for 24 hours. Saving settings clears the cache automatically.
                           Use this button to clear it manually if needed.</p>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('cmt_flush_ai_cache', 'cmt_flush_nonce'); ?>
                            <input type="hidden" name="action" value="cmt_flush_ai_cache">
                            <button type="submit" class="button button-secondary">
                                Clear Story Cache Now
                            </button>
                        </form>
                    </div>
                </div>

                <div class="cmt-admin-sidebar">
                    <div class="cmt-sidebar-box">
                        <h3>&#x26A1; How It Works</h3>
                        <ul>
                            <li>Player unlocks an upgrade for the <strong>first time</strong> &rarr; popup fires</li>
                            <li>Player completes a <strong>Hard Fork</strong> &rarr; popup fires</li>
                            <li>Subsequent purchases of the same upgrade &rarr; <strong>no popup</strong></li>
                            <li>Active contest mode &rarr; <strong>no popup</strong></li>
                            <li>Same story shown to all players for each milestone (24h cache)</li>
                        </ul>
                    </div>
                    <div class="cmt-sidebar-box">
                        <h3>&#x1F4B0; API Cost Tips</h3>
                        <p>There are <strong>10 upgrades + prestige events</strong> = max ~11 cached messages at any time.</p>
                        <p>At typical token usage (~80 tokens/response), monthly cost is negligible at even high traffic volumes.</p>
                        <p><strong>Claude Haiku</strong> is the most cost-effective option.</p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Handle manual AI cache flush request.
     * New in 1.0.0.
     */
    public function handle_flush_ai_cache() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized.', 'crypto-miner-tycoon-pro'));
        }

        check_admin_referer('cmt_flush_ai_cache', 'cmt_flush_nonce');

        CMT_AI_Storyline::flush_cache();

        wp_safe_redirect(add_query_arg(
            array(
                'page'          => 'crypto-miner-tycoon-ai',
                'cache-flushed' => '1',
            ),
            admin_url('admin.php')
        ));
        exit;
    }

    /**
     * Maybe create database table for cloud saves
     */
    private function maybe_create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cmt_saves';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            user_id bigint(20) UNSIGNED NOT NULL,
            save_data longtext NOT NULL,
            base_click_power decimal(20,6) DEFAULT 1,
            base_passive_income decimal(20,6) DEFAULT 0,
            prestige_level int DEFAULT 0,
            total_satoshis decimal(30,6) DEFAULT 0,
            rank_score decimal(30,6) DEFAULT 0,
            best_rank_score decimal(30,6) DEFAULT 0,
            difficulty varchar(10) DEFAULT 'medium',
            last_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id),
            KEY rank_score (rank_score DESC),
            KEY best_rank_score (best_rank_score DESC),
            KEY difficulty (difficulty),
            KEY last_updated (last_updated)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Load admin assets on all Crypto Miner admin pages (top-level + subpages).
        // Top-level hook is "toplevel_page_crypto-miner-tycoon"; subpage hooks
        // follow "crypto-miner_page_<slug>".
        $is_cmt_page = (
            'toplevel_page_crypto-miner-tycoon' === $hook
            || strpos($hook, '_page_crypto-miner-tycoon') !== false
        );

        if (!$is_cmt_page) {
            return;
        }
        
        wp_enqueue_media();
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        
        wp_enqueue_style(
            'cmt-admin-css',
            CMT_PLUGIN_URL . 'assets/css/cmt-admin.css',
            array('wp-color-picker'),
            CMT_VERSION
        );
        
        wp_enqueue_script(
            'cmt-admin-js',
            CMT_PLUGIN_URL . 'assets/js/cmt-admin.js',
            array('jquery', 'wp-color-picker'),
            CMT_VERSION,
            true
        );
        
        wp_localize_script('cmt-admin-js', 'cmtAdmin', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cmt_admin_nonce')
        ));
    }
}
