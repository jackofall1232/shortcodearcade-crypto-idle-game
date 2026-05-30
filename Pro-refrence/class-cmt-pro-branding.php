<?php
/**
 * Pro Branding Class
 * 
 * Handles custom branding features for Crypto Miner Tycoon Pro
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class CMT_Pro_Branding {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_init', array($this, 'register_branding_settings'));
    }

    /**
     * Render the Branding settings subpage.
     * New in 1.0.0 — called by the top-level admin menu wrapper.
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap cmt-admin-wrap">
            <h1>
                <span class="dashicons dashicons-art" style="font-size:28px;vertical-align:middle;margin-right:8px;"></span>
                Branding <span class="cmt-pro-badge">PRO</span>
            </h1>
            <div class="cmt-admin-container">
                <div class="cmt-admin-main">
                    <form action="options.php" method="post">
                        <?php
                        settings_fields('cmt_branding_group');
                        do_settings_sections('crypto-miner-tycoon-branding');
                        $this->render_ui_labels_section();
                        submit_button('Save Branding Settings');
                        ?>
                    </form>
                </div>
                <div class="cmt-admin-sidebar">
                    <div class="cmt-sidebar-box cmt-pro-box">
                        <h3>&#x1F3A8; Custom Branding</h3>
                        <p>Make the game truly yours with custom branding.</p>
                        <ul>
                            <li>Use high-contrast colors for better visibility</li>
                            <li>SVG images scale perfectly on all devices</li>
                            <li>Keep titles short for mobile displays</li>
                        </ul>
                    </div>
                    <div class="cmt-sidebar-box">
                        <h3>&#x1F3AF; Preview Changes</h3>
                        <p>After saving, view your game page to see the changes applied.</p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Register branding settings
     */
    public function register_branding_settings() {
        // Register settings group
        register_setting('cmt_branding_group', 'cmt_pro_custom_coin', array(
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default' => ''
        ));
        
        register_setting('cmt_branding_group', 'cmt_pro_color_primary', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_hex_color',
            'default' => '#00ffff'
        ));
        
        register_setting('cmt_branding_group', 'cmt_pro_color_secondary', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_hex_color',
            'default' => '#ff00ff'
        ));
        
        register_setting('cmt_branding_group', 'cmt_pro_color_accent', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_hex_color',
            'default' => '#ffff00'
        ));
        
        register_setting('cmt_branding_group', 'cmt_pro_game_title', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'Crypto Miner Tycoon'
        ));
        
        register_setting('cmt_branding_group', 'cmt_pro_game_subtitle', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'Click. Mine. Prosper.'
        ));
        
        register_setting('cmt_branding_group', 'cmt_pro_currency_name', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'Satoshis'
        ));
        
        register_setting('cmt_branding_group', 'cmt_pro_currency_label', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'Satoshis'
        ));
        
        register_setting('cmt_branding_group', 'cmt_pro_footer_text', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ));
        
        register_setting('cmt_branding_group', 'cmt_pro_enable_branding', array(
            'type' => 'boolean',
            'sanitize_callback' => array($this, 'sanitize_checkbox'),
            'default' => false
        ));
        
        // Register 10 upgrade name settings
        register_setting('cmt_branding_group', 'cmt_pro_upgrade_1_name', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'Click Power'
        ));
        
        register_setting('cmt_branding_group', 'cmt_pro_upgrade_2_name', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'CPU Miner'
        ));
        
        register_setting('cmt_branding_group', 'cmt_pro_upgrade_3_name', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'GPU Rig'
        ));
        
        register_setting('cmt_branding_group', 'cmt_pro_upgrade_4_name', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'ASIC Miner'
        ));
        
        register_setting('cmt_branding_group', 'cmt_pro_upgrade_5_name', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'Mining Farm'
        ));
        
        register_setting('cmt_branding_group', 'cmt_pro_upgrade_6_name', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'Data Center'
        ));
        
        register_setting('cmt_branding_group', 'cmt_pro_upgrade_7_name', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'Quantum Computer'
        ));
        
        register_setting('cmt_branding_group', 'cmt_pro_upgrade_8_name', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'AI Trading Bot'
        ));
        
        register_setting('cmt_branding_group', 'cmt_pro_upgrade_9_name', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'Blockchain Network'
        ));
        
        register_setting('cmt_branding_group', 'cmt_pro_upgrade_10_name', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'Global Mining Empire'
        ));
        
        // Register UI label overrides
        $label_keys = array(
            'per_click'             => 'Per Click',
            'per_second'            => 'Per Second',
            'miner_rating'          => 'Miner Rating',
            'difficulty'            => 'DIFFICULTY',
            'miners_active'         => 'Miners Active',
            'miners_stopped'        => 'Miners Stopped',
            'miners_restart'        => 'Restart',
            'find_coin_prompt'      => 'Find the brighter coin to mine!',
            'hard_fork'             => 'Hard Fork',
            'hard_fork_description' => 'Reset with permanent +10% bonus to all production',
            'chaos_level'           => 'Chaos Level',
            'difficulty_label'      => 'Difficulty',
            'difficulty_easy'       => 'Easy',
            'difficulty_medium'     => 'Medium',
            'difficulty_hard'       => 'Hard',
        );

        foreach ( $label_keys as $key => $default ) {
            register_setting(
                'cmt_branding_group',
                'cmt_label_' . $key,
                array(
                    'type'              => 'string',
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                )
            );
        }

        // Add settings section
        add_settings_section(
            'cmt_branding_section',
            'Branding Settings',
            array($this, 'render_branding_section_description'),
            'crypto-miner-tycoon-branding'
        );
        
        // Add settings fields
        add_settings_field(
            'cmt_pro_enable_branding',
            'Enable Custom Branding',
            array($this, 'render_enable_branding_field'),
            'crypto-miner-tycoon-branding',
            'cmt_branding_section'
        );
        
        add_settings_field(
            'cmt_pro_custom_coin',
            'Custom Coin Image',
            array($this, 'render_custom_coin_field'),
            'crypto-miner-tycoon-branding',
            'cmt_branding_section'
        );
        
        add_settings_field(
            'cmt_pro_colors',
            'Color Theme',
            array($this, 'render_color_fields'),
            'crypto-miner-tycoon-branding',
            'cmt_branding_section'
        );
        
        add_settings_field(
            'cmt_pro_game_title',
            'Game Title',
            array($this, 'render_game_title_field'),
            'crypto-miner-tycoon-branding',
            'cmt_branding_section'
        );
        
        add_settings_field(
            'cmt_pro_game_subtitle',
            'Game Subtitle',
            array($this, 'render_game_subtitle_field'),
            'crypto-miner-tycoon-branding',
            'cmt_branding_section'
        );
        
        add_settings_field(
            'cmt_pro_currency',
            'Currency Name',
            array($this, 'render_currency_field'),
            'crypto-miner-tycoon-branding',
            'cmt_branding_section'
        );
        
        add_settings_field(
            'cmt_pro_upgrade_names',
            'Upgrade Names',
            array($this, 'render_upgrade_names_field'),
            'crypto-miner-tycoon-branding',
            'cmt_branding_section'
        );
        
        add_settings_field(
            'cmt_pro_footer_text',
            'Footer Text',
            array($this, 'render_footer_field'),
            'crypto-miner-tycoon-branding',
            'cmt_branding_section'
        );
    }
    
    /**
     * Sanitize checkbox
     */
    public function sanitize_checkbox($input) {
        return (bool) $input;
    }
    
    /**
     * Render section description
     */
    public function render_branding_section_description() {
        echo '<p>Customize the appearance and branding of your game to match your brand identity.</p>';
    }
    
    /**
     * Render enable branding field
     */
    public function render_enable_branding_field() {
        $value = get_option('cmt_pro_enable_branding', false);
        ?>
        <label>
            <input type="checkbox" name="cmt_pro_enable_branding" value="1" <?php checked($value, true); ?>>
            Enable custom branding for the game
        </label>
        <p class="description">
            When enabled, your custom branding settings will replace the default theme.
        </p>
        <?php
    }
    
    /**
     * Render custom coin field
     */
    public function render_custom_coin_field() {
        $coin_url = get_option('cmt_pro_custom_coin', '');
        ?>
        <div class="cmt-coin-upload-wrapper">
            <input type="hidden" id="cmt_pro_custom_coin" name="cmt_pro_custom_coin" value="<?php echo esc_attr($coin_url); ?>">
            
            <div id="cmt-coin-preview" class="cmt-coin-preview">
                <?php if ($coin_url): ?>
                    <img src="<?php echo esc_url($coin_url); ?>" alt="Custom Coin">
                    <button type="button" class="button cmt-remove-coin">Remove Image</button>
                <?php else: ?>
                    <div class="cmt-coin-placeholder">
                        <span class="dashicons dashicons-format-image"></span>
                        <p>No custom coin image set</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <button type="button" class="button button-secondary" id="cmt-upload-coin">
                <?php echo $coin_url ? 'Change Image' : 'Upload Image'; ?>
            </button>
            
            <p class="description">
                Upload a custom coin/token image (SVG, PNG, or JPG). Recommended size: 512x512px.
                <br>Leave empty to use the default Bitcoin symbol.
            </p>
        </div>
        <?php
    }
    
    /**
     * Render color fields
     */
    public function render_color_fields() {
        $primary = get_option('cmt_pro_color_primary', '#00ffff');
        $secondary = get_option('cmt_pro_color_secondary', '#ff00ff');
        $accent = get_option('cmt_pro_color_accent', '#ffff00');
        ?>
        <div class="cmt-color-picker-group">
            <div class="cmt-color-picker-item">
                <label for="cmt_pro_color_primary">
                    <strong>Primary Color</strong>
                    <span class="description">(Cyan/Neon highlights)</span>
                </label>
                <input type="text" id="cmt_pro_color_primary" name="cmt_pro_color_primary" 
                       value="<?php echo esc_attr($primary); ?>" class="cmt-color-picker">
            </div>
            
            <div class="cmt-color-picker-item">
                <label for="cmt_pro_color_secondary">
                    <strong>Secondary Color</strong>
                    <span class="description">(Magenta/Pink accents)</span>
                </label>
                <input type="text" id="cmt_pro_color_secondary" name="cmt_pro_color_secondary" 
                       value="<?php echo esc_attr($secondary); ?>" class="cmt-color-picker">
            </div>
            
            <div class="cmt-color-picker-item">
                <label for="cmt_pro_color_accent">
                    <strong>Accent Color</strong>
                    <span class="description">(Yellow/Gold values)</span>
                </label>
                <input type="text" id="cmt_pro_color_accent" name="cmt_pro_color_accent" 
                       value="<?php echo esc_attr($accent); ?>" class="cmt-color-picker">
            </div>
        </div>
        
        <p class="description">
            These colors will be applied to the game's UI, buttons, and effects.
        </p>
        <?php
    }
    
    /**
     * Render game title field
     */
    public function render_game_title_field() {
        $value = get_option('cmt_pro_game_title', 'Crypto Miner Tycoon');
        ?>
        <input type="text" id="cmt_pro_game_title" name="cmt_pro_game_title" 
               value="<?php echo esc_attr($value); ?>" class="regular-text" maxlength="50">
        <p class="description">
            The main title displayed at the top of the game. Max 50 characters.
        </p>
        <?php
    }
    
    /**
     * Render game subtitle field
     */
    public function render_game_subtitle_field() {
        $value = get_option('cmt_pro_game_subtitle', 'Click. Mine. Prosper.');
        ?>
        <input type="text" id="cmt_pro_game_subtitle" name="cmt_pro_game_subtitle" 
               value="<?php echo esc_attr($value); ?>" class="regular-text" maxlength="100">
        <p class="description">
            The subtitle/tagline displayed below the title. Max 100 characters.
        </p>
        <?php
    }
    
    /**
     * Render currency field
     */
    public function render_currency_field() {
        $currency_name = get_option('cmt_pro_currency_name', 'Satoshis');
        $currency_label = get_option('cmt_pro_currency_label', 'Satoshis');
        ?>
        <div class="cmt-currency-fields">
            <div style="margin-bottom: 15px;">
                <label for="cmt_pro_currency_name">
                    <strong>Currency Name (Singular)</strong>
                </label>
                <input type="text" id="cmt_pro_currency_name" name="cmt_pro_currency_name" 
                       value="<?php echo esc_attr($currency_name); ?>" class="regular-text" maxlength="30">
                <p class="description">e.g., "Satoshi", "Token", "Coin"</p>
            </div>
            
            <div>
                <label for="cmt_pro_currency_label">
                    <strong>Currency Label (Display)</strong>
                </label>
                <input type="text" id="cmt_pro_currency_label" name="cmt_pro_currency_label" 
                       value="<?php echo esc_attr($currency_label); ?>" class="regular-text" maxlength="30">
                <p class="description">What appears in the stats panel. e.g., "Satoshis", "Tokens", "Credits"</p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render upgrade names field
     */
    public function render_upgrade_names_field() {
        $defaults = array(
            'Click Power',
            'CPU Miner',
            'GPU Rig',
            'ASIC Miner',
            'Mining Farm',
            'Data Center',
            'Quantum Computer',
            'AI Trading Bot',
            'Blockchain Network',
            'Global Mining Empire'
        );
        ?>
        <div class="cmt-upgrade-names-grid">
            <?php for ($i = 1; $i <= 10; $i++): 
                $value = get_option("cmt_pro_upgrade_{$i}_name", $defaults[$i - 1]);
            ?>
            <div class="cmt-upgrade-name-item">
                <label for="cmt_pro_upgrade_<?php echo esc_attr($i); ?>_name">
                    <strong>Upgrade <?php echo esc_html($i); ?></strong>
                </label>
                <input type="text" 
                       id="cmt_pro_upgrade_<?php echo esc_attr($i); ?>_name" 
                       name="cmt_pro_upgrade_<?php echo esc_attr($i); ?>_name" 
                       value="<?php echo esc_attr($value); ?>" 
                       class="regular-text"
                       placeholder="<?php echo esc_attr($defaults[$i - 1]); ?>"
                       maxlength="50">
            </div>
            <?php endfor; ?>
        </div>
        <p class="description">
            Customize the names of all 10 upgrade tiers. Leave empty to use defaults. Max 50 characters each.
        </p>
        <style>
            .cmt-upgrade-names-grid {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
                margin-top: 10px;
            }
            .cmt-upgrade-name-item {
                display: flex;
                flex-direction: column;
            }
            .cmt-upgrade-name-item label {
                margin-bottom: 5px;
            }
        </style>
        <?php
    }
    
    /**
     * Render footer field
     */
    public function render_footer_field() {
        $value = get_option('cmt_pro_footer_text', '');
        ?>
        <input type="text" id="cmt_pro_footer_text" name="cmt_pro_footer_text" 
               value="<?php echo esc_attr($value); ?>" class="large-text" maxlength="200">
        <p class="description">
            Custom text for the game footer. Leave empty to use default. Max 200 characters.
            <br>Available variables: <code>{year}</code> (current year), <code>{title}</code> (game title)
        </p>
        <?php
    }
    
    /**
     * Render the UI Labels section inside the branding settings form.
     * Fields are grouped by functional area and all save to the cmt_branding_group.
     */
    private function render_ui_labels_section() {
        ?>
        <h2>UI Labels</h2>
        <p class="description" style="margin-bottom:16px;">
            Rename any player-facing label to match your game&#8217;s theme.
            Leave blank to use the default text shown in each placeholder.
        </p>
        <table class="form-table">

            <!-- Group 1: Stat Panel -->
            <tr>
                <th scope="row"><label for="cmt_label_per_click">Per Click Label</label></th>
                <td>
                    <input type="text"
                           id="cmt_label_per_click"
                           name="cmt_label_per_click"
                           value="<?php echo esc_attr( get_option( 'cmt_label_per_click', '' ) ); ?>"
                           class="regular-text"
                           placeholder="Per Click">
                    <p class="description">Default: <code>Per Click</code></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="cmt_label_per_second">Per Second Label</label></th>
                <td>
                    <input type="text"
                           id="cmt_label_per_second"
                           name="cmt_label_per_second"
                           value="<?php echo esc_attr( get_option( 'cmt_label_per_second', '' ) ); ?>"
                           class="regular-text"
                           placeholder="Per Second">
                    <p class="description">Default: <code>Per Second</code></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="cmt_label_miner_rating">Miner Rating Label</label></th>
                <td>
                    <input type="text"
                           id="cmt_label_miner_rating"
                           name="cmt_label_miner_rating"
                           value="<?php echo esc_attr( get_option( 'cmt_label_miner_rating', '' ) ); ?>"
                           class="regular-text"
                           placeholder="Miner Rating">
                    <p class="description">Default: <code>Miner Rating</code></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="cmt_label_miners_active">Miners Active Label</label></th>
                <td>
                    <input type="text"
                           id="cmt_label_miners_active"
                           name="cmt_label_miners_active"
                           value="<?php echo esc_attr( get_option( 'cmt_label_miners_active', '' ) ); ?>"
                           class="regular-text"
                           placeholder="Miners Active">
                    <p class="description">Default: <code>Miners Active</code></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="cmt_label_miners_stopped">Miners Stopped Label</label></th>
                <td>
                    <input type="text"
                           id="cmt_label_miners_stopped"
                           name="cmt_label_miners_stopped"
                           value="<?php echo esc_attr( get_option( 'cmt_label_miners_stopped', '' ) ); ?>"
                           class="regular-text"
                           placeholder="Miners Stopped">
                    <p class="description">Default: <code>Miners Stopped</code></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="cmt_label_miners_restart">Miners Restart Button Label</label></th>
                <td>
                    <input type="text"
                           id="cmt_label_miners_restart"
                           name="cmt_label_miners_restart"
                           value="<?php echo esc_attr( get_option( 'cmt_label_miners_restart', '' ) ); ?>"
                           class="regular-text"
                           placeholder="Restart">
                    <p class="description">Default: <code>Restart</code></p>
                </td>
            </tr>

            <!-- Group 2: Difficulty -->
            <tr>
                <th scope="row"><label for="cmt_label_difficulty">Difficulty Header Label</label></th>
                <td>
                    <input type="text"
                           id="cmt_label_difficulty"
                           name="cmt_label_difficulty"
                           value="<?php echo esc_attr( get_option( 'cmt_label_difficulty', '' ) ); ?>"
                           class="regular-text"
                           placeholder="DIFFICULTY">
                    <p class="description">Default: <code>DIFFICULTY</code></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="cmt_label_difficulty_easy">Easy Tier Name</label></th>
                <td>
                    <input type="text"
                           id="cmt_label_difficulty_easy"
                           name="cmt_label_difficulty_easy"
                           value="<?php echo esc_attr( get_option( 'cmt_label_difficulty_easy', '' ) ); ?>"
                           class="regular-text"
                           placeholder="Easy">
                    <p class="description">Default: <code>Easy</code></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="cmt_label_difficulty_medium">Medium Tier Name</label></th>
                <td>
                    <input type="text"
                           id="cmt_label_difficulty_medium"
                           name="cmt_label_difficulty_medium"
                           value="<?php echo esc_attr( get_option( 'cmt_label_difficulty_medium', '' ) ); ?>"
                           class="regular-text"
                           placeholder="Medium">
                    <p class="description">Default: <code>Medium</code></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="cmt_label_difficulty_hard">Hard Tier Name</label></th>
                <td>
                    <input type="text"
                           id="cmt_label_difficulty_hard"
                           name="cmt_label_difficulty_hard"
                           value="<?php echo esc_attr( get_option( 'cmt_label_difficulty_hard', '' ) ); ?>"
                           class="regular-text"
                           placeholder="Hard">
                    <p class="description">Default: <code>Hard</code></p>
                </td>
            </tr>

            <!-- Group 3: Prestige / Hard Fork -->
            <tr>
                <th scope="row"><label for="cmt_label_hard_fork">Hard Fork Name</label></th>
                <td>
                    <input type="text"
                           id="cmt_label_hard_fork"
                           name="cmt_label_hard_fork"
                           value="<?php echo esc_attr( get_option( 'cmt_label_hard_fork', '' ) ); ?>"
                           class="regular-text"
                           placeholder="Hard Fork">
                    <p class="description">Default: <code>Hard Fork</code></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="cmt_label_hard_fork_description">Hard Fork Description</label></th>
                <td>
                    <input type="text"
                           id="cmt_label_hard_fork_description"
                           name="cmt_label_hard_fork_description"
                           value="<?php echo esc_attr( get_option( 'cmt_label_hard_fork_description', '' ) ); ?>"
                           class="regular-text"
                           placeholder="Reset with permanent +10% bonus to all production">
                    <p class="description">Default: <code>Reset with permanent +10% bonus to all production</code></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="cmt_label_chaos_level">Chaos Level Label</label></th>
                <td>
                    <input type="text"
                           id="cmt_label_chaos_level"
                           name="cmt_label_chaos_level"
                           value="<?php echo esc_attr( get_option( 'cmt_label_chaos_level', '' ) ); ?>"
                           class="regular-text"
                           placeholder="Chaos Level">
                    <p class="description">Default: <code>Chaos Level</code></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="cmt_label_difficulty_label">Difficulty Prefix Label</label></th>
                <td>
                    <input type="text"
                           id="cmt_label_difficulty_label"
                           name="cmt_label_difficulty_label"
                           value="<?php echo esc_attr( get_option( 'cmt_label_difficulty_label', '' ) ); ?>"
                           class="regular-text"
                           placeholder="Difficulty">
                    <p class="description">Default: <code>Difficulty</code> &mdash; the &#8220;Difficulty:&#8221; prefix in the prestige info line.</p>
                </td>
            </tr>

            <!-- Group 4: Multi-coin Mode -->
            <tr>
                <th scope="row"><label for="cmt_label_find_coin_prompt">Find Coin Prompt</label></th>
                <td>
                    <input type="text"
                           id="cmt_label_find_coin_prompt"
                           name="cmt_label_find_coin_prompt"
                           value="<?php echo esc_attr( get_option( 'cmt_label_find_coin_prompt', '' ) ); ?>"
                           class="regular-text"
                           placeholder="Find the brighter coin to mine!">
                    <p class="description">Default: <code>Find the brighter coin to mine!</code> &mdash; Shown in the prompt bar when multiple coins are active.</p>
                </td>
            </tr>

        </table>
        <?php
    }

    /**
     * Get branding settings for frontend
     */
    public static function get_branding_settings() {
        $enabled = get_option('cmt_pro_enable_branding', false);
        
        if (!$enabled) {
            return array(
                'enabled' => false
            );
        }
        
        // Default upgrade names
        $defaults = array(
            'Click Power',
            'CPU Miner',
            'GPU Rig',
            'ASIC Miner',
            'Mining Farm',
            'Data Center',
            'Quantum Computer',
            'AI Trading Bot',
            'Blockchain Network',
            'Global Mining Empire'
        );
        
        // Get custom upgrade names
        $upgradeNames = array();
        for ($i = 1; $i <= 10; $i++) {
            $upgradeNames[] = get_option("cmt_pro_upgrade_{$i}_name", $defaults[$i - 1]);
        }
        
        return array(
            'enabled' => true,
            'customCoin' => get_option('cmt_pro_custom_coin', ''),
            'colors' => array(
                'primary' => get_option('cmt_pro_color_primary', '#00ffff'),
                'secondary' => get_option('cmt_pro_color_secondary', '#ff00ff'),
                'accent' => get_option('cmt_pro_color_accent', '#ffff00')
            ),
            'gameTitle' => get_option('cmt_pro_game_title', 'Crypto Miner Tycoon'),
            'gameSubtitle' => get_option('cmt_pro_game_subtitle', 'Click. Mine. Prosper.'),
            'currencyName' => get_option('cmt_pro_currency_name', 'Satoshis'),
            'currencyLabel' => get_option('cmt_pro_currency_label', 'Satoshis'),
            'footerText' => get_option('cmt_pro_footer_text', ''),
            'upgradeNames' => $upgradeNames
        );
    }
}
