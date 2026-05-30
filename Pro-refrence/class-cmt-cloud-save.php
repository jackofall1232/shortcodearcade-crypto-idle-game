<?php
/**
 * Cloud Save Handler Class
 * 
 * Handles REST API endpoints for saving/loading game data
 * 
 * Version: 0.9.0 - Added difficulty support and per-difficulty leaderboards
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class CMT_Cloud_Save {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Save game endpoint
        register_rest_route('cmt/v1', '/save', array(
            'methods' => 'POST',
            'callback' => array($this, 'save_game'),
            'permission_callback' => array($this, 'check_cloud_save_permission'),
            'args' => array(
                'save_data' => array(
                    'required' => true,
                    'type' => 'object',
                    'validate_callback' => array($this, 'validate_save_data')
                )
            )
        ));
        
        // Load game endpoint
        register_rest_route('cmt/v1', '/load', array(
            'methods' => 'GET',
            'callback' => array($this, 'load_game'),
            'permission_callback' => array($this, 'check_cloud_save_permission')
        ));
        
        // Get leaderboard endpoint (updated in 0.9.0 for difficulty filtering)
        register_rest_route('cmt/v1', '/leaderboard', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_leaderboard'),
            'permission_callback' => '__return_true', // Public endpoint
            'args' => array(
                'difficulty' => array(
                    'required' => false,
                    'type' => 'string',
                    'default' => null,
                    'sanitize_callback' => 'sanitize_text_field'
                )
            )
        ));
        
        // Change difficulty endpoint (New in 0.9.0)
        register_rest_route('cmt/v1', '/change-difficulty', array(
            'methods' => 'POST',
            'callback' => array($this, 'change_difficulty'),
            'permission_callback' => array($this, 'check_cloud_save_permission'),
            'args' => array(
                'difficulty' => array(
                    'required' => true,
                    'type' => 'string',
                    'validate_callback' => array($this, 'validate_difficulty')
                )
            )
        ));
    }
    
    /**
     * Check if user has permission for cloud saves
     */
    public function check_cloud_save_permission() {
        // Cloud saves must be enabled
        if (!get_option('cmt_enable_cloud_saves', false)) {
            return new WP_Error(
                'cloud_saves_disabled',
                'Cloud saves are not enabled on this site.',
                array('status' => 403)
            );
        }
        
        // User must be logged in
        if (!is_user_logged_in()) {
            return new WP_Error(
                'not_logged_in',
                'You must be logged in to use cloud saves.',
                array('status' => 401)
            );
        }
        
        return true;
    }
    
    /**
     * Validate save data structure
     */
    public function validate_save_data($value, $request, $param) {
        // Check required fields exist
        $required_fields = array(
            'satoshis',
            'clickPower',
            'passiveIncome',
            'rating',
            'prestigeLevel',
            'prestigeMultiplier',
            'upgrades'
        );
        
        foreach ($required_fields as $field) {
            if (!isset($value[$field])) {
                return new WP_Error(
                    'invalid_save_data',
                    "Missing required field: $field",
                    array('status' => 400)
                );
            }
        }
        
        // Validate data types
        if (!is_numeric($value['satoshis']) || $value['satoshis'] < 0) {
            return new WP_Error('invalid_save_data', 'Invalid satoshis value', array('status' => 400));
        }
        
        if (!is_numeric($value['clickPower']) || $value['clickPower'] < 1) {
            return new WP_Error('invalid_save_data', 'Invalid clickPower value', array('status' => 400));
        }
        
        if (!is_numeric($value['passiveIncome']) || $value['passiveIncome'] < 0) {
            return new WP_Error('invalid_save_data', 'Invalid passiveIncome value', array('status' => 400));
        }
        
        if (!is_int($value['prestigeLevel']) || $value['prestigeLevel'] < 0) {
            return new WP_Error('invalid_save_data', 'Invalid prestigeLevel value', array('status' => 400));
        }
        
        if (!is_array($value['upgrades'])) {
            return new WP_Error('invalid_save_data', 'Upgrades must be an object', array('status' => 400));
        }
        
        // Validate difficulty if provided (New in 0.9.0)
        if (isset($value['difficulty'])) {
            $valid_difficulties = array('easy', 'medium', 'hard');
            if (!in_array($value['difficulty'], $valid_difficulties)) {
                return new WP_Error('invalid_save_data', 'Invalid difficulty value', array('status' => 400));
            }
        }
        
        // Anti-cheat: Basic sanity check
        $max_reasonable_satoshis = $this->calculate_max_possible_earnings($value);
        if ($value['satoshis'] > $max_reasonable_satoshis * 2) {
            // Flag suspicious but allow (for now - you can make this stricter)
            error_log("CMT: Suspicious save data for user " . get_current_user_id() . " - earnings exceed theoretical maximum");
        }
        
        return true;
    }
    
    /**
     * Validate difficulty value
     * New in 0.9.0
     */
    public function validate_difficulty($value, $request, $param) {
        $valid_difficulties = array('easy', 'medium', 'hard');
        if (!in_array($value, $valid_difficulties)) {
            return new WP_Error(
                'invalid_difficulty',
                'Difficulty must be easy, medium, or hard.',
                array('status' => 400)
            );
        }
        return true;
    }
    
    /**
     * Calculate theoretical maximum earnings (anti-cheat)
     */
    private function calculate_max_possible_earnings($save_data) {
        // Rough calculation: assume 30 days of 24/7 play with max possible production
        $max_days = 30;
        $seconds_per_day = 86400;
        
        // Assume max 10 clicks per second for click power
        $max_click_earnings = $save_data['clickPower'] * $save_data['prestigeMultiplier'] * 10 * $seconds_per_day * $max_days;
        
        // Passive income over 30 days
        $max_passive_earnings = $save_data['passiveIncome'] * $save_data['prestigeMultiplier'] * $seconds_per_day * $max_days;
        
        return $max_click_earnings + $max_passive_earnings;
    }
    
    /**
     * Save game data
     * 
     * Updated in 0.9.0: Now saves difficulty column
     */
    public function save_game($request) {
        global $wpdb;
        
        $user_id = get_current_user_id();
        $save_data = $request->get_param('save_data');
        
        // Calculate current rank score (logarithmic + prestige weighted)
        $current_rank_score = $this->calculate_rank_score(
            $save_data['satoshis'],
            $save_data['prestigeLevel']
        );
        
        $table_name = $wpdb->prefix . 'cmt_saves';
        
        // Get existing data
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table lookup
        $existing = $wpdb->get_row(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name safely constructed
                "SELECT best_rank_score, difficulty FROM {$table_name} WHERE user_id = %d",
                $user_id
            ),
            ARRAY_A
        );
        
        // Use best_rank_score from save_data if provided, otherwise calculate
        $best_rank_score = isset($save_data['bestRankScore']) ? floatval($save_data['bestRankScore']) : $current_rank_score;
        
        // Ensure we never decrease the best score (server-side protection)
        if ($existing && floatval($existing['best_rank_score']) > $best_rank_score) {
            $best_rank_score = floatval($existing['best_rank_score']);
        }
        
        // Update save_data with server-validated best score
        $save_data['bestRankScore'] = $best_rank_score;
        
        // Determine difficulty (New in 0.9.0)
        // Priority: save_data > existing > admin default
        $difficulty = 'medium';
        if (isset($save_data['difficulty']) && in_array($save_data['difficulty'], array('easy', 'medium', 'hard'))) {
            $difficulty = $save_data['difficulty'];
        } elseif ($existing && !empty($existing['difficulty'])) {
            $difficulty = $existing['difficulty'];
        } else {
            $difficulty = get_option('cmt_difficulty', 'medium');
        }
        
        // Ensure difficulty is in save_data
        $save_data['difficulty'] = $difficulty;
        
        // Prepare data for insertion
        $data = array(
            'user_id' => $user_id,
            'save_data' => wp_json_encode($save_data),
            'base_click_power' => floatval($save_data['clickPower']),
            'base_passive_income' => floatval($save_data['passiveIncome']),
            'prestige_level' => intval($save_data['prestigeLevel']),
            'total_satoshis' => floatval($save_data['satoshis']),
            'rank_score' => floatval($current_rank_score),
            'best_rank_score' => floatval($best_rank_score),
            'difficulty' => $difficulty
        );
        
        $format = array('%d', '%s', '%f', '%f', '%d', '%f', '%f', '%f', '%s');
        
        if ($existing) {
            // Update existing save
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table update
            $result = $wpdb->update(
                $table_name,
                $data,
                array('user_id' => $user_id),
                $format,
                array('%d')
            );
        } else {
            // Insert new save
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table insert
            $result = $wpdb->insert($table_name, $data, $format);
        }
        
        if ($result === false) {
            return new WP_Error(
                'save_failed',
                'Failed to save game data.',
                array('status' => 500)
            );
        }
        
        return array(
            'success' => true,
            'message' => 'Game saved successfully.',
            'rank_score' => $current_rank_score,
            'best_rank_score' => $best_rank_score,
            'difficulty' => $difficulty
        );
    }
    
    /**
     * Load game data
     * Updated in 0.9.0: Returns difficulty
     */
    public function load_game($request) {
        global $wpdb;
        
        $user_id = get_current_user_id();
        $table_name = $wpdb->prefix . 'cmt_saves';
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table lookup
        $row = $wpdb->get_row(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name safely constructed
                "SELECT save_data, best_rank_score, difficulty FROM {$table_name} WHERE user_id = %d",
                $user_id
            ),
            ARRAY_A
        );
        
        if (!$row) {
            // Return default difficulty for new players
            return array(
                'success' => false,
                'message' => 'No saved game found.',
                'data' => null,
                'difficulty' => get_option('cmt_difficulty', 'medium')
            );
        }
        
        $decoded_data = json_decode($row['save_data'], true);
        
        if (!$decoded_data) {
            return new WP_Error(
                'corrupt_save',
                'Save data is corrupted.',
                array('status' => 500)
            );
        }
        
        // Ensure bestRankScore is included from DB (server authoritative)
        $decoded_data['bestRankScore'] = floatval($row['best_rank_score']);
        
        // Ensure difficulty is included (New in 0.9.0)
        $decoded_data['difficulty'] = !empty($row['difficulty']) ? $row['difficulty'] : get_option('cmt_difficulty', 'medium');
        
        return array(
            'success' => true,
            'message' => 'Game loaded successfully.',
            'data' => $decoded_data,
            'difficulty' => $decoded_data['difficulty']
        );
    }
    
    /**
     * Change player difficulty
     * New in 0.9.0
     * 
     * This resets the current run but preserves prestige level and best rank score.
     */
    public function change_difficulty($request) {
        global $wpdb;
        
        // Check if player difficulty selection is enabled
        if (!get_option('cmt_allow_player_difficulty', false)) {
            return new WP_Error(
                'feature_disabled',
                'Player difficulty selection is not enabled.',
                array('status' => 403)
            );
        }
        
        $user_id = get_current_user_id();
        $new_difficulty = $request->get_param('difficulty');
        $table_name = $wpdb->prefix . 'cmt_saves';
        
        // Get existing save data
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table lookup
        $existing = $wpdb->get_row(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name safely constructed
                "SELECT * FROM {$table_name} WHERE user_id = %d",
                $user_id
            ),
            ARRAY_A
        );
        
        if (!$existing) {
            // No save exists, just return the new difficulty (will be set on first save)
            return array(
                'success' => true,
                'message' => 'Difficulty preference set. Will apply on first save.',
                'difficulty' => $new_difficulty,
                'reset_performed' => false
            );
        }
        
        // Decode existing save data
        $save_data = json_decode($existing['save_data'], true);
        if (!$save_data) {
            $save_data = array();
        }
        
        // Check if difficulty is actually changing
        $old_difficulty = isset($save_data['difficulty']) ? $save_data['difficulty'] : $existing['difficulty'];
        if ($old_difficulty === $new_difficulty) {
            return array(
                'success' => true,
                'message' => 'Already on this difficulty.',
                'difficulty' => $new_difficulty,
                'reset_performed' => false
            );
        }
        
        // Preserve prestige data
        $preserved_prestige_level = isset($save_data['prestigeLevel']) ? intval($save_data['prestigeLevel']) : intval($existing['prestige_level']);
        $preserved_prestige_multiplier = 1 + ($preserved_prestige_level * 0.1);
        $preserved_best_rank_score = floatval($existing['best_rank_score']);
        
        // Create reset save data (fresh start at new difficulty)
        $reset_save_data = array(
            'satoshis' => 0,
            'clickPower' => 1,
            'passiveIncome' => 0,
            'rating' => 1000,
            'prestigeLevel' => $preserved_prestige_level,
            'prestigeMultiplier' => $preserved_prestige_multiplier,
            'upgrades' => new stdClass(), // Empty object for JSON
            'bestRankScore' => $preserved_best_rank_score,
            'lastActiveTime' => time() * 1000, // JavaScript timestamp
            'minersActive' => true,
            'difficulty' => $new_difficulty,
            'version' => '0.9.0'
        );
        
        // Update database
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table update
        $result = $wpdb->update(
            $table_name,
            array(
                'save_data' => wp_json_encode($reset_save_data),
                'base_click_power' => 1,
                'base_passive_income' => 0,
                'total_satoshis' => 0,
                'rank_score' => 0,
                'difficulty' => $new_difficulty
                // Note: prestige_level and best_rank_score are NOT updated (preserved)
            ),
            array('user_id' => $user_id),
            array('%s', '%f', '%f', '%f', '%f', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error(
                'update_failed',
                'Failed to change difficulty.',
                array('status' => 500)
            );
        }
        
        return array(
            'success' => true,
            'message' => 'Difficulty changed to ' . ucfirst($new_difficulty) . '. Your run has been reset.',
            'difficulty' => $new_difficulty,
            'reset_performed' => true,
            'preserved' => array(
                'prestigeLevel' => $preserved_prestige_level,
                'prestigeMultiplier' => $preserved_prestige_multiplier,
                'bestRankScore' => $preserved_best_rank_score
            ),
            'new_save_data' => $reset_save_data
        );
    }
    
    /**
     * Get leaderboard
     * 
     * Updated in 0.9.0: 
     * - Uses best_rank_score for ordering
     * - Supports difficulty filtering when player difficulty is enabled
     */
    public function get_leaderboard($request) {
        // Check if leaderboard is enabled
        if (!get_option('cmt_enable_leaderboard', false)) {
            return new WP_Error(
                'leaderboard_disabled',
                'Leaderboard is not enabled on this site.',
                array('status' => 403)
            );
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'cmt_saves';
        $limit = get_option('cmt_leaderboard_limit', 10);
        
        // Check if filtering by difficulty
        $allow_player_difficulty = get_option('cmt_allow_player_difficulty', false);
        $requested_difficulty = $request->get_param('difficulty');
        
        // Build query based on whether difficulty filtering is needed
        if ($allow_player_difficulty && $requested_difficulty && in_array($requested_difficulty, array('easy', 'medium', 'hard'))) {
            // Filter by specific difficulty
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table join query for live leaderboard
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name safely constructed
                    "SELECT 
                        s.user_id,
                        s.total_satoshis,
                        s.prestige_level,
                        s.rank_score,
                        s.best_rank_score,
                        s.difficulty,
                        s.last_updated,
                        u.display_name
                    FROM {$table_name} s
                    LEFT JOIN {$wpdb->users} u ON s.user_id = u.ID
                    WHERE s.difficulty = %s
                    ORDER BY s.best_rank_score DESC
                    LIMIT %d",
                    $requested_difficulty,
                    $limit
                ),
                ARRAY_A
            );
        } else {
            // No difficulty filter - show all (legacy behavior or when player difficulty disabled)
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table join query for live leaderboard
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name safely constructed
                    "SELECT 
                        s.user_id,
                        s.total_satoshis,
                        s.prestige_level,
                        s.rank_score,
                        s.best_rank_score,
                        s.difficulty,
                        s.last_updated,
                        u.display_name
                    FROM {$table_name} s
                    LEFT JOIN {$wpdb->users} u ON s.user_id = u.ID
                    ORDER BY s.best_rank_score DESC
                    LIMIT %d",
                    $limit
                ),
                ARRAY_A
            );
        }
        
        if (!$results) {
            return array(
                'success' => true,
                'leaderboard' => array(),
                'difficulty_filter' => $requested_difficulty,
                'allow_player_difficulty' => $allow_player_difficulty
            );
        }
        
        // Format leaderboard data
        $leaderboard = array();
        $rank = 1;
        
        foreach ($results as $row) {
            $leaderboard[] = array(
                'rank' => $rank++,
                'username' => sanitize_text_field($row['display_name']),
                'satoshis' => floatval($row['total_satoshis']),
                'prestige_level' => intval($row['prestige_level']),
                'rank_score' => floatval($row['best_rank_score']), // Use best score for display
                'difficulty' => isset($row['difficulty']) ? $row['difficulty'] : 'medium',
                'last_updated' => $row['last_updated']
            );
        }
        
        return array(
            'success' => true,
            'leaderboard' => $leaderboard,
            'difficulty_filter' => $requested_difficulty,
            'allow_player_difficulty' => $allow_player_difficulty
        );
    }
    
    /**
     * Get leaderboard by difficulty
     * New in 0.9.0: Helper method for shortcode
     */
    public static function get_leaderboard_by_difficulty($difficulty = null, $limit = 10) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cmt_saves';
        
        $allow_player_difficulty = get_option('cmt_allow_player_difficulty', false);
        
        if ($allow_player_difficulty && $difficulty && in_array($difficulty, array('easy', 'medium', 'hard'))) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
            return $wpdb->get_results(
                $wpdb->prepare(
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name safely constructed
                    "SELECT s.user_id, s.total_satoshis, s.prestige_level, s.best_rank_score, s.difficulty, s.last_updated, u.display_name
                    FROM {$table_name} s 
                    LEFT JOIN {$wpdb->users} u ON s.user_id = u.ID
                    WHERE s.difficulty = %s
                    ORDER BY s.best_rank_score DESC 
                    LIMIT %d",
                    $difficulty,
                    $limit
                ),
                ARRAY_A
            );
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
            return $wpdb->get_results(
                $wpdb->prepare(
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name safely constructed
                    "SELECT s.user_id, s.total_satoshis, s.prestige_level, s.best_rank_score, s.difficulty, s.last_updated, u.display_name
                    FROM {$table_name} s 
                    LEFT JOIN {$wpdb->users} u ON s.user_id = u.ID
                    ORDER BY s.best_rank_score DESC 
                    LIMIT %d",
                    $limit
                ),
                ARRAY_A
            );
        }
    }
    
    /**
     * Calculate rank score
     * Uses logarithmic scaling + prestige weighting to prevent raw currency inflation
     */
    private function calculate_rank_score($satoshis, $prestige_level) {
        // Logarithmic base score (prevents inflation)
        $base_score = log10($satoshis + 1) * 1000;
        
        // Prestige bonus (linear bonus for each prestige level)
        $prestige_bonus = $prestige_level * 10000;
        
        return $base_score + $prestige_bonus;
    }
}
