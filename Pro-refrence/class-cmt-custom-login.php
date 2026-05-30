<?php
/**
 * Custom Login Class
 * 
 * Handles custom login, registration, and authentication pages
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class CMT_Custom_Login {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Only init if custom login is enabled
        if (!get_option('cmt_enable_custom_login', false)) {
            return;
        }
        
        // Register shortcodes
        add_shortcode('cmt_login_form', array($this, 'render_login_form'));
        add_shortcode('cmt_register_form', array($this, 'render_register_form'));
        add_shortcode('cmt_forgot_password', array($this, 'render_forgot_password'));
        add_shortcode('cmt_logout_link', array($this, 'render_logout_link'));
        
        // Enqueue assets for login pages
        add_action('wp_enqueue_scripts', array($this, 'enqueue_login_assets'));
        
        // Handle form submissions
        add_action('init', array($this, 'handle_login_submission'));
        add_action('init', array($this, 'handle_register_submission'));
        add_action('init', array($this, 'handle_password_reset_submission'));
        
        // Redirect after login/logout
        add_filter('login_redirect', array($this, 'custom_login_redirect'), 10, 3);
        add_action('wp_logout', array($this, 'custom_logout_redirect'));

        // 🔐 SAFE WordPress login overrides (only when enabled + configured)
        add_filter('login_url', array($this, 'override_login_url'), 10, 2);
        add_action('init', array($this, 'block_wp_login'));
        
        // Hide admin bar for non-admins (optional)
        if (get_option('cmt_hide_admin_bar', false)) {
            add_action('after_setup_theme', array($this, 'hide_admin_bar'));
        }
    }

    /**
     * Override WordPress login URL when custom login page is configured
     */
    public function override_login_url($login_url, $redirect) {
        $custom_login = get_option('cmt_login_page');

        // Fail safely if admin did not configure a page
        if (empty($custom_login)) {
            return $login_url;
        }

        if (!empty($redirect)) {
            $custom_login = add_query_arg(
                'redirect_to',
                rawurlencode($redirect),
                $custom_login
            );
        }

        return $custom_login;
    }

    /**
     * Prevent direct access to wp-login.php when custom login page exists
     */
    public function block_wp_login() {
        global $pagenow;

        if ($pagenow !== 'wp-login.php' || is_user_logged_in()) {
            return;
        }

        $action = '';
        if (isset($_REQUEST['action'])) {
            $action = sanitize_key(wp_unslash($_REQUEST['action']));
        }

        if (in_array($action, array('rp', 'resetpass'), true)) {
            return;
        }

        $custom_login = get_option('cmt_login_page');

        // If no page configured, allow default WordPress login
        if (empty($custom_login)) {
            return;
        }

        wp_safe_redirect($custom_login);
        exit;
    }
    
    /**
     * Enqueue login page assets
     */
    public function enqueue_login_assets() {
        // Check if we're on a page with login shortcodes
        global $post;
        
        if (is_a($post, 'WP_Post') && (
            has_shortcode($post->post_content, 'cmt_login_form') ||
            has_shortcode($post->post_content, 'cmt_register_form') ||
            has_shortcode($post->post_content, 'cmt_forgot_password')
        )) {
            // Enqueue Google Fonts (same as game)
            wp_enqueue_style(
                'cmt-google-fonts',
                'https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@300;400;600&display=swap',
                array(),
                null
            );
            
            // Enqueue login CSS
            wp_enqueue_style(
                'cmt-login-css',
                CMT_PLUGIN_URL . 'assets/css/login.css',
                array(),
                CMT_VERSION
            );
            
            // Enqueue login JS
            wp_enqueue_script(
                'cmt-login-js',
                CMT_PLUGIN_URL . 'assets/js/login.js',
                array('jquery'),
                CMT_VERSION,
                true
            );
            
            // Pass branding settings
            $branding = CMT_Pro_Branding::get_branding_settings();
            wp_localize_script('cmt-login-js', 'cmtLogin', array(
                'branding' => $branding,
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('cmt_login_nonce')
            ));
        }
    }

    
    /**
     * Render login form
     */
    public function render_login_form($atts) {
        // If user is logged in, show logged-in message
        if (is_user_logged_in()) {
            return $this->render_logged_in_message();
        }
        
        $atts = shortcode_atts(array(
            'redirect' => get_option('cmt_login_redirect', home_url())
        ), $atts);
        
        ob_start();
        
        // Get branding settings
        $branding = CMT_Pro_Branding::get_branding_settings();
        $game_title = $branding['enabled'] ? $branding['gameTitle'] : 'Crypto Miner Tycoon';
        
        ?>
        <div class="cmt-login-container">
            <div class="cmt-login-box">
                <div class="cmt-login-header">
                    <?php if ($branding['enabled'] && !empty($branding['customCoin'])): ?>
                        <img src="<?php echo esc_url($branding['customCoin']); ?>" alt="Logo" class="cmt-login-logo">
                    <?php else: ?>
                        <div class="cmt-login-logo-default">
                            <svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg">
                                <defs>
                                    <linearGradient id="cmt-loginGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                        <stop offset="0%" style="stop-color:#00ffff;stop-opacity:1" />
                                        <stop offset="50%" style="stop-color:#ff00ff;stop-opacity:1" />
                                        <stop offset="100%" style="stop-color:#ffff00;stop-opacity:1" />
                                    </linearGradient>
                                </defs>
                                <circle cx="100" cy="100" r="75" fill="none" stroke="url(#cmt-loginGrad)" stroke-width="4"/>
                                <path d="M 80 60 L 80 140 M 90 60 L 90 140" stroke="url(#cmt-loginGrad)" stroke-width="3" stroke-linecap="round"/>
                                <path d="M 70 75 L 120 75 C 130 75 135 80 135 90 C 135 100 130 105 120 105 L 70 105 M 70 105 L 125 105 C 135 105 140 110 140 120 C 140 130 135 135 125 135 L 70 135" 
                                      fill="none" stroke="url(#cmt-loginGrad)" stroke-width="4" stroke-linecap="round"/>
                            </svg>
                        </div>
                    <?php endif; ?>
                    <h1 class="cmt-login-title"><?php echo esc_html($game_title); ?></h1>
                    <p class="cmt-login-subtitle">Sign in to continue</p>
                </div>
                
                <?php 
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only status messages from redirects
                if (isset($_GET['login']) && sanitize_text_field(wp_unslash($_GET['login'])) === 'failed'): 
                ?>
                    <div class="cmt-login-error">
                        <span class="cmt-error-icon">&#x26A0;&#xFE0F;</span>
                        <p>Invalid username or password. Please try again.</p>
                    </div>
                <?php endif; ?>
                
                <?php 
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only status messages from redirects
                if (isset($_GET['registered']) && sanitize_text_field(wp_unslash($_GET['registered'])) === 'success'): 
                ?>
                    <div class="cmt-login-success">
                        <span class="cmt-success-icon">&#x2713;</span>
                        <p>Registration successful! Please log in.</p>
                    </div>
                <?php endif; ?>
                
                <?php 
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only status messages from redirects
                if (isset($_GET['reset']) && sanitize_text_field(wp_unslash($_GET['reset'])) === 'success'): 
                ?>
                    <div class="cmt-login-success">
                        <span class="cmt-success-icon">&#x2713;</span>
                        <p>Password reset email sent! Check your inbox.</p>
                    </div>
                <?php endif; ?>
                
                <form method="post" action="" class="cmt-login-form" id="cmt-login-form">
                    <?php wp_nonce_field('cmt_login_action', 'cmt_login_nonce'); ?>
                    <input type="hidden" name="cmt_login_submit" value="1">
                    <input type="hidden" name="redirect_to" value="<?php echo esc_url($atts['redirect']); ?>">
                    
                    <div class="cmt-form-group">
                        <label for="cmt_username">Username or Email</label>
                        <input type="text" id="cmt_username" name="username" required 
                               placeholder="Enter your username or email" autocomplete="username">
                    </div>
                    
                    <div class="cmt-form-group">
                        <label for="cmt_password">Password</label>
                        <input type="password" id="cmt_password" name="password" required 
                               placeholder="Enter your password" autocomplete="current-password">
                    </div>
                    
                    <div class="cmt-form-row">
                        <label class="cmt-checkbox-label">
                            <input type="checkbox" name="remember" value="1" checked>
                            <span>Remember me</span>
                        </label>
                        <?php if (get_option('cmt_enable_password_reset', true)): ?>
                            <a href="<?php echo esc_url(get_option('cmt_forgot_password_page', '#')); ?>" class="cmt-forgot-link">
                                Forgot password?
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <button type="submit" class="cmt-login-button">
                        <span class="cmt-button-text">Sign In</span>
                        <span class="cmt-button-loader" style="display:none;">&#x27F3;</span>
                    </button>
                </form>
                
                <?php if (get_option('cmt_enable_registration', true)): ?>
                    <div class="cmt-login-footer">
                        <p>Don't have an account? 
                            <a href="<?php echo esc_url(get_option('cmt_register_page', wp_registration_url())); ?>" class="cmt-register-link">
                                Sign up
                            </a>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Render registration form
     */
    public function render_register_form($atts) {
        // If user is logged in, show logged-in message
        if (is_user_logged_in()) {
            return $this->render_logged_in_message();
        }
        
        // Check if registration is enabled
        if (!get_option('users_can_register')) {
            return '<p class="cmt-notice">Registration is currently disabled.</p>';
        }
        
        $atts = shortcode_atts(array(
            'redirect' => get_option('cmt_login_redirect', home_url())
        ), $atts);
        
        ob_start();
        
        $branding = CMT_Pro_Branding::get_branding_settings();
        $game_title = $branding['enabled'] ? $branding['gameTitle'] : 'Crypto Miner Tycoon';
        
        ?>
        <div class="cmt-login-container">
            <div class="cmt-login-box">
                <div class="cmt-login-header">
                    <?php if ($branding['enabled'] && !empty($branding['customCoin'])): ?>
                        <img src="<?php echo esc_url($branding['customCoin']); ?>" alt="Logo" class="cmt-login-logo">
                    <?php else: ?>
                        <div class="cmt-login-logo-default">
                            <svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg">
                                <defs>
                                    <linearGradient id="cmt-registerGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                        <stop offset="0%" style="stop-color:#00ffff;stop-opacity:1" />
                                        <stop offset="50%" style="stop-color:#ff00ff;stop-opacity:1" />
                                        <stop offset="100%" style="stop-color:#ffff00;stop-opacity:1" />
                                    </linearGradient>
                                </defs>
                                <circle cx="100" cy="100" r="75" fill="none" stroke="url(#cmt-registerGrad)" stroke-width="4"/>
                                <path d="M 80 60 L 80 140 M 90 60 L 90 140" stroke="url(#cmt-registerGrad)" stroke-width="3" stroke-linecap="round"/>
                                <path d="M 70 75 L 120 75 C 130 75 135 80 135 90 C 135 100 130 105 120 105 L 70 105 M 70 105 L 125 105 C 135 105 140 110 140 120 C 140 130 135 135 125 135 L 70 135" 
                                      fill="none" stroke="url(#cmt-registerGrad)" stroke-width="4" stroke-linecap="round"/>
                            </svg>
                        </div>
                    <?php endif; ?>
                    <h1 class="cmt-login-title"><?php echo esc_html($game_title); ?></h1>
                    <p class="cmt-login-subtitle">Create your account</p>
                </div>
                
                <?php 
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only error messages from redirects
                if (isset($_GET['error'])): 
                ?>
                    <div class="cmt-login-error">
                        <span class="cmt-error-icon">&#x26A0;&#xFE0F;</span>
                        <p><?php echo esc_html(sanitize_text_field(wp_unslash($_GET['error']))); ?></p>
                    </div>
                <?php endif; ?>
                
                <form method="post" action="" class="cmt-login-form" id="cmt-register-form">
                    <?php wp_nonce_field('cmt_register_action', 'cmt_register_nonce'); ?>
                    <input type="hidden" name="cmt_register_submit" value="1">
                    <input type="hidden" name="redirect_to" value="<?php echo esc_url($atts['redirect']); ?>">
                    
                    <div class="cmt-form-group">
                        <label for="cmt_reg_username">Username *</label>
                        <input type="text" id="cmt_reg_username" name="username" required 
                               placeholder="Choose a username" autocomplete="username"
                               pattern="[a-zA-Z0-9_-]+" title="Only letters, numbers, dashes and underscores allowed">
                        <small class="cmt-field-help">Only letters, numbers, dashes and underscores</small>
                    </div>
                    
                    <div class="cmt-form-group">
                        <label for="cmt_reg_email">Email *</label>
                        <input type="email" id="cmt_reg_email" name="email" required 
                               placeholder="Enter your email" autocomplete="email">
                    </div>
                    
                    <div class="cmt-form-group">
                        <label for="cmt_reg_password">Password *</label>
                        <input type="password" id="cmt_reg_password" name="password" required 
                               placeholder="Create a password" autocomplete="new-password"
                               minlength="8">
                        <small class="cmt-field-help">Minimum 8 characters</small>
                    </div>
                    
                    <div class="cmt-form-group">
                        <label for="cmt_reg_password_confirm">Confirm Password *</label>
                        <input type="password" id="cmt_reg_password_confirm" name="password_confirm" required 
                               placeholder="Confirm your password" autocomplete="new-password">
                    </div>
                    
                    <?php if (get_option('cmt_require_tos', false)): ?>
                        <div class="cmt-form-group">
                            <label class="cmt-checkbox-label">
                                <input type="checkbox" name="accept_tos" required>
                                <span>I agree to the 
                                    <a href="<?php echo esc_url(get_option('cmt_tos_page', '#')); ?>" target="_blank">
                                        Terms of Service
                                    </a>
                                </span>
                            </label>
                        </div>
                    <?php endif; ?>
                    
                    <button type="submit" class="cmt-login-button">
                        <span class="cmt-button-text">Create Account</span>
                        <span class="cmt-button-loader" style="display:none;">&#x27F3;</span>
                    </button>
                </form>
                
                <div class="cmt-login-footer">
                    <p>Already have an account? 
                        <a href="<?php echo esc_url(get_option('cmt_login_page', wp_login_url())); ?>" class="cmt-register-link">
                            Sign in
                        </a>
                    </p>
                </div>
            </div>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Render forgot password form
     */
    public function render_forgot_password($atts) {
        if (is_user_logged_in()) {
            return $this->render_logged_in_message();
        }
        
        ob_start();
        
        $branding = CMT_Pro_Branding::get_branding_settings();
        $game_title = $branding['enabled'] ? $branding['gameTitle'] : 'Crypto Miner Tycoon';
        
        ?>
        <div class="cmt-login-container">
            <div class="cmt-login-box">
                <div class="cmt-login-header">
                    <?php if ($branding['enabled'] && !empty($branding['customCoin'])): ?>
                        <img src="<?php echo esc_url($branding['customCoin']); ?>" alt="Logo" class="cmt-login-logo">
                    <?php else: ?>
                        <div class="cmt-login-logo-default">
                            <svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg">
                                <defs>
                                    <linearGradient id="cmt-resetGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                        <stop offset="0%" style="stop-color:#00ffff;stop-opacity:1" />
                                        <stop offset="50%" style="stop-color:#ff00ff;stop-opacity:1" />
                                        <stop offset="100%" style="stop-color:#ffff00;stop-opacity:1" />
                                    </linearGradient>
                                </defs>
                                <circle cx="100" cy="100" r="75" fill="none" stroke="url(#cmt-resetGrad)" stroke-width="4"/>
                                <path d="M 80 60 L 80 140 M 90 60 L 90 140" stroke="url(#cmt-resetGrad)" stroke-width="3" stroke-linecap="round"/>
                                <path d="M 70 75 L 120 75 C 130 75 135 80 135 90 C 135 100 130 105 120 105 L 70 105 M 70 105 L 125 105 C 135 105 140 110 140 120 C 140 130 135 135 125 135 L 70 135" 
                                      fill="none" stroke="url(#cmt-resetGrad)" stroke-width="4" stroke-linecap="round"/>
                            </svg>
                        </div>
                    <?php endif; ?>
                    <h1 class="cmt-login-title">Reset Password</h1>
                    <p class="cmt-login-subtitle">Enter your email to receive reset link</p>
                </div>
                
                <?php 
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only error messages from redirects
                if (isset($_GET['error'])): 
                ?>
                    <div class="cmt-login-error">
                        <span class="cmt-error-icon">&#x26A0;&#xFE0F;</span>
                        <p><?php echo esc_html(sanitize_text_field(wp_unslash($_GET['error']))); ?></p>
                    </div>
                <?php endif; ?>
                
                <form method="post" action="" class="cmt-login-form" id="cmt-reset-form">
                    <?php wp_nonce_field('cmt_reset_action', 'cmt_reset_nonce'); ?>
                    <input type="hidden" name="cmt_reset_submit" value="1">
                    
                    <div class="cmt-form-group">
                        <label for="cmt_reset_email">Email Address</label>
                        <input type="email" id="cmt_reset_email" name="user_login" required 
                               placeholder="Enter your email" autocomplete="email">
                    </div>
                    
                    <button type="submit" class="cmt-login-button">
                        <span class="cmt-button-text">Send Reset Link</span>
                        <span class="cmt-button-loader" style="display:none;">&#x27F3;</span>
                    </button>
                </form>
                
                <div class="cmt-login-footer">
                    <p>Remember your password? 
                        <a href="<?php echo esc_url(get_option('cmt_login_page', wp_login_url())); ?>" class="cmt-register-link">
                            Sign in
                        </a>
                    </p>
                </div>
            </div>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Render logout link
     */
    public function render_logout_link($atts) {
        if (!is_user_logged_in()) {
            return '';
        }
        
        $atts = shortcode_atts(array(
            'text' => 'Logout',
            'redirect' => home_url()
        ), $atts);
        
        return '<a href="' . esc_url(wp_logout_url($atts['redirect'])) . '" class="cmt-logout-link">' . 
               esc_html($atts['text']) . '</a>';
    }
    
    /**
     * Render logged-in message
     */
    private function render_logged_in_message() {
        $current_user = wp_get_current_user();
        $game_page = get_option('cmt_game_page', home_url());
        
        ob_start();
        ?>
        <div class="cmt-login-container">
            <div class="cmt-login-box">
                <div class="cmt-login-header">
                    <h2 class="cmt-login-title">Welcome Back!</h2>
                    <p class="cmt-login-subtitle">You're already logged in as <strong><?php echo esc_html($current_user->display_name); ?></strong></p>
                </div>
                <div class="cmt-logged-in-actions">
                    <a href="<?php echo esc_url($game_page); ?>" class="cmt-login-button">
                        Go to Game
                    </a>
                    <a href="<?php echo esc_url(wp_logout_url()); ?>" class="cmt-logout-button">
                        Logout
                    </a>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Handle login form submission
     */
    public function handle_login_submission() {
        if (!isset($_POST['cmt_login_submit'])) {
            return;
        }
        
        // Verify nonce
        if (!isset($_POST['cmt_login_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['cmt_login_nonce'])), 'cmt_login_action')) {
            return;
        }
        
        $username = isset($_POST['username']) ? sanitize_user(wp_unslash($_POST['username'])) : '';
        // Passwords should not be sanitized as they may contain special characters
        $password = isset($_POST['password']) ? wp_unslash($_POST['password']) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $remember = isset($_POST['remember']);
        $redirect = isset($_POST['redirect_to']) ? esc_url_raw(wp_unslash($_POST['redirect_to'])) : home_url();
        
        // Attempt login
        $creds = array(
            'user_login'    => $username,
            'user_password' => $password,
            'remember'      => $remember
        );
        
        $user = wp_signon($creds, is_ssl());
        
        if (is_wp_error($user)) {
            // Login failed - redirect back with error
            wp_safe_redirect(add_query_arg('login', 'failed', wp_get_referer()));
            exit;
        }
        
        // Success - redirect
        wp_safe_redirect($redirect);
        exit;
    }
    
    /**
     * Handle registration form submission
     */
    public function handle_register_submission() {
        if (!isset($_POST['cmt_register_submit'])) {
            return;
        }
        
        // Verify nonce
        if (!isset($_POST['cmt_register_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['cmt_register_nonce'])), 'cmt_register_action')) {
            return;
        }
        
        // Check if registration is enabled
        if (!get_option('users_can_register')) {
            wp_safe_redirect(add_query_arg('error', urlencode('Registration is disabled'), wp_get_referer()));
            exit;
        }
        
        $username = isset($_POST['username']) ? sanitize_user(wp_unslash($_POST['username'])) : '';
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        // Passwords should not be sanitized as they may contain special characters
        $password = isset($_POST['password']) ? wp_unslash($_POST['password']) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $password_confirm = isset($_POST['password_confirm']) ? wp_unslash($_POST['password_confirm']) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        
        // Validate
        if ($password !== $password_confirm) {
            wp_safe_redirect(add_query_arg('error', urlencode('Passwords do not match'), wp_get_referer()));
            exit;
        }
        
        if (strlen($password) < 8) {
            wp_safe_redirect(add_query_arg('error', urlencode('Password must be at least 8 characters'), wp_get_referer()));
            exit;
        }
        
        // Check TOS if required
        if (get_option('cmt_require_tos', false) && !isset($_POST['accept_tos'])) {
            wp_safe_redirect(add_query_arg('error', urlencode('You must accept the Terms of Service'), wp_get_referer()));
            exit;
        }
        
        // Create user
        $user_id = wp_create_user($username, $password, $email);
        
        if (is_wp_error($user_id)) {
            wp_safe_redirect(add_query_arg('error', urlencode($user_id->get_error_message()), wp_get_referer()));
            exit;
        }
        
        // 🎉 Send welcome email to user
        $user = get_user_by('id', $user_id);
        
        $site_name = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
        $login_url = get_option('cmt_login_page', wp_login_url());
        
        $subject = sprintf('Welcome to %s!', $site_name);
        
        $message  = "Hi {$user->display_name},\n\n";
        $message .= "Welcome to {$site_name}! Your account has been created successfully.\n\n";
        $message .= "You can log in here:\n{$login_url}\n\n";
        $message .= "If you have any issues, just reset your password anytime.\n\n";
        $message .= "See you inside!\n";
        $message .= "{$site_name} Team";
        
        wp_mail(
            $user->user_email,
            $subject,
            $message
        );
        
        // Success - redirect to login page
        $login_page = get_option('cmt_login_page', wp_login_url());
        wp_safe_redirect(add_query_arg('registered', 'success', $login_page));
        exit;
    }
    
    /**
     * Handle password reset form submission
     */
    public function handle_password_reset_submission() {
        if (!isset($_POST['cmt_reset_submit'])) {
            return;
        }
        
        // Verify nonce
        if (!isset($_POST['cmt_reset_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['cmt_reset_nonce'])), 'cmt_reset_action')) {
            return;
        }
        
        $user_login = isset($_POST['user_login']) ? sanitize_text_field(wp_unslash($_POST['user_login'])) : '';
        
        // Set the user_login for retrieve_password()
        $_POST['user_login'] = $user_login;

        add_filter('retrieve_password_message', array($this, 'filter_retrieve_password_message'), 10, 4);
        add_filter('retrieve_password_title', array($this, 'filter_retrieve_password_title'), 10, 3);
        add_filter('wp_mail_content_type', array($this, 'filter_reset_mail_content_type'));

        // Use WordPress core password reset
        $result = retrieve_password();

        remove_filter('retrieve_password_message', array($this, 'filter_retrieve_password_message'), 10);
        remove_filter('retrieve_password_title', array($this, 'filter_retrieve_password_title'), 10);
        remove_filter('wp_mail_content_type', array($this, 'filter_reset_mail_content_type'));
        
        if (is_wp_error($result)) {
            wp_safe_redirect(add_query_arg('error', urlencode($result->get_error_message()), wp_get_referer()));
            exit;
        }
        
        // Success
        $login_page = get_option('cmt_login_page', wp_login_url());
        wp_safe_redirect(add_query_arg('reset', 'success', $login_page));
        exit;
    }
    
    /**
     * Custom login redirect
     */
    public function custom_login_redirect($redirect_to, $request, $user) {
        $custom_redirect = get_option('cmt_login_redirect', '');
        
        if (!empty($custom_redirect) && !is_wp_error($user)) {
            return $custom_redirect;
        }
        
        return $redirect_to;
    }
    
    /**
     * Custom logout redirect
     */
    public function custom_logout_redirect() {
        $custom_redirect = get_option('cmt_logout_redirect', home_url());
        wp_safe_redirect($custom_redirect);
        exit;
    }
    
    /**
     * Hide admin bar for non-admins
     */
    public function hide_admin_bar() {
        if (!current_user_can('manage_options')) {
            show_admin_bar(false);
        }
    }

    /**
     * Filter reset email subject.
     */
    public function filter_retrieve_password_title($title, $user_login, $user_data) {
        return 'Reset your password';
    }

    /**
     * Filter reset email message with branded HTML.
     */
    public function filter_retrieve_password_message($message, $key, $user_login, $user_data) {
        $reset_url = $this->extract_reset_url($message);

        if (empty($reset_url)) {
            return $message;
        }

        return $this->build_reset_html_email($reset_url);
    }

    /**
     * Force HTML content type for reset email only.
     */
    public function filter_reset_mail_content_type() {
        return 'text/html';
    }

    /**
     * Extract the reset URL from the default WordPress message.
     */
    private function extract_reset_url($message) {
        $matches = array();
        if (preg_match('/https?:\/\/\S+/i', $message, $matches)) {
            return trim($matches[0]);
        }

        return '';
    }

    /**
     * Build a branded HTML email with fallback instructions.
     */
    private function build_reset_html_email($reset_url) {
        $branding = CMT_Pro_Branding::get_branding_settings();
        $site_name = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
        $game_title = $branding['enabled'] ? $branding['gameTitle'] : $site_name;
        $logo_url = ($branding['enabled'] && !empty($branding['customCoin'])) ? $branding['customCoin'] : '';

        $headline = 'Password Reset Request';
        $button_text = 'Reset Password';
        $instructions = 'If the button does not work, copy and paste the link below into your browser.';
        $body_lines = array(
            'We received a request to reset your password.',
            'Click the button below to reset your password.',
            $instructions,
            'If you did not request this, you can safely ignore this email.',
        );

        $escaped_url = esc_url($reset_url);
        $escaped_game_title = esc_html($game_title);
        $escaped_headline = esc_html($headline);
        $escaped_button_text = esc_html($button_text);
        $escaped_instructions = esc_html($instructions);
        $escaped_body_lines = array_map('esc_html', $body_lines);
        $escaped_logo_url = esc_url($logo_url);

        ob_start();
        ?>
        <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background-color:#0b0f1a;padding:24px 0;font-family:Arial,sans-serif;">
            <tr>
                <td align="center">
                    <table role="presentation" cellpadding="0" cellspacing="0" width="600" style="background-color:#111827;border-radius:12px;overflow:hidden;">
                        <tr>
                            <td style="padding:32px 32px 16px;text-align:center;">
                                <?php if (!empty($escaped_logo_url)) : ?>
                                    <img src="<?php echo $escaped_logo_url; ?>" alt="<?php echo $escaped_game_title; ?>" width="72" height="72" style="display:block;margin:0 auto 12px;border-radius:12px;">
                                <?php endif; ?>
                                <h1 style="margin:0;color:#ffffff;font-size:24px;line-height:32px;"><?php echo $escaped_game_title; ?></h1>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:0 32px 16px;color:#e5e7eb;">
                                <h2 style="margin:0 0 12px;color:#ffffff;font-size:20px;"><?php echo $escaped_headline; ?></h2>
                                <p style="margin:0 0 12px;"><?php echo $escaped_body_lines[0]; ?></p>
                                <p style="margin:0 0 20px;"><?php echo $escaped_body_lines[1]; ?></p>
                                <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 20px;">
                                    <tr>
                                        <td style="border-radius:6px;background-color:#22c55e;">
                                            <a href="<?php echo $escaped_url; ?>" style="display:inline-block;padding:12px 24px;color:#0b0f1a;font-weight:bold;text-decoration:none;"><?php echo $escaped_button_text; ?></a>
                                        </td>
                                    </tr>
                                </table>
                                <p style="margin:0 0 8px;"><?php echo $escaped_instructions; ?></p>
                                <p style="margin:0 0 16px;word-break:break-all;color:#93c5fd;"><?php echo esc_html($reset_url); ?></p>
                                <p style="margin:0;"><?php echo $escaped_body_lines[3]; ?></p>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:0 32px 24px;color:#9ca3af;font-size:12px;text-align:center;">
                                <?php echo esc_html($site_name); ?>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
        <?php
        return trim(ob_get_clean());
    }
}
