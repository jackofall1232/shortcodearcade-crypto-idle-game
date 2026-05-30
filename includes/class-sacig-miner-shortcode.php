<?php
/**
 * Shortcode Handler Class
 *
 * Handles the [sacig_crypto_idle_game], [sacig_crypto_idle_leaderboard],
 * [sacig_crypto_idle_login], and [sacig_crypto_idle_register] shortcodes.
 * Renders game UI, applies branding, manages asset enqueuing, and displays leaderboards.
 *
 * @package Shortcode_Arcade_Crypto_Idle_Game
 * @since 0.4.6
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class SACIG_Miner_Shortcode {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_shortcode('sacig_crypto_idle_game', array($this, 'render_game'));
        add_shortcode('sacig_crypto_idle_leaderboard', array($this, 'render_leaderboard'));
        add_shortcode('sacig_crypto_idle_login', array($this, 'render_login'));
        add_shortcode('sacig_crypto_idle_register', array($this, 'render_register'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_filter('registration_redirect', array($this, 'filter_registration_redirect'));
    }

    /**
     * Build a scoped <style> block applying admin branding colors.
     *
     * Returns an empty string when the admin has not saved any branding colors,
     * so the game keeps its default neon theme until customized.
     *
     * @return string
     */
    private function get_branding_style() {
        $primary   = get_option('sacig_primary_color');
        $secondary = get_option('sacig_secondary_color');
        $accent    = get_option('sacig_accent_color');

        if (!$primary && !$secondary && !$accent) {
            return '';
        }

        $vars = '';
        if ($primary) {
            $vars .= '--sacig-neon-cyan:' . $primary . ';';
        }
        if ($secondary) {
            $vars .= '--sacig-neon-magenta:' . $secondary . ';';
        }
        if ($accent) {
            $vars .= '--sacig-neon-yellow:' . $accent . ';';
        }

        return '<style>.sacig-container,.sacig-leaderboard-container{' . esc_html($vars) . '}</style>';
    }
    
    /**
     * Enqueue CSS and JS assets
     */
    public function enqueue_assets() {
        // Only enqueue if shortcode is present on the page
        global $post;
        
        if (is_a($post, 'WP_Post') && (
            has_shortcode($post->post_content, 'sacig_crypto_idle_game')
            || has_shortcode($post->post_content, 'sacig_crypto_idle_leaderboard')
            || has_shortcode($post->post_content, 'sacig_crypto_idle_login')
            || has_shortcode($post->post_content, 'sacig_crypto_idle_register')
        )) {
            // Enqueue Google Fonts
            // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Google Fonts handles versioning via URL parameters
            wp_enqueue_style(
    'sacig-google-fonts',
    'https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@300;400;600&display=swap',
    array(),
    SACIG_VERSION
);

            // Enqueue game CSS
            wp_enqueue_style(
                'sacig-game-css',
                SACIG_PLUGIN_URL . 'assets/css/sacig-game.css',
                array(),
                SACIG_VERSION
            );
            
            // Only enqueue JS for the game shortcode
            if (has_shortcode($post->post_content, 'sacig_crypto_idle_game')) {
                wp_enqueue_script(
                    'sacig-game-js',
                    SACIG_PLUGIN_URL . 'assets/js/sacig-game.js',
                    array(),
                    SACIG_VERSION,
                    true
                );

                // Pass settings to JavaScript
                $this->localize_script();
            }
        }
    }
    
    /**
     * Localize script with settings and data
     */
    private function localize_script() {
        $cloud_saves_enabled = get_option('sacig_enable_cloud_saves', false);

        $script_data = array(
            'cloudSavesEnabled' => $cloud_saves_enabled,
            'isUserLoggedIn' => is_user_logged_in(),
            'restUrl' => rest_url('sacig/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'userId' => get_current_user_id(),
            'currencyName' => get_option('sacig_currency_name') ?: 'Satoshis',
        );

        wp_localize_script('sacig-game-js', 'sacigSettings', $script_data);

        // Gameplay settings (difficulty, anti-bot button mode, self-reset).
        if ( class_exists( 'SACIG_Admin' ) ) {
            wp_localize_script(
                'sacig-game-js',
                'sacigGameplay',
                SACIG_Admin::get_gameplay_settings()
            );
        }

        // AI Storyline data for the optional narrative popups. The nonce ties
        // requests to this site so the paid endpoint cannot be called anonymously.
        wp_localize_script(
            'sacig-game-js',
            'sacigAI',
            array(
                'enabled'  => (bool) get_option('sacig_ai_storyline_enabled', false),
                'endpoint' => rest_url('sacig/v1/storyline'),
                'nonce'    => wp_create_nonce('wp_rest'),
                'media'    => array(
                    'general' => get_option('sacig_ai_media_prestige_general', ''),
                    'level5'  => get_option('sacig_ai_media_prestige_5', ''),
                    'level10' => get_option('sacig_ai_media_prestige_10', ''),
                ),
            )
        );
    }
    
    /**
     * Render the game
     */
    public function render_game($atts) {
        // Parse shortcode attributes
        $atts = shortcode_atts(
            array(
                'ad_code' => '', // Allow custom ad code via shortcode attribute
            ),
            $atts,
            'sacig_crypto_idle_game'
        );
        
        // Check if cloud saves are enabled and user is not logged in
        $cloud_saves_enabled = get_option('sacig_enable_cloud_saves', false);
        $show_login_notice = $cloud_saves_enabled && !is_user_logged_in();

        // Branding options
        $game_title    = get_option('sacig_game_title') ?: 'Shortcode Arcade Crypto Idle Game';
        $currency_name = get_option('sacig_currency_name') ?: 'Satoshis';
        $coin_image    = get_option('sacig_coin_image');
        $footer_text   = get_option('sacig_footer_text');

        // Start output buffering
        ob_start();

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- value escaped inside get_branding_style()
        echo $this->get_branding_style();
        ?>

        <?php if ($show_login_notice): ?>
            <div class="sacig-login-notice">
                <p><strong>Note:</strong> Cloud saves are enabled. Please <a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>">log in</a> to save your progress.</p>
            </div>
        <?php endif; ?>
        
        <div class="sacig-container">
            <button class="sacig-info-button" onclick="sacigShowModal()">?</button>
            
            <header class="sacig-header">
                <h1 class="sacig-title"><?php echo esc_html($game_title); ?></h1>
                <div class="sacig-subtitle">Click. Mine. Prosper.</div>
            </header>

            <div class="sacig-main-game">
                <div class="sacig-game-area">
                    <div class="sacig-stats">
                        <div class="sacig-stat-item">
                            <span class="sacig-stat-label"><?php echo esc_html($currency_name); ?></span>
                            <span class="sacig-stat-value" id="sacig-satoshis">0</span>
                        </div>
                        <div class="sacig-stat-item">
                            <span class="sacig-stat-label">Per Click</span>
                            <span class="sacig-stat-value" id="sacig-clickPower">1</span>
                        </div>
                        <div class="sacig-stat-item">
                            <span class="sacig-stat-label">Per Second</span>
                            <span class="sacig-stat-value" id="sacig-passiveIncome">0</span>
                        </div>
                        <div class="sacig-stat-item">
                            <span class="sacig-stat-label">Miner Rating</span>
                            <span class="sacig-stat-value" id="sacig-rating">1000</span>
                        </div>
                    </div>

                    <div class="sacig-mine-button" id="sacig-mineButton" onclick="sacigMine()">
                        <?php if ($coin_image): ?>
                        <img src="<?php echo esc_url($coin_image); ?>" alt="<?php echo esc_attr($currency_name); ?>" class="sacig-coin-image">
                        <?php else: ?>
                        <svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg">
                            <defs>
                                <linearGradient id="sacig-coinGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" style="stop-color:#00ffff;stop-opacity:1" />
                                    <stop offset="50%" style="stop-color:#ff00ff;stop-opacity:1" />
                                    <stop offset="100%" style="stop-color:#ffff00;stop-opacity:1" />
                                </linearGradient>
                            </defs>
                            <circle cx="100" cy="100" r="80" fill="url(#sacig-coinGrad)" opacity="0.2"/>
                            <circle cx="100" cy="100" r="75" fill="none" stroke="url(#sacig-coinGrad)" stroke-width="4"/>
                            <path d="M 80 60 L 80 140 M 90 60 L 90 140" stroke="url(#sacig-coinGrad)" stroke-width="3" stroke-linecap="round"/>
                            <path d="M 70 75 L 120 75 C 130 75 135 80 135 90 C 135 100 130 105 120 105 L 70 105 M 70 105 L 125 105 C 135 105 140 110 140 120 C 140 130 135 135 125 135 L 70 135" 
                                  fill="none" stroke="url(#sacig-coinGrad)" stroke-width="4" stroke-linecap="round"/>
                        </svg>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="sacig-upgrades">
                    <h2>Upgrades</h2>
                    <div id="sacig-upgradesList"></div>
                </div>
            </div>

            <div class="sacig-prestige-section">
                <div class="sacig-prestige-info">
                    Hard Fork available at 1,000,000 <?php echo esc_html(strtolower($currency_name)); ?><br>
                    <span style="font-size: 0.9rem; opacity: 0.7;">Reset with permanent +10% bonus to all production</span>
                </div>
                <button class="sacig-prestige-button" id="sacig-prestigeButton" onclick="sacigPrestige()" disabled>
                    HARD FORK
                </button>
            </div>

            <?php if (!empty($atts['ad_code'])) : ?>
                <div class="sacig-ad-container">
                    <div class="sacig-ad-label">Advertisement</div>
                    <div class="sacig-ad-content">
                        <?php echo wp_kses_post($atts['ad_code']); ?>
                    </div>
                </div>
            <?php else : ?>
                <div class="sacig-ad-container">
                    <div class="sacig-ad-label">Advertisement</div>
                    <div class="sacig-ad-placeholder">
                        728 x 90 Ad Space
                    </div>
                </div>
            <?php endif; ?>

            <footer class="sacig-footer">
                <?php if ($footer_text): ?>
                    <?php echo esc_html($footer_text); ?>
                <?php else: ?>
                    <?php echo esc_html($game_title); ?> © <?php echo esc_html(gmdate('Y')); ?> | Game auto-saves every 10 seconds
                <?php endif; ?>
            </footer>
        </div>

        <div class="sacig-save-indicator" id="sacig-saveIndicator">Game Saved</div>

        <!-- Info Modal -->
        <div class="sacig-modal" id="sacig-infoModal">
            <div class="sacig-modal-content">
                <h2>How to Play</h2>
                <p><strong>Goal:</strong> Build the ultimate crypto mining empire!</p>
                <p><strong>Click the Bitcoin:</strong> Earn <?php echo esc_html(strtolower($currency_name)); ?> manually by clicking the glowing Bitcoin symbol.</p>
                <p><strong>Buy Upgrades:</strong> Spend <?php echo esc_html(strtolower($currency_name)); ?> on upgrades to increase your mining power and automate your income.</p>
                <p><strong>Elo Rating System:</strong> As you progress, your miner rating increases. Higher ratings unlock more powerful upgrades, but they also cost more based on the difficulty curve.</p>
                <p><strong>Hard Fork (Prestige):</strong> Once you reach 1,000,000 <?php echo esc_html(strtolower($currency_name)); ?>, you can perform a "Hard Fork" to reset your progress with a permanent +10% production bonus. This multiplier stacks!</p>
                <p><strong>Strategy:</strong> Balance between manual clicking upgrades and passive income generators for optimal growth.</p>
                <?php if ($cloud_saves_enabled && is_user_logged_in()): ?>
                    <p><strong>Cloud Saves:</strong> Your progress is automatically saved to the cloud!</p>
                <?php endif; ?>
                <button class="sacig-close-modal" onclick="sacigHideModal()">Start Mining!</button>
            </div>
        </div>
        
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render leaderboard
     */
    public function render_leaderboard($atts) {
        // Check if leaderboard is enabled
        if (!get_option('sacig_enable_leaderboard', false)) {
            return '<p class="sacig-leaderboard-disabled">Leaderboard is not enabled.</p>';
        }

        // Parse attributes
        $atts = shortcode_atts(
            array(
                'limit' => get_option('sacig_leaderboard_limit', 10),
            ),
            $atts,
            'sacig_crypto_idle_leaderboard'
        );

        // Get leaderboard data
        global $wpdb;
        $table_name  = $wpdb->prefix . 'sacig_saves';
        $users_table = $wpdb->users;
        $limit       = intval($atts['limit']);

        // Direct query required: leaderboard aggregation with JOIN and ORDER BY on custom table.
        // No WP_Query or equivalent API supports cross-table aggregation with custom tables.
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter
        // Table names are safely constructed using $wpdb->prefix and $wpdb->users constants.
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    s.user_id,
                    s.total_satoshis,
                    s.prestige_level,
                    s.best_rank_score AS rank_score,
                    s.last_updated,
                    u.display_name
                FROM {$table_name} s
                LEFT JOIN {$users_table} u ON s.user_id = u.ID
                ORDER BY s.best_rank_score DESC
                LIMIT %d",
                $limit
            ),
            ARRAY_A
        );
        // phpcs:enable

        // Display options
        $lb_title      = get_option('sacig_leaderboard_title') ?: 'Leaderboard';
        $show_avatars  = get_option('sacig_leaderboard_show_avatars', true);
        $highlight     = get_option('sacig_leaderboard_highlight_color') ?: '#7c3aed';
        $currency_name = get_option('sacig_currency_name') ?: 'Satoshis';

        // Start output
        ob_start();

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- value escaped inside get_branding_style()
        echo $this->get_branding_style();
        ?>
        <div class="sacig-leaderboard-container">
            <h2 class="sacig-leaderboard-title">&#x1F3C6; <?php echo esc_html($lb_title); ?></h2>

            <?php if (empty($results)): ?>
                <p class="sacig-leaderboard-empty">No players yet. Be the first!</p>
            <?php else: ?>
                <table class="sacig-leaderboard-table">
                    <thead>
                        <tr>
                            <th class="sacig-rank">Rank</th>
                            <th class="sacig-player">Player</th>
                            <th class="sacig-satoshis"><?php echo esc_html($currency_name); ?></th>
                            <th class="sacig-prestige">Prestige</th>
                            <th class="sacig-score">Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $rank = 1;
                        foreach ($results as $row): 
                            $rank_class = '';
                            if ($rank === 1) $rank_class = 'sacig-rank-1';
                            elseif ($rank === 2) $rank_class = 'sacig-rank-2';
                            elseif ($rank === 3) $rank_class = 'sacig-rank-3';
                            
                            $is_current_user = is_user_logged_in() && get_current_user_id() == $row['user_id'];
                        ?>
                        <tr class="<?php echo esc_attr($rank_class); ?> <?php echo $is_current_user ? 'sacig-current-user' : ''; ?>"<?php echo $is_current_user ? ' style="box-shadow: inset 4px 0 0 ' . esc_attr($highlight) . ';"' : ''; ?>>
                            <td class="sacig-rank">
                                <?php if ($rank <= 3): ?>
                                    <span class="sacig-medal">
                                        <?php echo $rank === 1 ? '🥇' : ($rank === 2 ? '🥈' : '🥉'); ?>
                                    </span>
                                <?php else: ?>
                                    <?php echo esc_html($rank); ?>
                                <?php endif; ?>
                            </td>
                            <td class="sacig-player">
                                <?php if ($show_avatars): ?>
                                    <span class="sacig-avatar"><?php echo get_avatar($row['user_id'], 28); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_avatar() returns safe, escaped HTML ?></span>
                                <?php endif; ?>
                                <?php echo esc_html($row['display_name']); ?>
                                <?php if ($is_current_user): ?>
                                    <span class="sacig-you-badge">You</span>
                                <?php endif; ?>
                            </td>
                            <td class="sacig-satoshis"><?php echo esc_html(number_format($row['total_satoshis'], 2)); ?></td>
                            <td class="sacig-prestige">Level <?php echo esc_html($row['prestige_level']); ?></td>
                            <td class="sacig-score"><?php echo esc_html(number_format($row['rank_score'], 0)); ?></td>
                        </tr>
                        <?php 
                        $rank++;
                        endforeach; 
                        ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php

        return ob_get_clean();
    }

    /**
     * Render the login form ([sacig_crypto_idle_login]).
     */
    public function render_login($atts) {
        $form_title    = get_option('sacig_login_form_title') ?: 'Log In to Play';
        $show_register = get_option('sacig_login_show_register', true);
        $redirect_url  = get_option('sacig_login_redirect_url');
        $redirect      = $redirect_url ? $redirect_url : ( is_singular() ? get_permalink() : home_url() );

        ob_start();

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- value escaped inside get_branding_style()
        echo $this->get_branding_style();
        ?>
        <div class="sacig-container sacig-auth-container">
            <header class="sacig-header">
                <h1 class="sacig-title"><?php echo esc_html($form_title); ?></h1>
            </header>
            <?php if (is_user_logged_in()): ?>
                <p class="sacig-auth-notice">
                    <?php
                    $current_user = wp_get_current_user();
                    /* translators: %s: current user's display name. */
                    printf(esc_html__('You are logged in as %s.', 'shortcodearcade-crypto-idle-game'), '<strong>' . esc_html($current_user->display_name) . '</strong>');
                    ?>
                    <a href="<?php echo esc_url(wp_logout_url($redirect)); ?>"><?php esc_html_e('Log out', 'shortcodearcade-crypto-idle-game'); ?></a>
                </p>
            <?php else: ?>
                <?php
                wp_login_form(array(
                    'echo'     => true,
                    'redirect' => $redirect,
                ));
                ?>
                <?php if ($show_register && get_option('users_can_register')): ?>
                    <p class="sacig-auth-register-link">
                        <a href="<?php echo esc_url(wp_registration_url()); ?>"><?php esc_html_e('Need an account? Register', 'shortcodearcade-crypto-idle-game'); ?></a>
                    </p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render the registration form ([sacig_crypto_idle_register]).
     */
    public function render_register($atts) {
        ob_start();

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- value escaped inside get_branding_style()
        echo $this->get_branding_style();
        ?>
        <div class="sacig-container sacig-auth-container">
            <header class="sacig-header">
                <h1 class="sacig-title"><?php esc_html_e('Create an Account', 'shortcodearcade-crypto-idle-game'); ?></h1>
            </header>
            <?php if (is_user_logged_in()): ?>
                <p class="sacig-auth-notice"><?php esc_html_e('You already have an account and are logged in.', 'shortcodearcade-crypto-idle-game'); ?></p>
            <?php elseif (!get_option('users_can_register')): ?>
                <p class="sacig-auth-notice"><?php esc_html_e('Registration is currently disabled on this site.', 'shortcodearcade-crypto-idle-game'); ?></p>
            <?php else: ?>
                <form name="registerform" id="sacig-registerform" action="<?php echo esc_url(site_url('wp-login.php?action=register', 'login_post')); ?>" method="post">
                    <p>
                        <label for="sacig-user-login"><?php esc_html_e('Username', 'shortcodearcade-crypto-idle-game'); ?></label>
                        <input type="text" name="user_login" id="sacig-user-login" class="input" value="" size="20" autocapitalize="off" required>
                    </p>
                    <p>
                        <label for="sacig-user-email"><?php esc_html_e('Email', 'shortcodearcade-crypto-idle-game'); ?></label>
                        <input type="email" name="user_email" id="sacig-user-email" class="input" value="" size="25" required>
                    </p>
                    <p class="sacig-auth-note"><?php esc_html_e('Registration confirmation will be emailed to you.', 'shortcodearcade-crypto-idle-game'); ?></p>
                    <p class="submit">
                        <input type="submit" name="wp-submit" id="sacig-wp-submit" class="button button-primary" value="<?php esc_attr_e('Register', 'shortcodearcade-crypto-idle-game'); ?>">
                    </p>
                </form>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Filter the post-registration redirect to the configured URL, when set.
     *
     * @param string $registration_redirect The redirect destination URL.
     * @return string
     */
    public function filter_registration_redirect($registration_redirect) {
        $url = get_option('sacig_register_redirect_url');
        return $url ? $url : $registration_redirect;
    }
}
