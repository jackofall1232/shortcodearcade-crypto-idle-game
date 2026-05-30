<?php
/**
 * Plugin Name: Crypto Miner Tycoon Pro
 * Plugin URI: https://ShortcodeArcade.com
 * Description: An engaging crypto-themed idle clicker game with Elo-balanced progression. Use shortcode [crypto_miner_tycoon] to display the game.
 * Version: 1.0.0
 * Author: Shortcode Arcade 
 * Author URI: https://shortcodearcade.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: crypto-miner-tycoon-pro
 * Domain Path: /languages
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('CMT_VERSION', '1.0.0');
define('CMT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CMT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CMT_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main Plugin Class
 */
class Crypto_Miner_Tycoon_Pro {
    
    /**
     * Single instance of the class
     */
    private static $instance = null;
    
    /**
     * Get single instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init();
    }
    
    /**
     * Initialize plugin
     */
    private function init() {
        // Load required files
        $this->load_dependencies();
        
        // Initialize components
        $this->init_components();
        
        // Activation/deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        // Global helpers (must load before all classes)
        require_once CMT_PLUGIN_DIR . 'includes/cmt-helpers.php';

        // Core classes
        require_once CMT_PLUGIN_DIR . 'includes/class-miner-shortcode.php';
        require_once CMT_PLUGIN_DIR . 'includes/class-cmt-admin.php';
        require_once CMT_PLUGIN_DIR . 'includes/class-cmt-cloud-save.php';
        require_once CMT_PLUGIN_DIR . 'includes/class-cmt-ai-storyline.php';

        // Pro features
        require_once CMT_PLUGIN_DIR . 'includes/class-cmt-pro-branding.php';
        require_once CMT_PLUGIN_DIR . 'includes/class-cmt-contest-database.php';
        require_once CMT_PLUGIN_DIR . 'includes/class-cmt-contest-manager.php';
        require_once CMT_PLUGIN_DIR . 'includes/class-cmt-contests-admin.php';
        require_once CMT_PLUGIN_DIR . 'includes/class-cmt-custom-login.php';
    }
    
    /**
     * Initialize plugin components
     */
    private function init_components() {
        // Initialize shortcode handler
        new CMT_Miner_Shortcode();
        
        // Initialize admin (only in admin area)
        if (is_admin()) {
            new CMT_Admin();
            new CMT_Pro_Branding();
            new CMT_Contests_Admin();
        }
        
        // Initialize cloud save REST API
        new CMT_Cloud_Save();

        // Initialize AI Storyline REST endpoint
        new CMT_AI_Storyline();
        
        // Initialize contest manager (for AJAX handlers)
        new CMT_Contest_Manager();
        
        // Initialize custom login
        new CMT_Custom_Login();
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create database table if cloud saves are enabled
        $this->maybe_create_table();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Maybe create database table for cloud saves
     * Updated in 0.9.0: Added difficulty column for player-selectable difficulty
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
        
        // Migration: Add best_rank_score column if it doesn't exist (for upgrades from older versions)
        $this->maybe_add_best_rank_score_column();
        
        // Migration: Add difficulty column if it doesn't exist (for upgrades from 0.8.x)
        $this->maybe_add_difficulty_column();
    }
    
    /**
     * Add best_rank_score column if upgrading from older version
     */
    private function maybe_add_best_rank_score_column() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cmt_saves';
        
        // Check if column exists
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema check
        $column_exists = $wpdb->get_results(
            $wpdb->prepare(
                "SHOW COLUMNS FROM {$table_name} LIKE %s",
                'best_rank_score'
            )
        );
        
        if (empty($column_exists)) {
            // Add the column
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Schema migration
            $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN best_rank_score decimal(30,6) DEFAULT 0 AFTER rank_score");
            
            // Copy current rank_score to best_rank_score for existing users
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Data migration
            $wpdb->query("UPDATE {$table_name} SET best_rank_score = rank_score WHERE best_rank_score = 0");
            
            // Add index
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Schema migration
            $wpdb->query("ALTER TABLE {$table_name} ADD KEY best_rank_score (best_rank_score DESC)");
        }
    }
    
    /**
     * Add difficulty column if upgrading from 0.8.x
     * New in 0.9.0
     */
    private function maybe_add_difficulty_column() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cmt_saves';
        
        // Check if column exists
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema check
        $column_exists = $wpdb->get_results(
            $wpdb->prepare(
                "SHOW COLUMNS FROM {$table_name} LIKE %s",
                'difficulty'
            )
        );
        
        if (empty($column_exists)) {
            // Get the current admin difficulty setting to use as default for existing players
            $admin_difficulty = get_option('cmt_difficulty', 'medium');
            
            // Add the column with admin's current setting as default
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Schema migration
            $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN difficulty varchar(10) DEFAULT 'medium' AFTER best_rank_score");
            
            // Set existing players to the current admin difficulty (they stay at current difficulty)
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Data migration
            $wpdb->update(
                $table_name,
                array('difficulty' => $admin_difficulty),
                array('difficulty' => 'medium'),
                array('%s'),
                array('%s')
            );
            
            // Actually, set ALL existing players to admin difficulty since column just added
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Data migration
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table_name} SET difficulty = %s",
                    $admin_difficulty
                )
            );
            
            // Add index for difficulty filtering
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Schema migration
            $wpdb->query("ALTER TABLE {$table_name} ADD KEY difficulty (difficulty)");
        }
    }
}

// Initialize plugin
function crypto_miner_tycoon() {
    return Crypto_Miner_Tycoon_Pro::get_instance();
}

// Start the plugin
crypto_miner_tycoon();
