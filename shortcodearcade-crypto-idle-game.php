<?php
/**
 * Plugin Name: Shortcode Arcade Crypto Idle Game
 * Plugin URI: https://github.com/jackofall1232/shortcodearcade-crypto-idle-game
 * Description: A crypto-themed idle clicker game with balanced progression, prestige mechanics, and optional leaderboards. Use the [sacig_crypto_idle_game] shortcode to display the game.
 * Version: 2.0.2
 * Author: ZillHa Games
 * Author URI: https://zillha.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: shortcodearcade-crypto-idle-game
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin constants
 */
define( 'SACIG_VERSION', '2.0.2' );
define( 'SACIG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SACIG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SACIG_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin bootstrap class
 */
final class SACIG_Bootstrap {

	/**
	 * Singleton instance
	 *
	 * @var SACIG_Bootstrap|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance
	 *
	 * @return SACIG_Bootstrap
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
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
		$this->load_dependencies();
		$this->init_components();

		// Run schema upgrades on init (not admin_init) so the new columns exist
		// for frontend REST requests on sites that upgrade without an admin visit.
		add_action( 'init', array( $this, 'maybe_upgrade_db' ) );

		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
	}

	/**
	 * Create or migrate the saves table when the plugin version changes.
	 *
	 * Runs on every context (front end included). dbDelta adds any columns
	 * missing from an older schema, so this also migrates existing tables.
	 */
	public function maybe_upgrade_db() {
		if ( SACIG_VERSION === get_option( 'sacig_db_version' ) ) {
			return;
		}

		if ( get_option( 'sacig_enable_cloud_saves', false ) ) {
			$this->maybe_create_table();
		}

		update_option( 'sacig_db_version', SACIG_VERSION );
	}

	/**
	 * Load required files
	 */
	private function load_dependencies() {
		require_once SACIG_PLUGIN_DIR . 'includes/class-sacig-miner-shortcode.php';
		require_once SACIG_PLUGIN_DIR . 'includes/class-sacig-admin.php';
		require_once SACIG_PLUGIN_DIR . 'includes/class-sacig-cloud-save.php';
		require_once SACIG_PLUGIN_DIR . 'includes/class-sacig-branding.php';
		require_once SACIG_PLUGIN_DIR . 'includes/class-sacig-login-pages.php';
		require_once SACIG_PLUGIN_DIR . 'includes/class-sacig-ai-storyline.php';
	}

	/**
	 * Initialize components
	 */
	private function init_components() {
		new SACIG_Miner_Shortcode();
		new SACIG_Cloud_Save();

		// Feature modules register their own hooks/shortcodes/REST routes.
		$branding = new SACIG_Branding();
		$login    = new SACIG_Login_Pages();
		$ai       = new SACIG_AI_Storyline();

		// Admin reuses the single feature instances above instead of constructing
		// new hook-registering objects when rendering each settings page.
		if ( is_admin() ) {
			new SACIG_Admin( $branding, $login, $ai );
		}
	}

	/**
	 * Plugin activation
	 */
	public function activate() {
		$this->maybe_create_table();
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation
	 */
	public function deactivate() {
		flush_rewrite_rules();
	}

	/**
	 * Create database table for cloud saves if needed
	 */
	private function maybe_create_table() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'sacig_saves';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
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
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}
}

/**
 * Bootstrap helper
 *
 * @return SACIG_Bootstrap
 */
function sacig_bootstrap() {
	return SACIG_Bootstrap::get_instance();
}

sacig_bootstrap();
