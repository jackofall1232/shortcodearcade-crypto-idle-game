<?php
/**
 * Database Schema Class
 * 
 * Handles creation and management of contest-related database tables
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class CMT_Contest_Database {
    
    /**
     * Database version
     */
    const DB_VERSION = '1.0.0';
    
    /**
     * Create all contest tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Create leaderboard periods table
        self::create_periods_table($charset_collate);
        
        // Create period scores table
        self::create_period_scores_table($charset_collate);
        
        // Create winners table
        self::create_winners_table($charset_collate);
        
        // Create achievements table
        self::create_achievements_table($charset_collate);
        
        // Update database version
        update_option('cmt_contest_db_version', self::DB_VERSION);
    }
    
    /**
     * Create leaderboard periods table
     */
    private static function create_periods_table($charset_collate) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cmt_leaderboard_periods';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            period_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            period_type varchar(20) NOT NULL DEFAULT 'custom',
            period_name varchar(100) NOT NULL,
            period_start datetime NOT NULL,
            period_end datetime DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            prize_info text DEFAULT NULL,
            prize_first varchar(500) DEFAULT NULL,
            prize_second varchar(500) DEFAULT NULL,
            prize_third varchar(500) DEFAULT NULL,
            winner_notified tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            created_by bigint(20) UNSIGNED DEFAULT NULL,
            PRIMARY KEY (period_id),
            KEY status (status),
            KEY period_type (period_type),
            KEY period_dates (period_start, period_end)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Create period scores table
     */
    private static function create_period_scores_table($charset_collate) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cmt_period_scores';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            score_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            period_id bigint(20) UNSIGNED NOT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            total_satoshis decimal(30,6) DEFAULT 0,
            base_click_power decimal(20,6) DEFAULT 1,
            base_passive_income decimal(20,6) DEFAULT 0,
            prestige_level int DEFAULT 0,
            rating int DEFAULT 1000,
            rank_score decimal(30,6) DEFAULT 0,
            final_rank int DEFAULT NULL,
            last_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (score_id),
            UNIQUE KEY period_user (period_id, user_id),
            KEY rank_score (rank_score DESC),
            KEY period_id (period_id),
            KEY user_id (user_id)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Create winners table
     */
    private static function create_winners_table($charset_collate) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cmt_winners';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            winner_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            period_id bigint(20) UNSIGNED NOT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            winner_rank int NOT NULL,
            final_satoshis decimal(30,6) DEFAULT 0,
            prestige_level int DEFAULT 0,
            rank_score decimal(30,6) DEFAULT 0,
            prize_description text DEFAULT NULL,
            prize_claimed tinyint(1) NOT NULL DEFAULT 0,
            prize_claimed_date datetime DEFAULT NULL,
            notified tinyint(1) NOT NULL DEFAULT 0,
            notified_date datetime DEFAULT NULL,
            awarded_date datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (winner_id),
            KEY period_id (period_id),
            KEY user_id (user_id),
            KEY winner_rank (winner_rank)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Create achievements table
     */
    private static function create_achievements_table($charset_collate) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cmt_achievements';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            achievement_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            achievement_type varchar(50) NOT NULL,
            achievement_data text DEFAULT NULL,
            earned_date datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (achievement_id),
            UNIQUE KEY user_achievement (user_id, achievement_type),
            KEY earned_date (earned_date),
            KEY achievement_type (achievement_type)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Check if tables exist
     */
    public static function tables_exist() {
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'cmt_leaderboard_periods',
            $wpdb->prefix . 'cmt_period_scores',
            $wpdb->prefix . 'cmt_winners',
            $wpdb->prefix . 'cmt_achievements'
        );
        
        foreach ($tables as $table) {
            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Get database version
     */
    public static function get_db_version() {
        return get_option('cmt_contest_db_version', '0.0.0');
    }
    
    /**
     * Check if database needs update
     */
    public static function needs_update() {
        $current_version = self::get_db_version();
        return version_compare($current_version, self::DB_VERSION, '<');
    }
    
    /**
     * Drop all contest tables (for debugging/uninstall)
     */
    public static function drop_tables() {
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'cmt_achievements',
            $wpdb->prefix . 'cmt_winners',
            $wpdb->prefix . 'cmt_period_scores',
            $wpdb->prefix . 'cmt_leaderboard_periods'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
        
        delete_option('cmt_contest_db_version');
    }
    
    /**
     * Get table statistics
     */
    public static function get_table_stats() {
        global $wpdb;
        
        return array(
            'periods' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}cmt_leaderboard_periods"),
            'scores' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}cmt_period_scores"),
            'winners' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}cmt_winners"),
            'achievements' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}cmt_achievements")
        );
    }
}
