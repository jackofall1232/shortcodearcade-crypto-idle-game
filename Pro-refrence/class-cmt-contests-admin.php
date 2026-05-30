<?php
/**
 * Contests Admin Class
 * 
 * Handles the contests admin interface
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class CMT_Contests_Admin {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_init', array($this, 'register_contest_settings'));
        add_action('admin_init', array($this, 'register_login_settings'));
        add_action('admin_menu', array($this, 'add_contests_submenu'));
    }

    /**
     * Register Contests as a submenu under the top-level CMT menu.
     * New in 1.0.0.
     */
    public function add_contests_submenu() {
        add_submenu_page(
            'crypto-miner-tycoon',
            'Contests — Crypto Miner Tycoon',
            'Contests',
            'manage_options',
            'crypto-miner-tycoon-contests',
            array($this, 'render_contests_page')
        );
    }

    /**
     * Render the Contests subpage wrapper.
     * New in 1.0.0 — delegates to the existing static renderer.
     */
    public function render_contests_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap cmt-admin-wrap">
            <h1>
                <span class="dashicons dashicons-awards" style="font-size:28px;vertical-align:middle;margin-right:8px;"></span>
                Contests
            </h1>
            <?php self::render_contests_tab(); ?>
        </div>
        <?php
    }

    /**
     * Render the Login Pages settings form (used by the admin subpage wrapper).
     * New in 1.0.0.
     */
    public function render_login_tab() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap cmt-admin-wrap">
            <h1>
                <span class="dashicons dashicons-lock" style="font-size:28px;vertical-align:middle;margin-right:8px;"></span>
                Login Pages
            </h1>
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
                </div>

                <div class="cmt-admin-sidebar">
                    <div class="cmt-sidebar-box cmt-pro-box">
                        <h3>&#x1F3A8; Branded Login Experience</h3>
                        <p>Custom login pages create a seamless, professional experience.</p>
                    </div>
                    <div class="cmt-sidebar-box">
                        <h3>&#x2699;&#xFE0F; WordPress Settings</h3>
                        <p>Don't forget to enable user registration in WordPress:</p>
                        <p><strong>Settings &gt; General &gt; Membership</strong></p>
                        <p>Check: "Anyone can register"</p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Register login page settings
     */
    public function register_login_settings() {
        register_setting('cmt_login_group', 'cmt_enable_custom_login', array(
            'type' => 'boolean',
            'sanitize_callback' => array($this, 'sanitize_checkbox'),
            'default' => false
        ));
        
        register_setting('cmt_login_group', 'cmt_login_page', array(
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default' => ''
        ));
        
        register_setting('cmt_login_group', 'cmt_register_page', array(
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default' => ''
        ));
        
        register_setting('cmt_login_group', 'cmt_forgot_password_page', array(
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default' => ''
        ));
        
        register_setting('cmt_login_group', 'cmt_game_page', array(
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default' => ''
        ));
        
        register_setting('cmt_login_group', 'cmt_login_redirect', array(
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default' => ''
        ));
        
        register_setting('cmt_login_group', 'cmt_logout_redirect', array(
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default' => ''
        ));
        
        register_setting('cmt_login_group', 'cmt_enable_registration', array(
            'type' => 'boolean',
            'sanitize_callback' => array($this, 'sanitize_checkbox'),
            'default' => true
        ));
        
        register_setting('cmt_login_group', 'cmt_enable_password_reset', array(
            'type' => 'boolean',
            'sanitize_callback' => array($this, 'sanitize_checkbox'),
            'default' => true
        ));
        
        register_setting('cmt_login_group', 'cmt_require_tos', array(
            'type' => 'boolean',
            'sanitize_callback' => array($this, 'sanitize_checkbox'),
            'default' => false
        ));
        
        register_setting('cmt_login_group', 'cmt_tos_page', array(
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default' => ''
        ));
        
        register_setting('cmt_login_group', 'cmt_hide_admin_bar', array(
            'type' => 'boolean',
            'sanitize_callback' => array($this, 'sanitize_checkbox'),
            'default' => false
        ));
        
        // Add settings section
        add_settings_section(
            'cmt_login_section',
            'Login Page Settings',
            array($this, 'render_login_section_description'),
            'crypto-miner-tycoon-login'
        );
        
        // Add settings fields
        add_settings_field(
            'cmt_enable_custom_login',
            'Enable Custom Login',
            array($this, 'render_enable_custom_login_field'),
            'crypto-miner-tycoon-login',
            'cmt_login_section'
        );
        
        add_settings_field(
            'cmt_login_pages',
            'Login Pages',
            array($this, 'render_login_pages_field'),
            'crypto-miner-tycoon-login',
            'cmt_login_section'
        );
        
        add_settings_field(
            'cmt_redirects',
            'Redirects',
            array($this, 'render_redirects_field'),
            'crypto-miner-tycoon-login',
            'cmt_login_section'
        );
        
        add_settings_field(
            'cmt_registration_options',
            'Registration Options',
            array($this, 'render_registration_options_field'),
            'crypto-miner-tycoon-login',
            'cmt_login_section'
        );
        
        add_settings_field(
            'cmt_other_options',
            'Other Options',
            array($this, 'render_other_options_field'),
            'crypto-miner-tycoon-login',
            'cmt_login_section'
        );
    }
    
    /**
     * Register contest settings
     */
    public function register_contest_settings() {
        register_setting('cmt_contests_group', 'cmt_enable_contests', array(
            'type' => 'boolean',
            'sanitize_callback' => array($this, 'sanitize_checkbox'),
            'default' => false
        ));
        
        register_setting('cmt_contests_group', 'cmt_contest_notification_email', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_email',
            'default' => get_option('admin_email')
        ));
        
        // Add settings section
        add_settings_section(
            'cmt_contests_section',
            'Contest Settings',
            array($this, 'render_contests_section_description'),
            'crypto-miner-tycoon-contests'
        );
        
        // Add settings fields
        add_settings_field(
            'cmt_enable_contests',
            'Enable Contests',
            array($this, 'render_enable_contests_field'),
            'crypto-miner-tycoon-contests',
            'cmt_contests_section'
        );
        
        add_settings_field(
            'cmt_contest_notification_email',
            'Notification Email',
            array($this, 'render_notification_email_field'),
            'crypto-miner-tycoon-contests',
            'cmt_contests_section'
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
    public function render_contests_section_description() {
        echo '<p>Manage timed contests and competitions for your players.</p>';
    }
    
    /**
     * Render enable contests field
     */
    public function render_enable_contests_field() {
        $value = get_option('cmt_enable_contests', false);
        $cloud_enabled = get_option('cmt_enable_cloud_saves', false);
        $disabled = !$cloud_enabled;
        ?>
        <label>
            <input type="checkbox" name="cmt_enable_contests" value="1" 
                <?php checked($value, true); ?> 
                <?php disabled($disabled); ?>>
            Enable contest system
        </label>
        <?php if ($disabled): ?>
            <p class="description">
                <span class="cmt-warning">&#x26A0;&#xFE0F; Cloud Saves must be enabled first</span>
            </p>
        <?php else: ?>
            <p class="description">
                Allow players to compete in timed contests for prizes.
            </p>
        <?php endif; ?>
        <?php
    }
    
    /**
     * Render notification email field
     */
    public function render_notification_email_field() {
        $value = get_option('cmt_contest_notification_email', get_option('admin_email'));
        ?>
        <input type="email" name="cmt_contest_notification_email" 
               value="<?php echo esc_attr($value); ?>" class="regular-text">
        <p class="description">
            Email address to receive contest notifications (winners, new contests, etc.)
        </p>
        <?php
    }
    
    /**
     * Render contests tab
     */
    public static function render_contests_tab() {
        // Check if tables exist
        if (!CMT_Contest_Database::tables_exist()) {
            self::render_setup_required();
            return;
        }
        
        // Get current view
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- View parameter is used for navigation only, not data modification
        $view = isset($_GET['view']) ? sanitize_text_field(wp_unslash($_GET['view'])) : 'list';
        
        switch ($view) {
            case 'create':
                self::render_create_contest();
                break;
            case 'view':
                self::render_view_contest();
                break;
            default:
                self::render_contests_list();
        }
    }
    
    /**
     * Render setup required message
     */
    private static function render_setup_required() {
        ?>
        <div class="cmt-admin-container">
            <div class="cmt-admin-main">
                <div class="cmt-setup-box">
                    <h2><span class="dashicons dashicons-awards" style="font-size:24px;vertical-align:middle;margin-right:6px;"></span>Contest System Setup Required</h2>
                    <p>The contest system requires database tables to be created.</p>
                    <form method="post" action="">
                        <?php wp_nonce_field('cmt_create_tables', 'cmt_tables_nonce'); ?>
                        <button type="submit" name="cmt_create_tables" class="button button-primary button-large">
                            Create Database Tables
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php
        
        // Handle table creation
        if (isset($_POST['cmt_create_tables']) && isset($_POST['cmt_tables_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['cmt_tables_nonce'])), 'cmt_create_tables')) {
            CMT_Contest_Database::create_tables();
            echo '<div class="notice notice-success"><p>Database tables created successfully! Refresh the page.</p></div>';
        }
    }
    
    /**
     * Render contests list
     */
    private static function render_contests_list() {
        $contests = CMT_Contest_Manager::get_contests();
        $stats = CMT_Contest_Database::get_table_stats();
        ?>
        <div class="cmt-admin-container">
            <div class="cmt-admin-main">
                <!-- Header with Create Button -->
                <div class="cmt-contests-header">
                    <h2><span class="dashicons dashicons-awards" style="font-size:24px;vertical-align:middle;margin-right:6px;"></span>Contest Management</h2>
                    <a href="?page=crypto-miner-tycoon&tab=contests&view=create" class="button button-primary">
                        <span class="dashicons dashicons-plus-alt"></span> Create New Contest
                    </a>
                </div>
                
                <!-- Active Contests -->
                <div class="cmt-contests-section">
                    <h3>Active Contests</h3>
                    <?php
                    $active_contests = array_filter($contests, function($c) { return $c['status'] === 'active'; });
                    if (empty($active_contests)):
                    ?>
                        <p class="cmt-empty-state">No active contests. Create one to get started!</p>
                    <?php else: ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th>Contest Name</th>
                                    <th>Type</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Participants</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($active_contests as $contest): 
                                    $participant_count = self::get_participant_count($contest['period_id']);
                                ?>
                                <tr>
                                    <td><strong><?php echo esc_html($contest['period_name']); ?></strong></td>
                                    <td><?php echo esc_html(ucfirst($contest['period_type'])); ?></td>
                                    <td><?php echo esc_html(gmdate('M j, Y', strtotime($contest['period_start']))); ?></td>
                                    <td><?php echo $contest['period_end'] ? esc_html(gmdate('M j, Y', strtotime($contest['period_end']))) : 'Ongoing'; ?></td>
                                    <td><?php echo esc_html($participant_count); ?> players</td>
                                    <td>
                                        <a href="?page=crypto-miner-tycoon&tab=contests&view=view&period_id=<?php echo esc_attr($contest['period_id']); ?>" class="button button-small">View</a>
                                        <button class="button button-small cmt-end-contest" data-period-id="<?php echo esc_attr($contest['period_id']); ?>">End Contest</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                
                <!-- Completed Contests -->
                <div class="cmt-contests-section">
                    <h3>Completed Contests</h3>
                    <?php
                    $completed_contests = array_filter($contests, function($c) { return $c['status'] === 'completed'; });
                    if (empty($completed_contests)):
                    ?>
                        <p class="cmt-empty-state">No completed contests yet.</p>
                    <?php else: ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th>Contest Name</th>
                                    <th>Completed</th>
                                    <th>Winners</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($completed_contests as $contest): 
                                    $winners = CMT_Contest_Manager::get_contest_winners($contest['period_id']);
                                ?>
                                <tr>
                                    <td><strong><?php echo esc_html($contest['period_name']); ?></strong></td>
                                    <td><?php echo esc_html(gmdate('M j, Y', strtotime($contest['completed_at']))); ?></td>
                                    <td><?php echo count($winners); ?> winners</td>
                                    <td>
                                        <a href="?page=crypto-miner-tycoon&tab=contests&view=view&period_id=<?php echo esc_attr($contest['period_id']); ?>" class="button button-small">View</a>
                                        <button class="button button-small button-link-delete cmt-delete-contest" data-period-id="<?php echo esc_attr($contest['period_id']); ?>">Delete</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="cmt-admin-sidebar">
                <!-- Stats Box -->
                <div class="cmt-sidebar-box">
                    <h3><span class="dashicons dashicons-chart-bar" style="vertical-align:middle;margin-right:4px;"></span>Statistics</h3>
                    <div class="cmt-stat">
                        <span class="cmt-stat-label">Total Contests</span>
                        <span class="cmt-stat-value"><?php echo esc_html($stats['periods']); ?></span>
                    </div>
                    <div class="cmt-stat">
                        <span class="cmt-stat-label">Total Participants</span>
                        <span class="cmt-stat-value"><?php echo esc_html($stats['scores']); ?></span>
                    </div>
                    <div class="cmt-stat">
                        <span class="cmt-stat-label">Total Winners</span>
                        <span class="cmt-stat-value"><?php echo esc_html($stats['winners']); ?></span>
                    </div>
                    <div class="cmt-stat">
                        <span class="cmt-stat-label">Achievements Awarded</span>
                        <span class="cmt-stat-value"><?php echo esc_html($stats['achievements']); ?></span>
                    </div>
                </div>
                
                <!-- Settings Box -->
                <div class="cmt-sidebar-box">
                    <h3><span class="dashicons dashicons-admin-settings" style="vertical-align:middle;margin-right:4px;"></span>Contest Settings</h3>
                    <form action="options.php" method="post">
                        <?php
                        settings_fields('cmt_contests_group');
                        do_settings_sections('crypto-miner-tycoon-contests');
                        submit_button('Save Settings', 'secondary');
                        ?>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render create contest form
     */
    private static function render_create_contest() {
        ?>
        <div class="cmt-admin-container">
            <div class="cmt-admin-main">
                <h2>Create New Contest</h2>
                
                <form id="cmt-create-contest-form" class="cmt-contest-form">
                    <input type="hidden" name="action" value="cmt_create_contest">
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="period_name">Contest Name *</label></th>
                            <td>
                                <input type="text" id="period_name" name="period_name" class="regular-text" required>
                                <p class="description">e.g., "January 2025 Championship"</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th><label for="period_type">Contest Type *</label></th>
                            <td>
                                <select id="period_type" name="period_type">
                                    <option value="custom">Custom</option>
                                    <option value="monthly">Monthly</option>
                                    <option value="weekly">Weekly</option>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <th><label for="period_start">Start Date *</label></th>
                            <td>
                                <input type="datetime-local" id="period_start" name="period_start" required>
                            </td>
                        </tr>
                        
                        <tr>
                            <th><label for="period_end">End Date</label></th>
                            <td>
                                <input type="datetime-local" id="period_end" name="period_end">
                                <p class="description">Leave empty for ongoing contest</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th><label for="prize_first">1st Place Prize</label></th>
                            <td>
                                <input type="text" id="prize_first" name="prize_first" class="large-text">
                                <p class="description">e.g., "$100 Amazon Gift Card"</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th><label for="prize_second">2nd Place Prize</label></th>
                            <td>
                                <input type="text" id="prize_second" name="prize_second" class="large-text">
                            </td>
                        </tr>
                        
                        <tr>
                            <th><label for="prize_third">3rd Place Prize</label></th>
                            <td>
                                <input type="text" id="prize_third" name="prize_third" class="large-text">
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="submit" class="button button-primary button-large">Create Contest</button>
                        <a href="?page=crypto-miner-tycoon&tab=contests" class="button button-large">Cancel</a>
                    </p>
                </form>
            </div>
            
            <div class="cmt-admin-sidebar">
                <div class="cmt-sidebar-box cmt-pro-box">
                    <h3><span class="dashicons dashicons-lightbulb" style="vertical-align:middle;margin-right:4px;"></span>Tips</h3>
                    <ul>
                        <li>Clear contest names help player engagement</li>
                        <li>Set realistic prize values</li>
                        <li>Monthly contests work well for most communities</li>
                        <li>You can end contests early if needed</li>
                    </ul>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render view contest
     */
    private static function render_view_contest() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- period_id is used for display only, not data modification
        $period_id = isset($_GET['period_id']) ? absint(wp_unslash($_GET['period_id'])) : 0;
        $contest = CMT_Contest_Manager::get_contest($period_id);
        
        if (!$contest) {
            echo '<p>Contest not found.</p>';
            return;
        }
        
        $leaderboard = CMT_Contest_Manager::get_contest_leaderboard($period_id);
        $winners = CMT_Contest_Manager::get_contest_winners($period_id);
        ?>
        <div class="cmt-admin-container">
            <div class="cmt-admin-main">
                <div class="cmt-contest-header">
                    <h2><?php echo esc_html($contest['period_name']); ?></h2>
                    <span class="cmt-contest-badge cmt-contest-<?php echo esc_attr($contest['status']); ?>">
                        <?php echo esc_html(ucfirst($contest['status'])); ?>
                    </span>
                </div>
                
                <div class="cmt-contest-info">
                    <div class="cmt-info-item">
                        <strong>Type:</strong> <?php echo esc_html(ucfirst($contest['period_type'])); ?>
                    </div>
                    <div class="cmt-info-item">
                        <strong>Started:</strong> <?php echo esc_html(gmdate('F j, Y g:i A', strtotime($contest['period_start']))); ?>
                    </div>
                    <?php if ($contest['period_end']): ?>
                    <div class="cmt-info-item">
                        <strong>Ends:</strong> <?php echo esc_html(gmdate('F j, Y g:i A', strtotime($contest['period_end']))); ?>
                    </div>
                    <?php endif; ?>
                    <div class="cmt-info-item">
                        <strong>Participants:</strong> <?php echo count($leaderboard); ?> players
                    </div>
                </div>
                
                <?php if (!empty($winners)): ?>
                <div class="cmt-winners-section">
                    <h3><span class="dashicons dashicons-awards" style="vertical-align:middle;margin-right:4px;"></span>Winners</h3>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Player</th>
                                <th>Final Score</th>
                                <th>Prize</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($winners as $winner): ?>
                            <tr>
                                <td>
                                    <?php
                                    $medals = array(1 => '&#x1F947;', 2 => '&#x1F948;', 3 => '&#x1F949;');
                                    $medal_rank = (int) $winner['winner_rank'];
                                    echo isset($medals[$medal_rank]) ? $medals[$medal_rank] : esc_html($winner['winner_rank']);
                                    ?>
                                </td>
                                <td><strong><?php echo esc_html($winner['display_name']); ?></strong></td>
                                <td><?php echo esc_html(number_format($winner['rank_score'], 0)); ?></td>
                                <td><?php echo $winner['prize_description'] ? esc_html($winner['prize_description']) : '&#x2014;'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
                
                <div class="cmt-leaderboard-section">
                    <h3><span class="dashicons dashicons-chart-bar" style="vertical-align:middle;margin-right:4px;"></span>Leaderboard</h3>
                    <?php if (empty($leaderboard)): ?>
                        <p class="cmt-empty-state">No participants yet.</p>
                    <?php else: ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>Player</th>
                                    <th>Satoshis</th>
                                    <th>Prestige</th>
                                    <th>Score</th>
                                    <th>Last Updated</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($leaderboard as $player): ?>
                                <tr>
                                    <td><?php echo esc_html($player['rank']); ?></td>
                                    <td><strong><?php echo esc_html($player['display_name']); ?></strong></td>
                                    <td><?php echo esc_html(number_format($player['total_satoshis'], 2)); ?></td>
                                    <td>Level <?php echo esc_html($player['prestige_level']); ?></td>
                                    <td><?php echo esc_html(number_format($player['rank_score'], 0)); ?></td>
                                    <td><?php echo esc_html(gmdate('M j, g:i A', strtotime($player['last_updated']))); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                
                <p>
                    <a href="?page=crypto-miner-tycoon&tab=contests" class="button">&#x2190; Back to Contests</a>
                </p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render login section description
     */
    public function render_login_section_description() {
        echo '<p>Configure custom login, registration, and authentication pages.</p>';
    }
    
    /**
     * Render enable custom login field
     */
    public function render_enable_custom_login_field() {
        $value = get_option('cmt_enable_custom_login', false);
        ?>
        <label>
            <input type="checkbox" name="cmt_enable_custom_login" value="1" <?php checked($value, true); ?>>
            Enable custom login system
        </label>
        <p class="description">
            Replace WordPress default login with branded pages using shortcodes.
        </p>
        <?php
    }
    
    /**
     * Render login pages field
     */
    public function render_login_pages_field() {
        $login_page = get_option('cmt_login_page', '');
        $register_page = get_option('cmt_register_page', '');
        $forgot_page = get_option('cmt_forgot_password_page', '');
        $game_page = get_option('cmt_game_page', '');
        ?>
        <table class="form-table" style="margin-top: 0;">
            <tr>
                <th><label for="cmt_login_page">Login Page</label></th>
                <td>
                    <input type="url" id="cmt_login_page" name="cmt_login_page" 
                           value="<?php echo esc_attr($login_page); ?>" class="regular-text">
                    <p class="description">Page with <code>[cmt_login_form]</code> shortcode</p>
                </td>
            </tr>
            <tr>
                <th><label for="cmt_register_page">Registration Page</label></th>
                <td>
                    <input type="url" id="cmt_register_page" name="cmt_register_page" 
                           value="<?php echo esc_attr($register_page); ?>" class="regular-text">
                    <p class="description">Page with <code>[cmt_register_form]</code> shortcode</p>
                </td>
            </tr>
            <tr>
                <th><label for="cmt_forgot_password_page">Forgot Password Page</label></th>
                <td>
                    <input type="url" id="cmt_forgot_password_page" name="cmt_forgot_password_page" 
                           value="<?php echo esc_attr($forgot_page); ?>" class="regular-text">
                    <p class="description">Page with <code>[cmt_forgot_password]</code> shortcode</p>
                </td>
            </tr>
            <tr>
                <th><label for="cmt_game_page">Game Page</label></th>
                <td>
                    <input type="url" id="cmt_game_page" name="cmt_game_page" 
                           value="<?php echo esc_attr($game_page); ?>" class="regular-text">
                    <p class="description">Page with <code>[crypto_miner_tycoon]</code> shortcode</p>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render redirects field
     */
    public function render_redirects_field() {
        $login_redirect = get_option('cmt_login_redirect', '');
        $logout_redirect = get_option('cmt_logout_redirect', '');
        ?>
        <table class="form-table" style="margin-top: 0;">
            <tr>
                <th><label for="cmt_login_redirect">After Login Redirect</label></th>
                <td>
                    <input type="url" id="cmt_login_redirect" name="cmt_login_redirect" 
                           value="<?php echo esc_attr($login_redirect); ?>" class="regular-text">
                    <p class="description">Where to redirect users after successful login (leave empty for default)</p>
                </td>
            </tr>
            <tr>
                <th><label for="cmt_logout_redirect">After Logout Redirect</label></th>
                <td>
                    <input type="url" id="cmt_logout_redirect" name="cmt_logout_redirect" 
                           value="<?php echo esc_attr($logout_redirect); ?>" class="regular-text">
                    <p class="description">Where to redirect users after logout (leave empty for homepage)</p>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render registration options field
     */
    public function render_registration_options_field() {
        $enable_reg = get_option('cmt_enable_registration', true);
        $enable_reset = get_option('cmt_enable_password_reset', true);
        $require_tos = get_option('cmt_require_tos', false);
        $tos_page = get_option('cmt_tos_page', '');
        ?>
        <label>
            <input type="checkbox" name="cmt_enable_registration" value="1" <?php checked($enable_reg, true); ?>>
            Show registration link on login page
        </label>
        <br><br>
        
        <label>
            <input type="checkbox" name="cmt_enable_password_reset" value="1" <?php checked($enable_reset, true); ?>>
            Enable "Forgot Password" link
        </label>
        <br><br>
        
        <label>
            <input type="checkbox" name="cmt_require_tos" value="1" <?php checked($require_tos, true); ?>>
            Require Terms of Service acceptance
        </label>
        <br>
        <?php if ($require_tos): ?>
            <div style="margin-left: 25px; margin-top: 10px;">
                <label for="cmt_tos_page">Terms of Service Page:</label><br>
                <input type="url" id="cmt_tos_page" name="cmt_tos_page" 
                       value="<?php echo esc_attr($tos_page); ?>" class="regular-text">
            </div>
        <?php endif; ?>
        <?php
    }
    
    /**
     * Render other options field
     */
    public function render_other_options_field() {
        $hide_admin_bar = get_option('cmt_hide_admin_bar', false);
        ?>
        <label>
            <input type="checkbox" name="cmt_hide_admin_bar" value="1" <?php checked($hide_admin_bar, true); ?>>
            Hide admin bar for non-administrators
        </label>
        <p class="description">
            Cleaner experience for regular players
        </p>
        <?php
    }
    
    /**
     * Get participant count for a contest
     */
    private static function get_participant_count($period_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cmt_period_scores';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safely constructed with $wpdb->prefix
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE period_id = %d",
            $period_id
        ));
    }
}
