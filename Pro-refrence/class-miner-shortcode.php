<?php
/**
 * Shortcode Handler Class
 * 
 * Handles the [crypto_miner_tycoon] and [crypto_miner_leaderboard] shortcodes
 * 
 * Version: 0.9.0 - Player difficulty selection, tabbed leaderboards
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class CMT_Miner_Shortcode {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_shortcode('crypto_miner_tycoon', array($this, 'render_game'));
        add_shortcode('crypto_miner_leaderboard', array($this, 'render_leaderboard'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }
    
    /**
     * Enqueue CSS and JS assets
     */
    public function enqueue_assets() {
        global $post;
        
        if (is_a($post, 'WP_Post') && (has_shortcode($post->post_content, 'crypto_miner_tycoon') || has_shortcode($post->post_content, 'crypto_miner_leaderboard'))) {
            wp_enqueue_style('cmt-google-fonts', 'https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@300;400;600&display=swap', array(), null);
            wp_enqueue_style('cmt-game-css', CMT_PLUGIN_URL . 'assets/css/game.css', array(), CMT_VERSION);
            
            if (has_shortcode($post->post_content, 'crypto_miner_tycoon')) {
                wp_enqueue_script('cmt-game-js', CMT_PLUGIN_URL . 'assets/js/game.js', array(), CMT_VERSION, true);
                $this->localize_script();
            }
        }
    }
    
    /**
     * Localize script with settings and data
     */
    private function localize_script() {
        $cloud_saves_enabled = get_option('cmt_enable_cloud_saves', false);
        $branding = CMT_Pro_Branding::get_branding_settings();
        $gameplay = CMT_Admin::get_gameplay_settings();
        
        $contests_enabled = (bool) get_option('cmt_enable_contests', false);
        $in_contest = $contests_enabled
            && class_exists('CMT_Contest_Manager')
            && CMT_Contest_Manager::has_active_contest();

        $script_data = array(
            'cloudSavesEnabled'   => $cloud_saves_enabled,
            'isUserLoggedIn'      => is_user_logged_in(),
            'restUrl'             => rest_url('cmt/v1/'),
            'nonce'               => wp_create_nonce('wp_rest'),
            'userId'              => get_current_user_id(),
            'branding'            => $branding,
            'gameplay'            => $gameplay,
            'aiStorylineEnabled'  => (bool) get_option('cmt_ai_storyline_enabled', false),
            'aiMedia'             => array(
                'upgrades' => array(
                    'betterClicker'   => esc_url( get_option( 'cmt_ai_media_upgrade_betterClicker',   '' ) ),
                    'cpuMiner'        => esc_url( get_option( 'cmt_ai_media_upgrade_cpuMiner',        '' ) ),
                    'powerfulClicker' => esc_url( get_option( 'cmt_ai_media_upgrade_powerfulClicker', '' ) ),
                    'gpuRig'          => esc_url( get_option( 'cmt_ai_media_upgrade_gpuRig',          '' ) ),
                    'megaClicker'     => esc_url( get_option( 'cmt_ai_media_upgrade_megaClicker',     '' ) ),
                    'asicMiner'       => esc_url( get_option( 'cmt_ai_media_upgrade_asicMiner',       '' ) ),
                    'ultraClicker'    => esc_url( get_option( 'cmt_ai_media_upgrade_ultraClicker',    '' ) ),
                    'miningFarm'      => esc_url( get_option( 'cmt_ai_media_upgrade_miningFarm',      '' ) ),
                    'godClicker'      => esc_url( get_option( 'cmt_ai_media_upgrade_godClicker',      '' ) ),
                    'datacenter'      => esc_url( get_option( 'cmt_ai_media_upgrade_datacenter',      '' ) ),
                ),
                'prestige' => array(
                    'general'     => esc_url( get_option( 'cmt_ai_media_prestige_general', '' ) ),
                    'milestone5'  => esc_url( get_option( 'cmt_ai_media_prestige_5',       '' ) ),
                    'milestone10' => esc_url( get_option( 'cmt_ai_media_prestige_10',      '' ) ),
                ),
            ),
            'inContest'           => $in_contest,
            'labels'              => array(
                'perClick'        => cmt_label( 'per_click',             'Per Click'    ),
                'perSecond'       => cmt_label( 'per_second',            'Per Second'   ),
                'minerRating'     => cmt_label( 'miner_rating',          'Miner Rating' ),
                'difficulty'      => cmt_label( 'difficulty',            'DIFFICULTY'   ),
                'minersActive'    => cmt_label( 'miners_active',         'Miners Active' ),
                'minersStopped'   => cmt_label( 'miners_stopped',        'Miners Stopped' ),
                'minersRestart'   => cmt_label( 'miners_restart',        'Restart' ),
                'findCoinPrompt'  => cmt_label( 'find_coin_prompt',      'Find the brighter coin to mine!' ),
                'hardFork'        => cmt_label( 'hard_fork',             'Hard Fork'    ),
                'hardForkDesc'    => cmt_label( 'hard_fork_description', 'Reset with permanent +10% bonus to all production' ),
                'chaosLevel'      => cmt_label( 'chaos_level',           'Chaos Level'  ),
                'difficultyLabel' => cmt_label( 'difficulty_label',      'Difficulty'   ),
                'easy'            => cmt_label( 'difficulty_easy',       'Easy'         ),
                'medium'          => cmt_label( 'difficulty_medium',     'Medium'       ),
                'hard'            => cmt_label( 'difficulty_hard',       'Hard'         ),
            ),
        );
        
        wp_localize_script('cmt-game-js', 'cmtSettings', $script_data);
    }
    
    /**
     * Render coin button (real or decoy)
     */
    private function render_coin_button($is_real, $index, $branding) {
        $class = 'cmt-mine-button ' . ($is_real ? 'cmt-real-button' : 'cmt-decoy-button');
        $data_real = $is_real ? 'true' : 'false';
        
        ob_start();
        ?>
        <div class="<?php echo esc_attr($class); ?>" data-real="<?php echo esc_attr($data_real); ?>" data-index="<?php echo esc_attr($index); ?>">
            <?php if ($branding['enabled'] && !empty($branding['customCoin'])): ?>
                <img src="<?php echo esc_url($branding['customCoin']); ?>" alt="Coin" class="cmt-custom-coin-img">
            <?php else: ?>
            <svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <linearGradient id="cmt-coinGrad<?php echo esc_attr($index); ?>" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" style="stop-color:#00ffff;stop-opacity:1" />
                        <stop offset="50%" style="stop-color:#ff00ff;stop-opacity:1" />
                        <stop offset="100%" style="stop-color:#ffff00;stop-opacity:1" />
                    </linearGradient>
                </defs>
                <circle cx="100" cy="100" r="80" fill="url(#cmt-coinGrad<?php echo esc_attr($index); ?>)" opacity="0.2"/>
                <circle cx="100" cy="100" r="75" fill="none" stroke="url(#cmt-coinGrad<?php echo esc_attr($index); ?>)" stroke-width="4"/>
                <path d="M 80 60 L 80 140 M 90 60 L 90 140" stroke="url(#cmt-coinGrad<?php echo esc_attr($index); ?>)" stroke-width="3" stroke-linecap="round"/>
                <path d="M 70 75 L 120 75 C 130 75 135 80 135 90 C 135 100 130 105 120 105 L 70 105 M 70 105 L 125 105 C 135 105 140 110 140 120 C 140 130 135 135 125 135 L 70 135" 
                      fill="none" stroke="url(#cmt-coinGrad<?php echo esc_attr($index); ?>)" stroke-width="4" stroke-linecap="round"/>
            </svg>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render the game
     */
    public function render_game($atts) {
        $atts = shortcode_atts(array('ad_code' => ''), $atts, 'crypto_miner_tycoon');
        
        $cloud_saves_enabled = get_option('cmt_enable_cloud_saves', false);
        $show_login_notice = $cloud_saves_enabled && !is_user_logged_in();
        $branding = CMT_Pro_Branding::get_branding_settings();
        $gameplay = CMT_Admin::get_gameplay_settings();
        
        $game_title    = 'Crypto Miner Tycoon';
        $game_subtitle = 'Click. Mine. Prosper.';
        if ( ! empty( $branding['enabled'] ) ) {
            if ( ! empty( $branding['gameTitle'] ) )    $game_title    = $branding['gameTitle'];
            if ( ! empty( $branding['gameSubtitle'] ) ) $game_subtitle = $branding['gameSubtitle'];
        }
        $currency_label = ! empty( $branding['enabled'] ) ? $branding['currencyLabel'] : 'Satoshis';
        
        ob_start();
        ?>
        
        <?php if ($show_login_notice): ?>
            <div class="cmt-login-notice">
                <p><strong>Note:</strong> Cloud saves are enabled. Please <a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>">log in</a> to save your progress.</p>
            </div>
        <?php endif; ?>
        
        <div class="cmt-container">
            <button class="cmt-info-button" onclick="cmtShowModal()">?</button>
            
            <header class="cmt-header">
                <h1 class="cmt-title"><?php echo esc_html( $game_title ); ?></h1>
                <?php if ( ! empty( $game_subtitle ) ) : ?>
                <div class="cmt-subtitle"><?php echo esc_html( $game_subtitle ); ?></div>
                <?php endif; ?>
            </header>

            <div class="cmt-main-game">
                <div class="cmt-game-area">
                    <div class="cmt-stats">
                        <div class="cmt-stat-item">
                            <span class="cmt-stat-label"><?php echo esc_html($currency_label); ?></span>
                            <span class="cmt-stat-value" id="cmt-satoshis">0</span>
                        </div>
                        <div class="cmt-stat-item">
                            <span class="cmt-stat-label"><?php echo esc_html( cmt_label( 'per_click', 'Per Click' ) ); ?></span>
                            <span class="cmt-stat-value" id="cmt-clickPower">1</span>
                        </div>
                        <div class="cmt-stat-item">
                            <span class="cmt-stat-label"><?php echo esc_html( cmt_label( 'per_second', 'Per Second' ) ); ?></span>
                            <span class="cmt-stat-value" id="cmt-passiveIncome">0</span>
                        </div>
                        <div class="cmt-stat-item">
                            <span class="cmt-stat-label"><?php echo esc_html( cmt_label( 'miner_rating', 'Miner Rating' ) ); ?></span>
                            <span class="cmt-stat-value" id="cmt-rating">1000</span>
                        </div>
                    </div>
                    
                    <!-- Difficulty Selector (New in 0.9.0) -->
                    <?php if ($gameplay['allowPlayerDifficulty']): ?>
                    <div class="cmt-difficulty-selector" id="cmt-difficultySelector">
                        <span class="cmt-difficulty-label"><?php echo esc_html( cmt_label( 'difficulty_label', 'Difficulty:' ) ); ?></span>
                        <div class="cmt-difficulty-buttons">
                            <button class="cmt-difficulty-btn" data-difficulty="easy" title="0.6x intensity - Easier upgrades"><?php echo esc_html( cmt_label( 'difficulty_easy', 'Easy' ) ); ?></button>
                            <button class="cmt-difficulty-btn" data-difficulty="medium" title="0.8x intensity - Balanced"><?php echo esc_html( cmt_label( 'difficulty_medium', 'Medium' ) ); ?></button>
                            <button class="cmt-difficulty-btn" data-difficulty="hard" title="1.0x intensity - Full challenge"><?php echo esc_html( cmt_label( 'difficulty_hard', 'Hard' ) ); ?></button>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Miner Status Indicator (New in 0.8.1) -->
                    <div class="cmt-miner-status" id="cmt-minerStatus">
                        <span class="cmt-miner-active">&#x26A1; Miners Active</span>
                    </div>

                    <!-- Multi-button click area (New in 0.8.0) -->
                    <div class="cmt-click-area" id="cmt-clickArea" data-button-mode="<?php echo esc_attr($gameplay['buttonMode']); ?>">
                        <?php
                        $button_mode = $gameplay['buttonMode'];
                        $buttons = array();
                        
                        for ($i = 0; $i < $button_mode; $i++) {
                            $buttons[] = $i;
                        }
                        
                        if ($button_mode > 1) {
                            shuffle($buttons);
                        }
                        
                        $real_index = $buttons[0];
                        for ($i = 0; $i < $button_mode; $i++) {
                            $is_real = ($i === 0);
                            echo $this->render_coin_button($is_real, $buttons[$i], $branding);
                        }
                        ?>
                    </div>
                    
                    <?php if ($button_mode > 1): ?>
                    <div class="cmt-click-hint" id="cmt-clickHint">
                        <span class="cmt-hint-icon">&#x1F4A1;</span> <?php echo esc_html( cmt_label( 'find_coin_prompt', 'Find the brighter coin to mine!' ) ); ?>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="cmt-upgrades">
                    <h2>Upgrades</h2>
                    <div id="cmt-upgradesList"></div>
                </div>
            </div>

            <div class="cmt-prestige-section">
                <div class="cmt-prestige-info">
                    <?php echo esc_html( cmt_label( 'hard_fork', 'Hard Fork' ) ); ?> available at 1,000,000 <?php echo esc_html( $currency_label ); ?><br>
                    <span style="font-size: 0.9rem; opacity: 0.7;"><?php echo esc_html( cmt_label( 'hard_fork_description', 'Reset with permanent +10% bonus to all production' ) ); ?></span>
                </div>
                <div class="cmt-prestige-buttons">
                    <button class="cmt-prestige-button" id="cmt-prestigeButton" onclick="cmtPrestige()" disabled>
                        <?php echo esc_html( mb_strtoupper( cmt_label( 'hard_fork', 'Hard Fork' ), 'UTF-8' ) ); ?>
                    </button>
                    <?php if ($gameplay['enableSelfReset']): ?>
                    <button class="cmt-reset-button" onclick="cmtSelfReset()">
                        &#x1F504; New Run
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <?php
            $ad_enabled = (bool) get_option( 'cmt_ad_enabled', false );
            $ad_html    = get_option( 'cmt_ad_html', '' );

            if ( $ad_enabled && ! empty( $ad_html ) ) :
            ?>
                <div class="cmt-ad-container">
                    <div class="cmt-ad-label">Advertisement</div>
                    <div class="cmt-ad-content"><?php echo wp_kses_post( $ad_html ); ?></div>
                </div>
            <?php endif; ?>

            <footer class="cmt-footer">
                <?php
                if ($branding['enabled'] && !empty($branding['footerText'])) {
                    $footer = str_replace(array('{year}', '{title}'), array(gmdate('Y'), $branding['gameTitle']), $branding['footerText']);
                    echo esc_html($footer);
                } else {
                    echo esc_html('Crypto Miner Tycoon ' . gmdate('Y') . ' | Game auto-saves every 10 seconds');
                }
                ?>
            </footer>
        </div>

        <div class="cmt-save-indicator" id="cmt-saveIndicator">Game Saved</div>

        <!-- Info Modal -->
        <div class="cmt-modal" id="cmt-infoModal">
            <div class="cmt-modal-content">
                <h2>How to Play</h2>
                <p><strong>Goal:</strong> Build the ultimate crypto mining empire!</p>
                <p><strong>Click the Bitcoin:</strong> Earn satoshis manually by clicking the glowing Bitcoin symbol.</p>
                <?php if ($button_mode > 1): ?>
                <p><strong>Find the Real Coin:</strong> Multiple coins appear - the real one glows brighter. Click the wrong one and it shakes!</p>
                <?php endif; ?>
                <p><strong>Buy Upgrades:</strong> Spend satoshis on upgrades to increase your mining power.</p>
                <p><strong>Hard Fork (Prestige):</strong> At 1,000,000 satoshis, reset with a permanent +10% production bonus.</p>
                <p><strong>&#x26A1; Miner Timeout:</strong> Your miners will stop after 48 hours of inactivity. Come back and click to restart them!</p>
                <?php if ($gameplay['allowPlayerDifficulty']): ?>
                <p><strong>&#x1F3AE; Difficulty:</strong> Choose Easy, Medium, or Hard. Changing difficulty resets your current run but keeps prestige!</p>
                <?php endif; ?>
                <?php if ($gameplay['enableSelfReset']): ?>
                <p><strong>New Run:</strong> Reset progress while keeping prestige level and best leaderboard score!</p>
                <?php endif; ?>
                <button class="cmt-close-modal" onclick="cmtHideModal()">Start Mining!</button>
            </div>
        </div>
        
        <!-- Self-Reset Confirmation Modal -->
        <?php if ($gameplay['enableSelfReset']): ?>
        <div class="cmt-modal" id="cmt-resetModal">
            <div class="cmt-modal-content cmt-reset-modal-content">
                <h2>&#x1F504; Start New Run?</h2>
                <p>This will reset your current progress but preserve your achievements:</p>
                <div class="cmt-reset-details">
                    <div class="cmt-reset-list">
                        <h4>&#x2705; Preserved:</h4>
                        <ul><li>Prestige Level &amp; Multiplier</li><li>Best Leaderboard Score</li></ul>
                    </div>
                    <div class="cmt-reset-list">
                        <h4>&#x274C; Reset:</h4>
                        <ul><li>Current Satoshis</li><li>All Upgrades</li><li>Miner Rating</li></ul>
                    </div>
                </div>
                <p class="cmt-reset-warning">&#x26A0;&#xFE0F; This action cannot be undone!</p>
                <div class="cmt-modal-buttons">
                    <button class="cmt-confirm-reset" onclick="cmtConfirmReset()">Start Fresh</button>
                    <button class="cmt-cancel-reset" onclick="cmtHideResetModal()">Cancel</button>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Difficulty Change Confirmation Modal (New in 0.9.0) -->
        <?php if ($gameplay['allowPlayerDifficulty']): ?>
        <div class="cmt-modal" id="cmt-difficultyModal">
            <div class="cmt-modal-content cmt-reset-modal-content">
                <h2>&#x1F3AE; Change Difficulty?</h2>
                <p>Changing difficulty will reset your current run:</p>
                <div class="cmt-difficulty-change-info">
                    <p><strong>New Difficulty:</strong> <span id="cmt-newDifficultyLabel">Medium</span></p>
                </div>
                <div class="cmt-reset-details">
                    <div class="cmt-reset-list">
                        <h4>&#x2705; Preserved:</h4>
                        <ul><li>Prestige Level &amp; Multiplier</li><li>Best Leaderboard Score</li></ul>
                    </div>
                    <div class="cmt-reset-list">
                        <h4>&#x274C; Reset:</h4>
                        <ul><li>Current Satoshis</li><li>All Upgrades</li><li>Miner Rating</li></ul>
                    </div>
                </div>
                <p class="cmt-reset-warning">&#x26A0;&#xFE0F; You'll compete on the <span id="cmt-newDifficultyLeaderboard">Medium</span> leaderboard!</p>
                <div class="cmt-modal-buttons">
                    <button class="cmt-confirm-reset" onclick="cmtConfirmDifficultyChange()">Change Difficulty</button>
                    <button class="cmt-cancel-reset" onclick="cmtHideDifficultyModal()">Cancel</button>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render leaderboard
     * Updated in 0.9.0: Tabbed interface for per-difficulty leaderboards
     */
    public function render_leaderboard($atts) {
        if (!get_option('cmt_enable_leaderboard', false)) {
            return '<p class="cmt-leaderboard-disabled">Leaderboard is not enabled.</p>';
        }
        
        $atts = shortcode_atts(array(
            'limit' => get_option('cmt_leaderboard_limit', 10),
            'difficulty' => '' // Allow forcing a specific difficulty
        ), $atts, 'crypto_miner_leaderboard');
        
        $branding = CMT_Pro_Branding::get_branding_settings();
        $currency_label = $branding['enabled'] ? $branding['currencyLabel'] : 'Satoshis';
        $allow_player_difficulty = get_option('cmt_allow_player_difficulty', false);
        
        $limit = intval($atts['limit']);
        
        ob_start();
        ?>
        <div class="cmt-leaderboard-container" data-allow-player-difficulty="<?php echo $allow_player_difficulty ? 'true' : 'false'; ?>">
            <h2 class="cmt-leaderboard-title">&#x1F3C6; Top Players</h2>
            
            <?php if ($allow_player_difficulty && empty($atts['difficulty'])): ?>
                <!-- Tabbed interface for difficulty selection -->
                <div class="cmt-leaderboard-tabs" id="cmt-leaderboardTabs">
                    <button class="cmt-leaderboard-tab" data-difficulty="easy">Easy</button>
                    <button class="cmt-leaderboard-tab active" data-difficulty="medium">Medium</button>
                    <button class="cmt-leaderboard-tab" data-difficulty="hard">Hard</button>
                </div>
                
                <!-- Leaderboard content containers for each difficulty -->
                <div class="cmt-leaderboard-content" id="cmt-leaderboardContent">
                    <?php
                    $difficulties = array('easy', 'medium', 'hard');
                    foreach ($difficulties as $diff):
                        $results = CMT_Cloud_Save::get_leaderboard_by_difficulty($diff, $limit);
                        $is_active = ($diff === 'medium'); // Default to medium tab
                    ?>
                    <div class="cmt-leaderboard-panel <?php echo $is_active ? 'active' : ''; ?>" data-difficulty="<?php echo esc_attr($diff); ?>">
                        <?php if (empty($results)): ?>
                            <p class="cmt-leaderboard-empty">No players on <?php echo esc_html(ucfirst($diff)); ?> difficulty yet. Be the first!</p>
                        <?php else: ?>
                            <?php echo $this->render_leaderboard_table($results, $currency_label); ?>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <script>
                // Simple tab switching for leaderboard
                document.addEventListener('DOMContentLoaded', function() {
                    var tabs = document.querySelectorAll('.cmt-leaderboard-tab');
                    var panels = document.querySelectorAll('.cmt-leaderboard-panel');
                    
                    tabs.forEach(function(tab) {
                        tab.addEventListener('click', function() {
                            var difficulty = this.getAttribute('data-difficulty');
                            
                            // Update active tab
                            tabs.forEach(function(t) { t.classList.remove('active'); });
                            this.classList.add('active');
                            
                            // Update active panel
                            panels.forEach(function(p) { p.classList.remove('active'); });
                            var targetPanel = document.querySelector('.cmt-leaderboard-panel[data-difficulty="' + difficulty + '"]');
                            if (targetPanel) targetPanel.classList.add('active');
                        });
                    });
                });
                </script>
                
            <?php else: ?>
                <!-- Single leaderboard (either forced difficulty or player difficulty disabled) -->
                <?php
                $forced_difficulty = !empty($atts['difficulty']) ? $atts['difficulty'] : null;
                $results = CMT_Cloud_Save::get_leaderboard_by_difficulty($forced_difficulty, $limit);
                ?>
                
                <?php if (empty($results)): ?>
                    <p class="cmt-leaderboard-empty">No players yet. Be the first!</p>
                <?php else: ?>
                    <?php echo $this->render_leaderboard_table($results, $currency_label); ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render leaderboard table HTML
     * New in 0.9.0: Extracted to helper method for reuse
     */
    private function render_leaderboard_table($results, $currency_label) {
        ob_start();
        ?>
        <table class="cmt-leaderboard-table">
            <thead>
                <tr>
                    <th class="cmt-rank">Rank</th>
                    <th class="cmt-player">Player</th>
                    <th class="cmt-satoshis"><?php echo esc_html($currency_label); ?></th>
                    <th class="cmt-prestige">Prestige</th>
                    <th class="cmt-score">Best Score</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $rank = 1;
                foreach ($results as $row): 
                    $rank_class = ($rank === 1) ? 'cmt-rank-1' : (($rank === 2) ? 'cmt-rank-2' : (($rank === 3) ? 'cmt-rank-3' : ''));
                    $is_current_user = is_user_logged_in() && get_current_user_id() == $row['user_id'];
                ?>
                <tr class="<?php echo esc_attr($rank_class); ?> <?php echo $is_current_user ? 'cmt-current-user' : ''; ?>">
                    <td class="cmt-rank">
                        <?php if ($rank <= 3): ?>
                            <span class="cmt-medal"><?php echo ($rank === 1) ? '&#x1F947;' : (($rank === 2) ? '&#x1F948;' : '&#x1F949;'); ?></span>
                        <?php else: ?>
                            <?php echo esc_html($rank); ?>
                        <?php endif; ?>
                    </td>
                    <td class="cmt-player">
                        <?php echo esc_html($row['display_name']); ?>
                        <?php if ($is_current_user): ?><span class="cmt-you-badge">You</span><?php endif; ?>
                    </td>
                    <td class="cmt-satoshis"><?php echo esc_html(number_format($row['total_satoshis'], 2)); ?></td>
                    <td class="cmt-prestige">Level <?php echo esc_html($row['prestige_level']); ?></td>
                    <td class="cmt-score"><?php echo esc_html(number_format($row['best_rank_score'], 0)); ?></td>
                </tr>
                <?php $rank++; endforeach; ?>
            </tbody>
        </table>
        <?php
        return ob_get_clean();
    }
}
