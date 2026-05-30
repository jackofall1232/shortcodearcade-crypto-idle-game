<?php
/**
 * Cloud Save Handler Class
 *
 * Handles REST API endpoints for saving/loading game data.
 *
 * REST API Namespace: sacig/v1
 * Endpoints:
 * - POST /wp-json/sacig/v1/save - Save game progress (requires auth)
 * - GET /wp-json/sacig/v1/load - Load game progress (requires auth)
 * - GET /wp-json/sacig/v1/leaderboard - Public leaderboard data
 *
 * @package Shortcode_Arcade_Crypto_Idle_Game
 * @since 0.4.6
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cloud save REST API handler.
 *
 * Registers and handles REST endpoints in the sacig/v1 namespace.
 */
class SACIG_Cloud_Save {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API routes.
	 *
	 * @return void
	 */
	public function register_routes() {

		// Save game endpoint.
		register_rest_route(
			'sacig/v1',
			'/save',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'save_game' ),
				'permission_callback' => array( $this, 'check_cloud_save_permission' ),
				'args'                => array(
					'save_data' => array(
						'required'          => true,
						'type'              => 'object',
						'validate_callback' => array( $this, 'validate_save_data' ),
					),
				),
			)
		);

		// Load game endpoint.
		register_rest_route(
			'sacig/v1',
			'/load',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'load_game' ),
				'permission_callback' => array( $this, 'check_cloud_save_permission' ),
			)
		);

		// Get leaderboard endpoint (public).
		register_rest_route(
			'sacig/v1',
			'/leaderboard',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_leaderboard' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'difficulty' => array(
						'required' => false,
						'type'     => 'string',
					),
				),
			)
		);

		// Change difficulty endpoint.
		register_rest_route(
			'sacig/v1',
			'/change-difficulty',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'change_difficulty' ),
				'permission_callback' => array( $this, 'check_cloud_save_permission' ),
				'args'                => array(
					'difficulty' => array(
						'required'          => true,
						'type'              => 'string',
						'validate_callback' => array( $this, 'validate_difficulty' ),
					),
				),
			)
		);
	}

	/**
	 * Validate a difficulty value.
	 *
	 * @param mixed $value Value to validate.
	 * @return bool
	 */
	public function validate_difficulty( $value ) {
		return in_array( $value, array( 'easy', 'medium', 'hard' ), true );
	}

	/**
	 * Check if user has permission for cloud saves.
	 *
	 * @return true|WP_Error
	 */
	public function check_cloud_save_permission() {

		// Cloud saves must be enabled.
		$enabled = (bool) get_option( 'sacig_enable_cloud_saves', false );
		if ( ! $enabled ) {
			return new WP_Error(
				'cloud_saves_disabled',
				'Cloud saves are not enabled on this site.',
				array( 'status' => 403 )
			);
		}

		// User must be logged in.
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'not_logged_in',
				'You must be logged in to use cloud saves.',
				array( 'status' => 401 )
			);
		}

		return true;
	}

	/**
	 * Validate save data structure.
	 *
	 * @param mixed           $value   Value of the parameter.
	 * @param WP_REST_Request $request Request object.
	 * @param string          $param   Parameter name.
	 * @return true|WP_Error
	 */
	public function validate_save_data( $value, $request, $param ) {

		if ( ! is_array( $value ) ) {
			return new WP_Error(
				'invalid_save_data',
				'Save data must be an object.',
				array( 'status' => 400 )
			);
		}

		// Check required fields exist.
		$required_fields = array(
			'satoshis',
			'clickPower',
			'passiveIncome',
			'rating',
			'prestigeLevel',
			'prestigeMultiplier',
			'upgrades',
		);

		foreach ( $required_fields as $field ) {
			if ( ! array_key_exists( $field, $value ) ) {
				return new WP_Error(
					'invalid_save_data',
					'Missing required field: ' . sanitize_key( $field ),
					array( 'status' => 400 )
				);
			}
		}

		// Validate data types.
		if ( ! is_numeric( $value['satoshis'] ) || $value['satoshis'] < 0 ) {
			return new WP_Error( 'invalid_save_data', 'Invalid satoshis value', array( 'status' => 400 ) );
		}

		if ( ! is_numeric( $value['clickPower'] ) || $value['clickPower'] < 1 ) {
			return new WP_Error( 'invalid_save_data', 'Invalid clickPower value', array( 'status' => 400 ) );
		}

		if ( ! is_numeric( $value['passiveIncome'] ) || $value['passiveIncome'] < 0 ) {
			return new WP_Error( 'invalid_save_data', 'Invalid passiveIncome value', array( 'status' => 400 ) );
		}

		// REST request values commonly arrive as numeric strings; accept numeric and then validate bounds.
		if ( ! is_numeric( $value['prestigeLevel'] ) || (int) $value['prestigeLevel'] < 0 ) {
			return new WP_Error( 'invalid_save_data', 'Invalid prestigeLevel value', array( 'status' => 400 ) );
		}

		if ( ! is_array( $value['upgrades'] ) ) {
			return new WP_Error( 'invalid_save_data', 'Upgrades must be an object', array( 'status' => 400 ) );
		}

		// Anti-cheat: basic sanity check (log only during debug).
		$max_reasonable_satoshis = $this->calculate_max_possible_earnings( $value );
		if ( is_numeric( $max_reasonable_satoshis ) && $max_reasonable_satoshis > 0 ) {
			if ( (float) $value['satoshis'] > ( (float) $max_reasonable_satoshis * 2 ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug-only anti-cheat monitoring.
					error_log( 'SACIG: Suspicious save data for user ' . get_current_user_id() . ' - earnings exceed theoretical maximum' );
				}
			}
		}

		return true;
	}

	/**
	 * Calculate theoretical maximum earnings (anti-cheat).
	 *
	 * @param array $save_data Save data array.
	 * @return float
	 */
	private function calculate_max_possible_earnings( $save_data ) {

		// Rough calculation: assume 30 days of 24/7 play with max possible production.
		$max_days        = 30;
		$seconds_per_day = 86400;

		$click_power         = isset( $save_data['clickPower'] ) ? (float) $save_data['clickPower'] : 0.0;
		$prestige_multiplier = isset( $save_data['prestigeMultiplier'] ) ? (float) $save_data['prestigeMultiplier'] : 1.0;
		$passive_income      = isset( $save_data['passiveIncome'] ) ? (float) $save_data['passiveIncome'] : 0.0;

		// Assume max 10 clicks per second for click power.
		$max_click_earnings = $click_power * $prestige_multiplier * 10 * $seconds_per_day * $max_days;

		// Passive income over 30 days.
		$max_passive_earnings = $passive_income * $prestige_multiplier * $seconds_per_day * $max_days;

		return (float) ( $max_click_earnings + $max_passive_earnings );
	}

	/**
	 * Save game data.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return array|WP_Error
	 */
	public function save_game( $request ) {
		global $wpdb;

		$user_id   = get_current_user_id();
		$save_data = $request->get_param( 'save_data' );

		if ( ! is_array( $save_data ) ) {
			return new WP_Error(
				'invalid_save_data',
				'Save data must be an object.',
				array( 'status' => 400 )
			);
		}

		// Calculate rank score (logarithmic + prestige weighted).
		$rank_score = $this->calculate_rank_score(
			(float) $save_data['satoshis'],
			(int) $save_data['prestigeLevel']
		);

		// Resolve the player's difficulty (whitelisted) and its best-score column.
		// When player-selectable difficulty is disabled, the admin's global
		// difficulty is authoritative — the client value is ignored so it cannot
		// be spoofed to populate the wrong per-difficulty leaderboard column.
		if ( (bool) get_option( 'sacig_allow_player_difficulty', false ) ) {
			$difficulty = isset( $save_data['difficulty'] ) ? (string) $save_data['difficulty'] : 'medium';
		} else {
			$difficulty = (string) get_option( 'sacig_difficulty', 'medium' );
		}
		if ( ! in_array( $difficulty, array( 'easy', 'medium', 'hard' ), true ) ) {
			$difficulty = 'medium';
		}
		$diff_column = 'best_rank_score_' . $difficulty;

		// Persist the authoritative difficulty back into the stored JSON so a
		// reload reflects the enforced value (not a spoofed client one).
		$save_data['difficulty'] = $difficulty;

		$table_name = $wpdb->prefix . 'sacig_saves';

		$encoded = wp_json_encode( $save_data );
		if ( false === $encoded ) {
			return new WP_Error(
				'encode_failed',
				'Failed to encode save data.',
				array( 'status' => 500 )
			);
		}

		// Check if user already has a save (and read current best scores).
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		// Table name is safely constructed using $wpdb->prefix constant.
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT user_id, best_rank_score, best_rank_score_easy, best_rank_score_medium, best_rank_score_hard FROM {$table_name} WHERE user_id = %d",
				$user_id
			)
		);
		// phpcs:enable

		// Best scores never decrease.
		$current_best      = $existing ? (float) $existing->best_rank_score : 0.0;
		$current_best_diff = ( $existing && isset( $existing->{$diff_column} ) ) ? (float) $existing->{$diff_column} : 0.0;
		$best_rank_score   = max( $current_best, (float) $rank_score );
		$best_rank_diff    = max( $current_best_diff, (float) $rank_score );

		// Prepare data for insertion.
		$data = array(
			'user_id'             => $user_id,
			'save_data'           => $encoded,
			'base_click_power'    => (float) $save_data['clickPower'],
			'base_passive_income' => (float) $save_data['passiveIncome'],
			'prestige_level'      => (int) $save_data['prestigeLevel'],
			'total_satoshis'      => (float) $save_data['satoshis'],
			'rank_score'          => (float) $rank_score,
			'best_rank_score'     => (float) $best_rank_score,
			'difficulty'          => $difficulty,
			$diff_column          => (float) $best_rank_diff,
		);

		// Format order matches the $data keys above (the difficulty column is whitelisted).
		$format = array( '%d', '%s', '%f', '%f', '%d', '%f', '%f', '%f', '%s', '%f' );

		if ( $existing ) {
			// Update existing save.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table update
			$result = $wpdb->update(
				$table_name,
				$data,
				array( 'user_id' => $user_id ),
				$format,
				array( '%d' )
			);
		} else {
			// Insert new save.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table insert
			$result = $wpdb->insert( $table_name, $data, $format );
		}

		if ( false === $result ) {
			return new WP_Error(
				'save_failed',
				'Failed to save game data.',
				array( 'status' => 500 )
			);
		}

		return array(
			'success'         => true,
			'message'         => 'Game saved successfully.',
			'rank_score'      => (float) $rank_score,
			'best_rank_score' => (float) $best_rank_score,
			'difficulty'      => $difficulty,
		);
	}

	/**
	 * Load game data.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return array|WP_Error
	 */
	public function load_game( $request ) {
		global $wpdb;

		$user_id    = get_current_user_id();
		$table_name = $wpdb->prefix . 'sacig_saves';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		// Table name is safely constructed using $wpdb->prefix constant.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT save_data, difficulty FROM {$table_name} WHERE user_id = %d",
				$user_id
			)
		);
		// phpcs:enable

		if ( ! $row || ! $row->save_data ) {
			return array(
				'success' => false,
				'message' => 'No saved game found.',
				'data'    => null,
			);
		}

		$decoded_data = json_decode( $row->save_data, true );

		if ( ! is_array( $decoded_data ) ) {
			return new WP_Error(
				'corrupt_save',
				'Save data is corrupted.',
				array( 'status' => 500 )
			);
		}

		return array(
			'success'    => true,
			'message'    => 'Game loaded successfully.',
			'data'       => $decoded_data,
			'difficulty' => isset( $row->difficulty ) ? $row->difficulty : 'medium',
		);
	}

	/**
	 * Get leaderboard entries for a specific difficulty.
	 *
	 * Used by the leaderboard shortcode renderer (tabbed per-difficulty view).
	 * When per-player difficulty is disabled or no valid difficulty is given,
	 * falls back to ranking by the all-time best score.
	 *
	 * @param string|null $difficulty 'easy'|'medium'|'hard'|null.
	 * @param int         $limit      Max entries to return.
	 * @return array Row arrays (ARRAY_A).
	 */
	public static function get_leaderboard_by_difficulty( $difficulty = null, $limit = 10 ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'sacig_saves';

		$allow_player_difficulty = (bool) get_option( 'sacig_allow_player_difficulty', false );
		$valid                   = array( 'easy', 'medium', 'hard' );

		if ( $allow_player_difficulty && $difficulty && in_array( $difficulty, $valid, true ) ) {
			// $difficulty is whitelisted above — column interpolation is safe.
			$diff_col = 'best_rank_score_' . $difficulty;
			// $diff_col is whitelisted against array('easy','medium','hard') before
			// interpolation. $table_name uses $wpdb->prefix — a trusted constant.
			// SQL identifiers cannot use placeholder preparation.
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT s.user_id,
							s.total_satoshis,
							s.prestige_level,
							COALESCE(s.{$diff_col}, 0) AS rank_score,
							s.difficulty,
							s.last_updated,
							u.display_name
					 FROM {$table_name} s
					 LEFT JOIN {$wpdb->users} u ON s.user_id = u.ID
					 WHERE s.{$diff_col} > 0
					 ORDER BY s.{$diff_col} DESC
					 LIMIT %d",
					$limit
				),
				ARRAY_A
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			// phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter
			return $results;
		}

		// No difficulty filter — rank by all-time best score.
		// $diff_col is whitelisted against array('easy','medium','hard') before
		// interpolation. $table_name uses $wpdb->prefix — a trusted constant.
		// SQL identifiers cannot use placeholder preparation.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.user_id,
						s.total_satoshis,
						s.prestige_level,
						s.best_rank_score AS rank_score,
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
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter
		return $results;
	}

	/**
	 * Get leaderboard.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return array|WP_Error
	 */
	public function get_leaderboard( $request ) {

		// Check if leaderboard is enabled.
		$enabled = (bool) get_option( 'sacig_enable_leaderboard', false );
		if ( ! $enabled ) {
			return new WP_Error(
				'leaderboard_disabled',
				'Leaderboard is not enabled on this site.',
				array( 'status' => 403 )
			);
		}

		global $wpdb;

		$limit = (int) get_option( 'sacig_leaderboard_limit', 10 );
		if ( $limit < 1 ) {
			$limit = 10;
		}
		if ( $limit > 100 ) {
			$limit = 100;
		}

		$table_name  = $wpdb->prefix . 'sacig_saves';
		$users_table = $wpdb->users;

		// Determine whether to filter by a specific difficulty.
		// The score column is whitelisted below BEFORE any interpolation, so no
		// user-supplied value ever reaches the SQL string.
		$allow_diff   = (bool) get_option( 'sacig_allow_player_difficulty', false );
		$req_diff     = $request ? $request->get_param( 'difficulty' ) : '';
		$use_diff     = $allow_diff && in_array( $req_diff, array( 'easy', 'medium', 'hard' ), true );
		$score_column = 'rank_score';
		$where_clause = '';
		if ( $use_diff ) {
			$score_column = 'best_rank_score_' . $req_diff;
			$where_clause = "WHERE s.{$score_column} > 0";
		}

		// Direct query required: leaderboard aggregation with JOIN and ORDER BY on custom table.
		// No WP_Query or equivalent API supports cross-table aggregation with custom tables.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		// Table names use $wpdb->prefix/$wpdb->users; $score_column is whitelisted above.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					s.user_id,
					s.total_satoshis,
					s.prestige_level,
					s.{$score_column} AS rank_score,
					s.last_updated,
					u.display_name
				FROM {$table_name} s
				LEFT JOIN {$users_table} u ON s.user_id = u.ID
				{$where_clause}
				ORDER BY s.{$score_column} DESC
				LIMIT %d",
				$limit
			),
			ARRAY_A
		);
		// phpcs:enable

		if ( empty( $results ) ) {
			return array(
				'success'     => true,
				'leaderboard' => array(),
			);
		}

		// Format leaderboard data.
		$leaderboard = array();
		$rank        = 1;

		foreach ( $results as $row ) {
			$leaderboard[] = array(
				'rank'          => $rank++,
				'username'      => isset( $row['display_name'] ) ? sanitize_text_field( $row['display_name'] ) : '',
				'satoshis'      => isset( $row['total_satoshis'] ) ? (float) $row['total_satoshis'] : 0.0,
				'prestige_level'=> isset( $row['prestige_level'] ) ? (int) $row['prestige_level'] : 0,
				'rank_score'    => isset( $row['rank_score'] ) ? (float) $row['rank_score'] : 0.0,
				'last_updated'  => isset( $row['last_updated'] ) ? sanitize_text_field( $row['last_updated'] ) : '',
			);
		}

		return array(
			'success'     => true,
			'leaderboard' => $leaderboard,
		);
	}

	/**
	 * Change the player's difficulty.
	 *
	 * Resets the current run while preserving prestige progression and best
	 * scores (overall and per-difficulty).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return array|WP_Error
	 */
	public function change_difficulty( $request ) {
		global $wpdb;

		// Per-player difficulty must be enabled.
		if ( ! (bool) get_option( 'sacig_allow_player_difficulty', false ) ) {
			return new WP_Error(
				'difficulty_disabled',
				'Player-selectable difficulty is not enabled on this site.',
				array( 'status' => 403 )
			);
		}

		$user_id    = get_current_user_id();
		$difficulty = (string) $request->get_param( 'difficulty' );
		if ( ! in_array( $difficulty, array( 'easy', 'medium', 'hard' ), true ) ) {
			return new WP_Error(
				'invalid_difficulty',
				'Invalid difficulty value.',
				array( 'status' => 400 )
			);
		}

		$table_name = $wpdb->prefix . 'sacig_saves';

		// Load the existing save to preserve prestige and best scores.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		// Table name is safely constructed using $wpdb->prefix constant.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT save_data, prestige_level, best_rank_score, best_rank_score_easy, best_rank_score_medium, best_rank_score_hard FROM {$table_name} WHERE user_id = %d",
				$user_id
			)
		);
		// phpcs:enable

		$prestige_level      = $row ? (int) $row->prestige_level : 0;
		$prestige_multiplier = 1.0;
		$decoded             = $row ? json_decode( $row->save_data, true ) : array();
		if ( is_array( $decoded ) && isset( $decoded['prestigeMultiplier'] ) ) {
			$prestige_multiplier = (float) $decoded['prestigeMultiplier'];
		}

		// Build a fresh run state, keeping prestige progression.
		$fresh_save = array(
			'satoshis'           => 0,
			'clickPower'         => 1,
			'passiveIncome'      => 0,
			'rating'             => ( is_array( $decoded ) && isset( $decoded['rating'] ) ) ? $decoded['rating'] : 0,
			'prestigeLevel'      => $prestige_level,
			'prestigeMultiplier' => $prestige_multiplier,
			'upgrades'           => array(),
			'difficulty'         => $difficulty,
		);

		$encoded = wp_json_encode( $fresh_save );
		if ( false === $encoded ) {
			return new WP_Error(
				'encode_failed',
				'Failed to encode save data.',
				array( 'status' => 500 )
			);
		}

		// Best-score columns are intentionally omitted so they are preserved on update.
		$data = array(
			'user_id'             => $user_id,
			'save_data'           => $encoded,
			'base_click_power'    => 1,
			'base_passive_income' => 0,
			'prestige_level'      => $prestige_level,
			'total_satoshis'      => 0,
			'rank_score'          => 0,
			'difficulty'          => $difficulty,
		);
		$format = array( '%d', '%s', '%f', '%f', '%d', '%f', '%f', '%s' );

		if ( $row ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table update
			$result = $wpdb->update( $table_name, $data, array( 'user_id' => $user_id ), $format, array( '%d' ) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table insert
			$result = $wpdb->insert( $table_name, $data, $format );
		}

		if ( false === $result ) {
			return new WP_Error(
				'change_failed',
				'Failed to change difficulty.',
				array( 'status' => 500 )
			);
		}

		return array(
			'success'         => true,
			'difficulty'      => $difficulty,
			'reset_performed' => true,
			'preserved'       => array(
				'prestige_level'         => $prestige_level,
				'prestige_multiplier'    => $prestige_multiplier,
				'best_rank_score'        => $row ? (float) $row->best_rank_score : 0.0,
				'best_rank_score_easy'   => $row ? (float) $row->best_rank_score_easy : 0.0,
				'best_rank_score_medium' => $row ? (float) $row->best_rank_score_medium : 0.0,
				'best_rank_score_hard'   => $row ? (float) $row->best_rank_score_hard : 0.0,
			),
		);
	}

	/**
	 * Calculate rank score.
	 *
	 * Uses logarithmic scaling + prestige weighting to prevent raw currency inflation.
	 *
	 * @param float $satoshis       Total satoshis.
	 * @param int   $prestige_level Prestige level.
	 * @return float
	 */
	private function calculate_rank_score( $satoshis, $prestige_level ) {

		$satoshis       = (float) $satoshis;
		$prestige_level = (int) $prestige_level;

		// Logarithmic base score (prevents inflation).
		$base_score = log10( $satoshis + 1 ) * 1000;

		// Prestige bonus (linear bonus for each prestige level).
		$prestige_bonus = $prestige_level * 10000;

		return (float) ( $base_score + $prestige_bonus );
	}
}
