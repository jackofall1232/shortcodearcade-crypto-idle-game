<?php
/**
 * Contest Manager Class
 * 
 * Handles contest creation, management, and winner selection
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class CMT_Contest_Manager {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Register AJAX handlers
        add_action('wp_ajax_cmt_create_contest', array($this, 'ajax_create_contest'));
        add_action('wp_ajax_cmt_end_contest', array($this, 'ajax_end_contest'));
        add_action('wp_ajax_cmt_delete_contest', array($this, 'ajax_delete_contest'));
    }
    
    /**
     * Create a new contest
     */
    public static function create_contest($data) {
        global $wpdb;
        
        // Validate required fields
        if (empty($data['period_name']) || empty($data['period_start'])) {
            return new WP_Error('missing_data', 'Contest name and start date are required.');
        }
        
        $table_name = $wpdb->prefix . 'cmt_leaderboard_periods';
        
        $insert_data = array(
            'period_type' => sanitize_text_field($data['period_type'] ?? 'custom'),
            'period_name' => sanitize_text_field($data['period_name']),
            'period_start' => sanitize_text_field($data['period_start']),
            'period_end' => !empty($data['period_end']) ? sanitize_text_field($data['period_end']) : null,
            'status' => 'active',
            'prize_first' => !empty($data['prize_first']) ? sanitize_text_field($data['prize_first']) : null,
            'prize_second' => !empty($data['prize_second']) ? sanitize_text_field($data['prize_second']) : null,
            'prize_third' => !empty($data['prize_third']) ? sanitize_text_field($data['prize_third']) : null,
            'created_by' => get_current_user_id()
        );
        
        $format = array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d');
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table for contest data
        $result = $wpdb->insert($table_name, $insert_data, $format);
        
        if ($result === false) {
            return new WP_Error('db_error', 'Failed to create contest.');
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Is at least one contest currently active?
     * New in 1.0.0 — used to suppress AI storyline popups during contests.
     *
     * @return bool
     */
    public static function has_active_contest() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cmt_leaderboard_periods';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, data changes frequently.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name safely constructed with $wpdb->prefix.
        $count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table_name} WHERE status = 'active' LIMIT 1"
        );

        return intval($count) > 0;
    }

    /**
     * Get active contests
     */
    public static function get_active_contests() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cmt_leaderboard_periods';
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, data changes frequently
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name safely constructed with $wpdb->prefix
        return $wpdb->get_results(
            "SELECT * FROM {$table_name} 
             WHERE status = 'active' 
             ORDER BY period_start DESC",
            ARRAY_A
        );
    }
    
    /**
     * Get contest by ID
     */
    public static function get_contest($period_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cmt_leaderboard_periods';
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, single record lookup
        return $wpdb->get_row(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name safely constructed with $wpdb->prefix
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE period_id = %d",
                $period_id
            ),
            ARRAY_A
        );
    }
    
    /**
     * Get all contests (with pagination)
     */
    public static function get_contests($args = array()) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cmt_leaderboard_periods';
        
        $defaults = array(
            'status' => null,
            'limit' => 20,
            'offset' => 0,
            'orderby' => 'period_start',
            'order' => 'DESC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Whitelist orderby and order values to prevent SQL injection
        $allowed_orderby = array('period_start', 'period_end', 'period_name', 'status', 'created_at');
        $orderby = in_array($args['orderby'], $allowed_orderby, true) ? $args['orderby'] : 'period_start';
        $order = in_array(strtoupper($args['order']), array('ASC', 'DESC'), true) ? strtoupper($args['order']) : 'DESC';
        
        $where = '';
        if ($args['status']) {
            $where = $wpdb->prepare("WHERE status = %s", $args['status']);
        }
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table with pagination
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name and whitelisted columns safely constructed
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
                $args['limit'],
                $args['offset']
            ),
            ARRAY_A
        );
    }
    
    /**
     * End a contest and select winners
     */
    public static function end_contest($period_id) {
        global $wpdb;
        
        $periods_table = $wpdb->prefix . 'cmt_leaderboard_periods';
        $scores_table = $wpdb->prefix . 'cmt_period_scores';
        $winners_table = $wpdb->prefix . 'cmt_winners';
        
        // Get contest
        $contest = self::get_contest($period_id);
        if (!$contest) {
            return new WP_Error('not_found', 'Contest not found.');
        }
        
        if ($contest['status'] === 'completed') {
            return new WP_Error('already_completed', 'Contest already completed.');
        }
        
        // Get top 3 players
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table join query
        $top_players = $wpdb->get_results(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names safely constructed with $wpdb->prefix
            $wpdb->prepare(
                "SELECT ps.*, u.display_name, u.user_email
                 FROM {$scores_table} ps
                 LEFT JOIN {$wpdb->users} u ON ps.user_id = u.ID
                 WHERE ps.period_id = %d
                 ORDER BY ps.rank_score DESC
                 LIMIT 3",
                $period_id
            ),
            ARRAY_A
        );
        
        if (empty($top_players)) {
            return new WP_Error('no_players', 'No players participated in this contest.');
        }
        
        // Update final ranks in scores table
        $rank = 1;
        foreach ($top_players as $player) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table update
            $wpdb->update(
                $scores_table,
                array('final_rank' => $rank),
                array('score_id' => $player['score_id']),
                array('%d'),
                array('%d')
            );
            $rank++;
        }
        
        // Create winner records
        $winner_rank = 1;
        $prizes = array(
            1 => $contest['prize_first'],
            2 => $contest['prize_second'],
            3 => $contest['prize_third']
        );
        
        foreach ($top_players as $player) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table for winner records
            $wpdb->insert(
                $winners_table,
                array(
                    'period_id' => $period_id,
                    'user_id' => $player['user_id'],
                    'winner_rank' => $winner_rank,
                    'final_satoshis' => $player['total_satoshis'],
                    'prestige_level' => $player['prestige_level'],
                    'rank_score' => $player['rank_score'],
                    'prize_description' => $prizes[$winner_rank] ?? null
                ),
                array('%d', '%d', '%d', '%f', '%d', '%f', '%s')
            );
            
            // Award achievement
            self::award_achievement($player['user_id'], 'contest_winner_rank_' . $winner_rank, array(
                'period_id' => $period_id,
                'period_name' => $contest['period_name'],
                'rank' => $winner_rank,
                'prize' => $prizes[$winner_rank] ?? null
            ));
            
            $winner_rank++;
        }
        
        // Update contest status
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table update
        $wpdb->update(
            $periods_table,
            array(
                'status' => 'completed',
                'completed_at' => current_time('mysql')
            ),
            array('period_id' => $period_id),
            array('%s', '%s'),
            array('%d')
        );
        
        return $top_players;
    }
    
    /**
     * Save player score to contest
     */
    public static function save_contest_score($period_id, $user_id, $game_data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cmt_period_scores';
        
        // Calculate rank score
        $rank_score = self::calculate_rank_score(
            $game_data['satoshis'],
            $game_data['prestigeLevel']
        );
        
        $data = array(
            'period_id' => $period_id,
            'user_id' => $user_id,
            'total_satoshis' => floatval($game_data['satoshis']),
            'base_click_power' => floatval($game_data['clickPower']),
            'base_passive_income' => floatval($game_data['passiveIncome']),
            'prestige_level' => intval($game_data['prestigeLevel']),
            'rating' => intval($game_data['rating']),
            'rank_score' => floatval($rank_score)
        );
        
        // Check if score exists
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table lookup
        $existing = $wpdb->get_var(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name safely constructed with $wpdb->prefix
            $wpdb->prepare(
                "SELECT score_id FROM {$table_name} WHERE period_id = %d AND user_id = %d",
                $period_id,
                $user_id
            )
        );
        
        if ($existing) {
            // Update existing
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table update
            $result = $wpdb->update(
                $table_name,
                $data,
                array('period_id' => $period_id, 'user_id' => $user_id),
                array('%d', '%d', '%f', '%f', '%f', '%d', '%d', '%f'),
                array('%d', '%d')
            );
        } else {
            // Insert new
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table insert
            $result = $wpdb->insert($table_name, $data, array('%d', '%d', '%f', '%f', '%f', '%d', '%d', '%f'));
        }
        
        return $result !== false;
    }
    
    /**
     * Get contest leaderboard
     */
    public static function get_contest_leaderboard($period_id, $limit = 100) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cmt_period_scores';
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table join query for live leaderboard
        $results = $wpdb->get_results(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name safely constructed with $wpdb->prefix
            $wpdb->prepare(
                "SELECT ps.*, u.display_name
                 FROM {$table_name} ps
                 LEFT JOIN {$wpdb->users} u ON ps.user_id = u.ID
                 WHERE ps.period_id = %d
                 ORDER BY ps.rank_score DESC
                 LIMIT %d",
                $period_id,
                $limit
            ),
            ARRAY_A
        );
        
        // Add rank numbers
        $rank = 1;
        foreach ($results as &$row) {
            $row['rank'] = $rank++;
        }
        
        return $results;
    }
    
    /**
     * Get winners for a contest
     */
    public static function get_contest_winners($period_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cmt_winners';
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table join query
        return $wpdb->get_results(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name safely constructed with $wpdb->prefix
            $wpdb->prepare(
                "SELECT w.*, u.display_name, u.user_email
                 FROM {$table_name} w
                 LEFT JOIN {$wpdb->users} u ON w.user_id = u.ID
                 WHERE w.period_id = %d
                 ORDER BY w.winner_rank ASC",
                $period_id
            ),
            ARRAY_A
        );
    }
    
    /**
     * Delete a contest
     */
    public static function delete_contest($period_id) {
        global $wpdb;
        
        $periods_table = $wpdb->prefix . 'cmt_leaderboard_periods';
        $scores_table = $wpdb->prefix . 'cmt_period_scores';
        $winners_table = $wpdb->prefix . 'cmt_winners';
        
        // Delete in correct order (foreign key constraints)
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table delete
        $wpdb->delete($winners_table, array('period_id' => $period_id), array('%d'));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table delete
        $wpdb->delete($scores_table, array('period_id' => $period_id), array('%d'));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table delete
        $wpdb->delete($periods_table, array('period_id' => $period_id), array('%d'));
        
        return true;
    }
    
    /**
     * Award achievement to user
     */
    private static function award_achievement($user_id, $achievement_type, $data = array()) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cmt_achievements';
        
        // Check if already exists
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table lookup
        $existing = $wpdb->get_var(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name safely constructed with $wpdb->prefix
            $wpdb->prepare(
                "SELECT achievement_id FROM {$table_name} WHERE user_id = %d AND achievement_type = %s",
                $user_id,
                $achievement_type
            )
        );
        
        if ($existing) {
            return false; // Already has this achievement
        }
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table insert
        return $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'achievement_type' => $achievement_type,
                'achievement_data' => wp_json_encode($data)
            ),
            array('%d', '%s', '%s')
        );
    }
    
    /**
     * Calculate rank score (same as cloud save)
     */
    private static function calculate_rank_score($satoshis, $prestige_level) {
        $base_score = log10($satoshis + 1) * 1000;
        $prestige_bonus = $prestige_level * 10000;
        return $base_score + $prestige_bonus;
    }
    
    /**
     * AJAX: Create contest
     */
    public function ajax_create_contest() {
        check_ajax_referer('cmt_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied.');
        }
        
        $result = self::create_contest($_POST);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success(array(
            'period_id' => $result,
            'message' => 'Contest created successfully!'
        ));
    }
    
    /**
     * AJAX: End contest
     */
    public function ajax_end_contest() {
        check_ajax_referer('cmt_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied.');
        }
        
        $period_id = isset($_POST['period_id']) ? intval($_POST['period_id']) : 0;
        
        if (!$period_id) {
            wp_send_json_error('Invalid contest ID.');
        }
        
        $result = self::end_contest($period_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success(array(
            'winners' => $result,
            'message' => 'Contest ended and winners selected!'
        ));
    }
    
    /**
     * AJAX: Delete contest
     */
    public function ajax_delete_contest() {
        check_ajax_referer('cmt_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied.');
        }
        
        $period_id = isset($_POST['period_id']) ? intval($_POST['period_id']) : 0;
        
        if (!$period_id) {
            wp_send_json_error('Invalid contest ID.');
        }
        
        self::delete_contest($period_id);
        
        wp_send_json_success('Contest deleted successfully!');
    }
}
