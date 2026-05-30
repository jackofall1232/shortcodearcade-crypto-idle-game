<?php
/**
 * Login Pages Class
 *
 * Provides custom login, registration, and password-reset settings plus the
 * front-end shortcodes that render branded authentication forms.
 *
 * @package Shortcode_Arcade_Crypto_Idle_Game
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SACIG_Login_Pages {

	/**
	 * Auth error message for the current request.
	 *
	 * Stored on the instance (not a transient) so it is request-scoped and
	 * never leaks to other users on a concurrent request.
	 *
	 * @var string
	 */
	private $auth_error = '';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_shortcode( 'sacig_login_form', array( $this, 'render_login_form' ) );
		add_shortcode( 'sacig_register_form', array( $this, 'render_register_form' ) );
		add_shortcode( 'sacig_forgot_password', array( $this, 'render_forgot_password_form' ) );
		add_shortcode( 'sacig_logout_link', array( $this, 'render_logout_link' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'init', array( $this, 'handle_login_submission' ) );
		add_action( 'init', array( $this, 'handle_register_submission' ) );
		add_action( 'init', array( $this, 'maybe_redirect_login' ) );
		add_filter( 'show_admin_bar', array( $this, 'maybe_hide_admin_bar' ) );
	}

	/**
	 * Hide the admin bar for non-admin users when the option is enabled.
	 *
	 * @param bool $show Whether to show the admin bar.
	 * @return bool
	 */
	public function maybe_hide_admin_bar( $show ) {
		if ( get_option( 'sacig_hide_admin_bar', false ) && ! current_user_can( 'manage_options' ) ) {
			return false;
		}
		return $show;
	}

	/**
	 * Register login settings, section, and fields.
	 */
	public function register_settings() {
		register_setting(
			'sacig_login_group',
			'sacig_enable_custom_login',
			array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
			)
		);
		register_setting(
			'sacig_login_group',
			'sacig_login_page',
			array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'esc_url_raw',
			)
		);
		register_setting(
			'sacig_login_group',
			'sacig_register_page',
			array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'esc_url_raw',
			)
		);
		register_setting(
			'sacig_login_group',
			'sacig_forgot_password_page',
			array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'esc_url_raw',
			)
		);
		register_setting(
			'sacig_login_group',
			'sacig_game_page',
			array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'esc_url_raw',
			)
		);
		register_setting(
			'sacig_login_group',
			'sacig_login_redirect',
			array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'esc_url_raw',
			)
		);
		register_setting(
			'sacig_login_group',
			'sacig_logout_redirect',
			array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'esc_url_raw',
			)
		);
		register_setting(
			'sacig_login_group',
			'sacig_enable_registration',
			array(
				'type'              => 'boolean',
				'default'           => true,
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
			)
		);
		register_setting(
			'sacig_login_group',
			'sacig_enable_password_reset',
			array(
				'type'              => 'boolean',
				'default'           => true,
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
			)
		);
		register_setting(
			'sacig_login_group',
			'sacig_hide_admin_bar',
			array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
			)
		);

		add_settings_section(
			'sacig_login_section',
			__( 'Login Page Settings', 'shortcodearcade-crypto-idle-game' ),
			array( $this, 'render_section_description' ),
			'shortcodearcade-crypto-idle-game-login'
		);

		add_settings_field( 'sacig_enable_custom_login', __( 'Custom Login System', 'shortcodearcade-crypto-idle-game' ), array( $this, 'render_enable_custom_login_field' ), 'shortcodearcade-crypto-idle-game-login', 'sacig_login_section' );
		add_settings_field( 'sacig_login_page', __( 'Login Page URL', 'shortcodearcade-crypto-idle-game' ), array( $this, 'render_login_page_field' ), 'shortcodearcade-crypto-idle-game-login', 'sacig_login_section' );
		add_settings_field( 'sacig_register_page', __( 'Register Page URL', 'shortcodearcade-crypto-idle-game' ), array( $this, 'render_register_page_field' ), 'shortcodearcade-crypto-idle-game-login', 'sacig_login_section' );
		add_settings_field( 'sacig_forgot_password_page', __( 'Forgot Password Page URL', 'shortcodearcade-crypto-idle-game' ), array( $this, 'render_forgot_password_page_field' ), 'shortcodearcade-crypto-idle-game-login', 'sacig_login_section' );
		add_settings_field( 'sacig_game_page', __( 'Game Page URL', 'shortcodearcade-crypto-idle-game' ), array( $this, 'render_game_page_field' ), 'shortcodearcade-crypto-idle-game-login', 'sacig_login_section' );
		add_settings_field( 'sacig_login_redirect', __( 'After Login Redirect', 'shortcodearcade-crypto-idle-game' ), array( $this, 'render_login_redirect_field' ), 'shortcodearcade-crypto-idle-game-login', 'sacig_login_section' );
		add_settings_field( 'sacig_logout_redirect', __( 'After Logout Redirect', 'shortcodearcade-crypto-idle-game' ), array( $this, 'render_logout_redirect_field' ), 'shortcodearcade-crypto-idle-game-login', 'sacig_login_section' );
		add_settings_field( 'sacig_enable_registration', __( 'Allow Registration', 'shortcodearcade-crypto-idle-game' ), array( $this, 'render_enable_registration_field' ), 'shortcodearcade-crypto-idle-game-login', 'sacig_login_section' );
		add_settings_field( 'sacig_enable_password_reset', __( 'Allow Password Reset', 'shortcodearcade-crypto-idle-game' ), array( $this, 'render_enable_password_reset_field' ), 'shortcodearcade-crypto-idle-game-login', 'sacig_login_section' );
		add_settings_field( 'sacig_hide_admin_bar', __( 'Hide Admin Bar', 'shortcodearcade-crypto-idle-game' ), array( $this, 'render_hide_admin_bar_field' ), 'shortcodearcade-crypto-idle-game-login', 'sacig_login_section' );
	}

	/**
	 * Render the settings section description.
	 */
	public function render_section_description() {
		echo '<p>' . esc_html__( 'Configure custom login, registration, and authentication pages.', 'shortcodearcade-crypto-idle-game' ) . '</p>';
	}

	/**
	 * Render the login settings page.
	 */
	public function render_settings_page() {
		?>
		<div class="wrap sacig-arcade-wrap">
			<div class="sacig-arcade-header">
				<div class="sacig-arcade-logo">&#x20BF;</div>
				<h1 class="sacig-arcade-title"><?php esc_html_e( 'Login Pages', 'shortcodearcade-crypto-idle-game' ); ?></h1>
				<p class="sacig-arcade-subtitle"><?php esc_html_e( 'Branded login and registration for your players', 'shortcodearcade-crypto-idle-game' ); ?></p>
			</div>
			<div class="sacig-admin-container">
				<div class="sacig-admin-main">
					<div class="sacig-arcade-card">
						<form action="options.php" method="post">
							<?php
							settings_fields( 'sacig_login_group' );
							do_settings_sections( 'shortcodearcade-crypto-idle-game-login' );
							submit_button( __( 'Save Login Settings', 'shortcodearcade-crypto-idle-game' ) );
							?>
						</form>
					</div>

					<div class="sacig-info-box">
						<h3>&#x1F4CB; <?php esc_html_e( 'Available Shortcodes', 'shortcodearcade-crypto-idle-game' ); ?></h3>
						<table class="widefat striped">
							<thead>
								<tr><th><?php esc_html_e( 'Shortcode', 'shortcodearcade-crypto-idle-game' ); ?></th><th><?php esc_html_e( 'Description', 'shortcodearcade-crypto-idle-game' ); ?></th></tr>
							</thead>
							<tbody>
								<tr><td><code>[sacig_login_form]</code></td><td><?php esc_html_e( 'Display login form', 'shortcodearcade-crypto-idle-game' ); ?></td></tr>
								<tr><td><code>[sacig_register_form]</code></td><td><?php esc_html_e( 'Display registration form', 'shortcodearcade-crypto-idle-game' ); ?></td></tr>
								<tr><td><code>[sacig_forgot_password]</code></td><td><?php esc_html_e( 'Display password reset form', 'shortcodearcade-crypto-idle-game' ); ?></td></tr>
								<tr><td><code>[sacig_logout_link]</code></td><td><?php esc_html_e( 'Display logout link', 'shortcodearcade-crypto-idle-game' ); ?></td></tr>
							</tbody>
						</table>
					</div>

					<div class="sacig-info-box">
						<h3>&#x1F680; <?php esc_html_e( 'Quick Setup Guide', 'shortcodearcade-crypto-idle-game' ); ?></h3>
						<ol>
							<li><?php esc_html_e( 'Create pages for Login, Register, Forgot Password', 'shortcodearcade-crypto-idle-game' ); ?></li>
							<li><?php esc_html_e( 'Add the respective shortcode to each page', 'shortcodearcade-crypto-idle-game' ); ?></li>
							<li><?php esc_html_e( 'Enter the page URLs in the fields above', 'shortcodearcade-crypto-idle-game' ); ?></li>
							<li><?php esc_html_e( 'Enable "Custom Login System"', 'shortcodearcade-crypto-idle-game' ); ?></li>
							<li><?php esc_html_e( 'Enable user registration: Settings > General > Membership > "Anyone can register"', 'shortcodearcade-crypto-idle-game' ); ?></li>
						</ol>
					</div>
				</div>

				<div class="sacig-admin-sidebar">
					<div class="sacig-sidebar-box">
						<h3>&#x1F3A8; <?php esc_html_e( 'Branded Experience', 'shortcodearcade-crypto-idle-game' ); ?></h3>
						<p><?php esc_html_e( 'Custom login pages match your game aesthetic for a seamless player experience.', 'shortcodearcade-crypto-idle-game' ); ?></p>
					</div>
					<div class="sacig-sidebar-box">
						<h3>&#x2699;&#xFE0F; <?php esc_html_e( 'WordPress Settings', 'shortcodearcade-crypto-idle-game' ); ?></h3>
						<p><?php esc_html_e( 'Remember to enable user registration under Settings > General > Membership > "Anyone can register".', 'shortcodearcade-crypto-idle-game' ); ?></p>
					</div>
					<div class="sacig-sidebar-box">
						<h3>&#x1F4A1; <?php esc_html_e( 'Pro Tips', 'shortcodearcade-crypto-idle-game' ); ?></h3>
						<ul>
							<li><?php esc_html_e( 'Use custom URLs like /login instead of /wp-login.php', 'shortcodearcade-crypto-idle-game' ); ?></li>
							<li><?php esc_html_e( 'Set redirect to game page for better player flow', 'shortcodearcade-crypto-idle-game' ); ?></li>
							<li><?php esc_html_e( 'Hide admin bar for non-admin users', 'shortcodearcade-crypto-idle-game' ); ?></li>
						</ul>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Settings field renderers
	 * ------------------------------------------------------------------- */

	/**
	 * Render a boolean checkbox field.
	 *
	 * @param string $option  Option name.
	 * @param bool   $default Default value.
	 * @param string $label   Label text.
	 * @param string $desc    Optional description.
	 */
	private function render_checkbox( $option, $default, $label, $desc = '' ) {
		$value = (bool) get_option( $option, $default );
		?>
		<label>
			<input type="hidden" name="<?php echo esc_attr( $option ); ?>" value="0">
			<input type="checkbox" name="<?php echo esc_attr( $option ); ?>" value="1" <?php checked( $value, true ); ?>>
			<?php echo esc_html( $label ); ?>
		</label>
		<?php if ( $desc ) : ?>
			<p class="description"><?php echo esc_html( $desc ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render a URL field.
	 *
	 * @param string $option      Option name.
	 * @param string $placeholder Placeholder text.
	 * @param string $desc        Description text.
	 */
	private function render_url_field( $option, $placeholder, $desc ) {
		$value = get_option( $option, '' );
		?>
		<input type="url" id="<?php echo esc_attr( $option ); ?>" name="<?php echo esc_attr( $option ); ?>" class="regular-text" value="<?php echo esc_url( $value ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>">
		<p class="description"><?php echo esc_html( $desc ); ?></p>
		<?php
	}

	public function render_enable_custom_login_field() {
		$this->render_checkbox( 'sacig_enable_custom_login', false, __( 'Enable the custom login system', 'shortcodearcade-crypto-idle-game' ), __( 'Redirects the default WordPress login to your custom login page.', 'shortcodearcade-crypto-idle-game' ) );
	}
	public function render_login_page_field() {
		$this->render_url_field( 'sacig_login_page', 'https://example.com/login', __( 'Page containing the [sacig_login_form] shortcode.', 'shortcodearcade-crypto-idle-game' ) );
	}
	public function render_register_page_field() {
		$this->render_url_field( 'sacig_register_page', 'https://example.com/register', __( 'Page containing the [sacig_register_form] shortcode.', 'shortcodearcade-crypto-idle-game' ) );
	}
	public function render_forgot_password_page_field() {
		$this->render_url_field( 'sacig_forgot_password_page', 'https://example.com/forgot-password', __( 'Page containing the [sacig_forgot_password] shortcode.', 'shortcodearcade-crypto-idle-game' ) );
	}
	public function render_game_page_field() {
		$this->render_url_field( 'sacig_game_page', 'https://example.com/play', __( 'Page containing the game shortcode.', 'shortcodearcade-crypto-idle-game' ) );
	}
	public function render_login_redirect_field() {
		$this->render_url_field( 'sacig_login_redirect', 'https://example.com/play', __( 'Where players go after logging in. Leave blank for the WordPress default.', 'shortcodearcade-crypto-idle-game' ) );
	}
	public function render_logout_redirect_field() {
		$this->render_url_field( 'sacig_logout_redirect', 'https://example.com/', __( 'Where players go after logging out. Leave blank for the home page.', 'shortcodearcade-crypto-idle-game' ) );
	}
	public function render_enable_registration_field() {
		$this->render_checkbox( 'sacig_enable_registration', true, __( 'Show a registration link on the login form', 'shortcodearcade-crypto-idle-game' ) );
	}
	public function render_enable_password_reset_field() {
		$this->render_checkbox( 'sacig_enable_password_reset', true, __( 'Show a password reset link on the login form', 'shortcodearcade-crypto-idle-game' ) );
	}
	public function render_hide_admin_bar_field() {
		$this->render_checkbox( 'sacig_hide_admin_bar', false, __( 'Hide the admin bar for non-admin users', 'shortcodearcade-crypto-idle-game' ) );
	}

	/* ---------------------------------------------------------------------
	 * Shortcode renderers
	 * ------------------------------------------------------------------- */

	/**
	 * Render the login form shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_login_form( $atts ) {
		$game_page = get_option( 'sacig_game_page', '' );
		ob_start();

		if ( is_user_logged_in() ) {
			$current = wp_get_current_user();
			?>
			<div class="sacig-login-container">
				<div class="sacig-login-box">
					<div class="sacig-login-header">
						<h2 class="sacig-login-title"><?php echo esc_html( sprintf( /* translators: %s: display name */ __( 'Welcome back, %s', 'shortcodearcade-crypto-idle-game' ), $current->display_name ) ); ?></h2>
					</div>
					<p>
						<?php if ( $game_page ) : ?>
							<a href="<?php echo esc_url( $game_page ); ?>" class="sacig-login-button"><?php esc_html_e( 'Go to Game', 'shortcodearcade-crypto-idle-game' ); ?></a>
						<?php endif; ?>
						<a href="<?php echo esc_url( wp_logout_url( $this->logout_redirect() ) ); ?>" class="sacig-login-button"><?php esc_html_e( 'Logout', 'shortcodearcade-crypto-idle-game' ); ?></a>
					</p>
				</div>
			</div>
			<?php
			return ob_get_clean();
		}

		$this->maybe_render_error();
		$register_page = get_option( 'sacig_register_page', '' );
		$forgot_page   = get_option( 'sacig_forgot_password_page', '' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only prefill of the redirect target.
		$redirect_to = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : $this->login_redirect();
		?>
		<div class="sacig-login-container">
			<div class="sacig-login-box">
				<div class="sacig-login-header">
					<h2 class="sacig-login-title"><?php esc_html_e( 'Log In', 'shortcodearcade-crypto-idle-game' ); ?></h2>
				</div>
				<form class="sacig-login-form" method="post" action="">
					<?php wp_nonce_field( 'sacig_login_action', 'sacig_login_nonce' ); ?>
					<div class="sacig-form-group">
						<label for="sacig_login_username"><?php esc_html_e( 'Username or Email', 'shortcodearcade-crypto-idle-game' ); ?></label>
						<input type="text" id="sacig_login_username" name="sacig_username" required>
					</div>
					<div class="sacig-form-group">
						<label for="sacig_login_password"><?php esc_html_e( 'Password', 'shortcodearcade-crypto-idle-game' ); ?></label>
						<input type="password" id="sacig_login_password" name="sacig_password" required>
					</div>
					<div class="sacig-form-group">
						<label><input type="checkbox" name="sacig_remember" value="1"> <?php esc_html_e( 'Remember me', 'shortcodearcade-crypto-idle-game' ); ?></label>
					</div>
					<input type="hidden" name="redirect_to" value="<?php echo esc_url( $redirect_to ); ?>">
					<button type="submit" name="sacig_login_submit" class="sacig-login-button"><?php esc_html_e( 'Log In', 'shortcodearcade-crypto-idle-game' ); ?></button>
				</form>
				<p class="sacig-login-links">
					<?php if ( get_option( 'sacig_enable_registration', true ) && $register_page ) : ?>
						<a href="<?php echo esc_url( $register_page ); ?>"><?php esc_html_e( 'Create an account', 'shortcodearcade-crypto-idle-game' ); ?></a>
					<?php endif; ?>
					<?php if ( get_option( 'sacig_enable_password_reset', true ) ) : ?>
						<a href="<?php echo esc_url( $forgot_page ? $forgot_page : wp_lostpassword_url() ); ?>"><?php esc_html_e( 'Forgot password?', 'shortcodearcade-crypto-idle-game' ); ?></a>
					<?php endif; ?>
				</p>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render the registration form shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_register_form( $atts ) {
		ob_start();

		if ( is_user_logged_in() ) {
			echo '<div class="sacig-login-container"><div class="sacig-login-box"><p>' . esc_html__( 'You are already logged in.', 'shortcodearcade-crypto-idle-game' ) . '</p></div></div>';
			return ob_get_clean();
		}

		if ( ! get_option( 'users_can_register' ) || ! get_option( 'sacig_enable_registration', true ) ) {
			echo '<div class="sacig-login-container"><div class="sacig-login-box"><p>' . esc_html__( 'Registration is currently disabled.', 'shortcodearcade-crypto-idle-game' ) . '</p></div></div>';
			return ob_get_clean();
		}

		$this->maybe_render_error();
		$login_page = get_option( 'sacig_login_page', '' );
		?>
		<div class="sacig-login-container">
			<div class="sacig-login-box">
				<div class="sacig-login-header">
					<h2 class="sacig-login-title"><?php esc_html_e( 'Create an Account', 'shortcodearcade-crypto-idle-game' ); ?></h2>
				</div>
				<form class="sacig-login-form" method="post" action="">
					<?php wp_nonce_field( 'sacig_register_action', 'sacig_register_nonce' ); ?>
					<div class="sacig-form-group">
						<label for="sacig_register_username"><?php esc_html_e( 'Username', 'shortcodearcade-crypto-idle-game' ); ?></label>
						<input type="text" id="sacig_register_username" name="sacig_reg_username" required>
					</div>
					<div class="sacig-form-group">
						<label for="sacig_register_email"><?php esc_html_e( 'Email', 'shortcodearcade-crypto-idle-game' ); ?></label>
						<input type="email" id="sacig_register_email" name="sacig_reg_email" required>
					</div>
					<div class="sacig-form-group">
						<label for="sacig_register_password"><?php esc_html_e( 'Password', 'shortcodearcade-crypto-idle-game' ); ?></label>
						<input type="password" id="sacig_register_password" name="sacig_reg_password" required>
					</div>
					<div class="sacig-form-group">
						<label for="sacig_register_password2"><?php esc_html_e( 'Confirm Password', 'shortcodearcade-crypto-idle-game' ); ?></label>
						<input type="password" id="sacig_register_password2" name="sacig_reg_password2" required>
					</div>
					<button type="submit" name="sacig_register_submit" class="sacig-login-button"><?php esc_html_e( 'Register', 'shortcodearcade-crypto-idle-game' ); ?></button>
				</form>
				<?php if ( $login_page ) : ?>
					<p class="sacig-login-links"><a href="<?php echo esc_url( $login_page ); ?>"><?php esc_html_e( 'Already have an account? Log in', 'shortcodearcade-crypto-idle-game' ); ?></a></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render the forgot-password form shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_forgot_password_form( $atts ) {
		ob_start();
		?>
		<div class="sacig-login-container">
			<div class="sacig-login-box">
				<div class="sacig-login-header">
					<h2 class="sacig-login-title"><?php esc_html_e( 'Reset Password', 'shortcodearcade-crypto-idle-game' ); ?></h2>
				</div>
				<form class="sacig-login-form" method="post" action="<?php echo esc_url( wp_lostpassword_url() ); ?>">
					<?php wp_nonce_field( 'sacig_forgot_password_action', 'sacig_forgot_password_nonce' ); ?>
					<div class="sacig-form-group">
						<label for="sacig_forgot_login"><?php esc_html_e( 'Username or Email', 'shortcodearcade-crypto-idle-game' ); ?></label>
						<input type="text" id="sacig_forgot_login" name="user_login" required>
					</div>
					<button type="submit" class="sacig-login-button"><?php esc_html_e( 'Get New Password', 'shortcodearcade-crypto-idle-game' ); ?></button>
				</form>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render the logout link shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_logout_link( $atts ) {
		$atts = shortcode_atts(
			array(
				'redirect' => home_url(),
				'text'     => __( 'Logout', 'shortcodearcade-crypto-idle-game' ),
			),
			$atts,
			'sacig_logout_link'
		);

		return '<a href="' . esc_url( wp_logout_url( $atts['redirect'] ) ) . '" class="sacig-logout-link">' . esc_html( $atts['text'] ) . '</a>';
	}

	/* ---------------------------------------------------------------------
	 * Submission handlers
	 * ------------------------------------------------------------------- */

	/**
	 * Process a submitted login form.
	 */
	public function handle_login_submission() {
		if ( ! isset( $_POST['sacig_login_submit'] ) ) {
			return;
		}
		if ( ! isset( $_POST['sacig_login_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['sacig_login_nonce'] ) ), 'sacig_login_action' ) ) {
			return;
		}

		$creds = array(
			// sanitize_text_field (not sanitize_user) so email logins keep their @ and . characters.
			'user_login'    => isset( $_POST['sacig_username'] ) ? sanitize_text_field( wp_unslash( $_POST['sacig_username'] ) ) : '',
			// Passwords are intentionally not sanitized; doing so would strip valid special characters.
			'user_password' => isset( $_POST['sacig_password'] ) ? wp_unslash( $_POST['sacig_password'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
			'remember'      => ! empty( $_POST['sacig_remember'] ),
		);

		$user = wp_signon( $creds, is_ssl() );

		if ( is_wp_error( $user ) ) {
			$this->set_error( $user->get_error_message() );
			return;
		}

		$redirect = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : '';
		if ( empty( $redirect ) ) {
			$redirect = $this->login_redirect();
		}
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Process a submitted registration form.
	 */
	public function handle_register_submission() {
		if ( ! isset( $_POST['sacig_register_submit'] ) ) {
			return;
		}
		if ( ! isset( $_POST['sacig_register_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['sacig_register_nonce'] ) ), 'sacig_register_action' ) ) {
			return;
		}
		if ( ! get_option( 'users_can_register' ) || ! get_option( 'sacig_enable_registration', true ) ) {
			$this->set_error( __( 'Registration is currently disabled.', 'shortcodearcade-crypto-idle-game' ) );
			return;
		}

		$username  = isset( $_POST['sacig_reg_username'] ) ? sanitize_user( wp_unslash( $_POST['sacig_reg_username'] ) ) : '';
		$email     = isset( $_POST['sacig_reg_email'] ) ? sanitize_email( wp_unslash( $_POST['sacig_reg_email'] ) ) : '';
		// Passwords are intentionally not sanitized.
		$password  = isset( $_POST['sacig_reg_password'] ) ? wp_unslash( $_POST['sacig_reg_password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$password2 = isset( $_POST['sacig_reg_password2'] ) ? wp_unslash( $_POST['sacig_reg_password2'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash

		if ( empty( $username ) ) {
			$this->set_error( __( 'Please enter a username.', 'shortcodearcade-crypto-idle-game' ) );
			return;
		}
		if ( ! is_email( $email ) ) {
			$this->set_error( __( 'Please enter a valid email address.', 'shortcodearcade-crypto-idle-game' ) );
			return;
		}
		if ( '' === $password ) {
			$this->set_error( __( 'Please enter a password.', 'shortcodearcade-crypto-idle-game' ) );
			return;
		}
		if ( $password !== $password2 ) {
			$this->set_error( __( 'Passwords do not match.', 'shortcodearcade-crypto-idle-game' ) );
			return;
		}
		if ( username_exists( $username ) || email_exists( $email ) ) {
			$this->set_error( __( 'That username or email is already registered.', 'shortcodearcade-crypto-idle-game' ) );
			return;
		}

		$user_id = wp_create_user( $username, $password, $email );
		if ( is_wp_error( $user_id ) ) {
			$this->set_error( $user_id->get_error_message() );
			return;
		}

		$login_page = get_option( 'sacig_login_page', '' );
		wp_safe_redirect( $login_page ? add_query_arg( 'registered', '1', $login_page ) : wp_login_url() );
		exit;
	}

	/**
	 * Redirect the default wp-login.php to the custom login page when enabled.
	 */
	public function maybe_redirect_login() {
		if ( ! get_option( 'sacig_enable_custom_login', false ) || is_user_logged_in() ) {
			return;
		}
		$login_page = get_option( 'sacig_login_page', '' );
		if ( empty( $login_page ) ) {
			return;
		}

		global $pagenow;
		if ( 'wp-login.php' !== $pagenow ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check of the wp-login action.
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
		if ( in_array( $action, array( 'rp', 'resetpass', 'logout' ), true ) ) {
			return;
		}

		// Send registration attempts to the custom register page when one is configured.
		if ( 'register' === $action ) {
			$register_page = get_option( 'sacig_register_page', '' );
			if ( ! empty( $register_page ) ) {
				wp_safe_redirect( $register_page );
				exit;
			}
		}

		// Preserve the original redirect_to target (e.g. a protected page) across the redirect.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only passthrough of the login redirect target; no state-changing processing occurs.
		$redirect_to = isset( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : '';
		if ( '' !== $redirect_to ) {
			$login_page = add_query_arg(
				'redirect_to',
				rawurlencode( $redirect_to ),
				$login_page
			);
		}

		wp_safe_redirect( $login_page );
		exit;
	}

	/* ---------------------------------------------------------------------
	 * Assets & helpers
	 * ------------------------------------------------------------------- */

	/**
	 * Enqueue assets on pages that use the login shortcodes.
	 *
	 * The login forms share the game's neon/dark theme, so the existing game
	 * stylesheet covers the design language; no separate login CSS is needed.
	 */
	public function enqueue_assets() {
		if ( ! is_singular() ) {
			return;
		}
		$post = get_post();
		if ( ! $post ) {
			return;
		}
		if (
			has_shortcode( $post->post_content, 'sacig_login_form' ) ||
			has_shortcode( $post->post_content, 'sacig_register_form' ) ||
			has_shortcode( $post->post_content, 'sacig_forgot_password' ) ||
			has_shortcode( $post->post_content, 'sacig_logout_link' )
		) {
			wp_enqueue_style(
				'sacig-game-css',
				SACIG_PLUGIN_URL . 'assets/css/sacig-game.css',
				array(),
				SACIG_VERSION
			);
		}
	}

	/**
	 * Resolve the post-login redirect target.
	 *
	 * @return string
	 */
	private function login_redirect() {
		$redirect = get_option( 'sacig_login_redirect', '' );
		return $redirect ? $redirect : admin_url();
	}

	/**
	 * Resolve the post-logout redirect target.
	 *
	 * @return string
	 */
	private function logout_redirect() {
		$redirect = get_option( 'sacig_logout_redirect', '' );
		return $redirect ? $redirect : home_url();
	}

	/**
	 * Store an auth error for display on the next page load.
	 *
	 * @param string $message Error message.
	 */
	private function set_error( $message ) {
		$this->auth_error = $message;
	}

	/**
	 * Output and clear any stored auth error.
	 */
	private function maybe_render_error() {
		if ( ! empty( $this->auth_error ) ) {
			echo '<div class="sacig-login-error">' . esc_html( $this->auth_error ) . '</div>';
			$this->auth_error = '';
		}
	}

	/**
	 * Sanitize a checkbox value to a strict boolean.
	 *
	 * @param mixed $input Raw value.
	 * @return bool
	 */
	public function sanitize_checkbox( $input ) {
		return (bool) $input;
	}
}
