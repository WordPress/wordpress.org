<?php
/**
 * Plugin Name: WP.org Two Factor
 * Description: WordPress.org-specific Two Factor authentication tweaks.
 * Version:     1.0
 * Author:      WordPress.org
 * Author URI:  https://wordpress.org/
 * License:     GPLv2 or later
 *
 * @package WordPressdotorg\TwoFactor
 */

class WPORG_Two_Factor extends Two_Factor_Core {

	const WPORG_2FA_COOKIE = 'wporg_2fa';

	public function __construct() {
		add_filter( 'two_factor_providers', [ $this, 'two_factor_providers' ] );

		add_filter( 'determine_current_user', [ $this, 'disable_authentication_without_2fa' ], 20 ); // Cookies at priority 10, Must be > 11
		add_action( 'clear_auth_cookie',      [ $this, 'clear_2fa_cookies' ] );
		add_filter( 'salt',                   [ $this, 'add_2fa_salt' ], 10, 2 );
		add_action( 'set_auth_cookie',        [ $this, 'set_auth_cookie_maybe_set_2fa_cookie' ], 10, 6 );

		remove_action( 'edit_user_profile', [ 'Two_Factor_Core', 'user_two_factor_options' ] );
		remove_action( 'show_user_profile', [ 'Two_Factor_Core', 'user_two_factor_options' ] );

		if ( ! is_admin() ) {
			add_action( 'edit_user_profile', [ $this, 'user_two_factor_options' ] );
			add_action( 'show_user_profile', [ $this, 'user_two_factor_options' ] );
		}

		add_action( 'wp_ajax_two-factor-totp-verify-code', [ $this, 'ajax_verify_code' ] );
		add_action( 'wp_ajax_two-factor-disable',          [ $this, 'ajax_disable' ] );

		// Auth cookie unsetting.
		remove_action( 'wp_login',                [ 'Two_Factor_Core', 'wp_login' ], 10, 2 );
		remove_action( 'login_form_validate_2fa', [ 'Two_Factor_Core', 'login_form_validate_2fa' ] );
		remove_action( 'login_form_backup_2fa',   [ 'Two_Factor_Core', 'backup_2fa' ] );

		add_action( 'wp_login',                [ $this, 'wp_login' ], 10, 2 );
		add_action( 'login_form_validate_2fa', [ $this, 'login_form_validate_2fa' ] );
		add_action( 'login_form_backup_2fa',   [ $this, 'backup_2fa' ] );

	}

	function add_2fa_salt( $salt, $scheme ) {
		if ( '2fa' == $scheme ) {
			$salt = defined( 'WPORG_2FA_KEY' ) ? WPORG_2FA_KEY : AUTH_KEY;
		}

		return $salt;
	}

	function disable_authentication_without_2fa( $user_id ) {
		if ( ! $user_id ) {
			return $user_id;
		}
		// User is logged in:

		// If the user isn't a 2FA user, allow.
		if ( ! self::is_user_using_two_factor( $user_id ) ) {
			return $user_id;
		}

		// If the user has a valid 2FA cookie, allow
		if ( isset( $_COOKIE[ self::WPORG_2FA_COOKIE ] ) && wp_validate_auth_cookie( $_COOKIE[ self::WPORG_2FA_COOKIE ], '2fa' ) ) {
			return $user_id;
		}

		// If the user did not authenticate via Cookie, allow
		if ( ! wp_validate_auth_cookie( false ) && ! wp_validate_logged_in_cookie( false ) ) {
			// The user wasn't authenticated by cookie, so allow the auth.
			return $user_id;
		}

		// If they're on the 2FA login page, allow
		$login_host = class_exists( 'WPOrg_SSO' ) ? WPOrg_SSO::SSO_HOST : 'login.wordpress.org';
		if ( $login_host === $_SERVER['HTTP_HOST']  ) {
			if ( '/wp-login.php' == substr( $_SERVER['REQUEST_URI'], 0, 13 ) ) {
				if ( $_POST || ( isset( $_REQUEST['action'] ) && ( 'backup_2fa' == $_REQUEST['action'] || 'validate_2fa' == $_REQUEST['action'] ) ) ) {
					return $user_id;
				}
			}
		}

		/*
		 * Fail. We've checked that:
		 * - the user has 2FA enabled
		 * - doesn't have a valid 2FA cookie
		 * - the user is logged in via cookie
		 * - isn't currently logging in on the SSO host
		 *
		 * The users cookies are not valid until that 2FA cookie is set.
		 */
		return 0;
	}

	function set_auth_cookie_maybe_set_2fa_cookie( $auth_cookie, $expire, $expiration, $user_id, $scheme, $token = '' ) {
		// Check if they're the current user and 2FA
		if ( ! is_user_logged_in() || get_current_user_id() !== $user_id ) {
			return;
		}

		if ( ! self::is_user_using_two_factor( $user_id ) ) {
			return;
		}

		if ( empty( $_COOKIE[ self::WPORG_2FA_COOKIE ] ) ) {
			return;
		}

		// At this point we know they have a 2FA account, were already logged in, and had a 2FA cookie
		$this->set_2fa_cookies( get_userdata( $user_id ), $expire );
	}


	function clear_2fa_cookies() {
		setcookie( self::WPORG_2FA_COOKIE, ' ', time() - YEAR_IN_SECONDS, ADMIN_COOKIE_PATH,   COOKIE_DOMAIN );
		setcookie( self::WPORG_2FA_COOKIE, ' ', time() - YEAR_IN_SECONDS, PLUGINS_COOKIE_PATH, COOKIE_DOMAIN );
	}

	function set_2fa_cookies( $user, $expiration = false ) {
		if ( ! $expiration ) {
			// Set the Expiration based on the main Authentication cookie
			$auth_cookie_parts = wp_parse_auth_cookie( '', 'secure_auth' );
			if ( ! $auth_cookie_parts  ) {
				wp_logout();
				return;
			}
			$expiration = $auth_cookie_parts['expiration'];
		}

		$cookie_value = wp_generate_auth_cookie( $user->ID, $expiration, '2fa', '' /* WordPress.org doesn't use Session Tokens yet */ );

		setcookie( self::WPORG_2FA_COOKIE, $cookie_value, $expiration, ADMIN_COOKIE_PATH,   COOKIE_DOMAIN, true, true );
		setcookie( self::WPORG_2FA_COOKIE, $cookie_value, $expiration, PLUGINS_COOKIE_PATH, COOKIE_DOMAIN, true, true );
	}

	/**
	 * Handle the browser-based login.
	 *
	 * @since 0.1-dev
	 *
	 * @param string  $user_login Username.
	 * @param WP_User $user WP_User object of the logged-in user.
	 */
	public static function wp_login( $user_login, $user ) {
		if ( ! self::is_user_using_two_factor( $user->ID ) ) {
			return;
		}

		$redirect_to = isset( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : $_SERVER['REQUEST_URI'];
		self::login_html( $user, '', $redirect_to );

		exit;
	}

	/**
	 * Login form validation.
	 *
	 * @since 0.1-dev
	 */
	public static function login_form_validate_2fa() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$user = wp_get_current_user();

		if ( isset( $_POST['provider'] ) ) {
			$providers = self::get_available_providers_for_user( $user );
			if ( isset( $providers[ $_POST['provider'] ] ) ) {
				$provider = $providers[ $_POST['provider'] ];
			} else {
				wp_die( 'A valid 2FA provider could not be found.', 403 );
			}
		} else {
			$provider = self::get_primary_provider_for_user( $user->ID );
		}

		// Allow the provider to re-send codes, etc.
		if ( true === $provider->pre_process_authentication( $user ) ) {
			self::login_html( $user, '', $_REQUEST['redirect_to'], '', $provider );
			exit;
		}

		// Ask the provider to verify the second factor.
		if ( true !== $provider->validate_authentication( $user ) ) {
			do_action( 'wp_login_failed', $user->user_login );

			self::login_html( $user, '', $_REQUEST['redirect_to'], esc_html__( 'ERROR: Invalid verification code.', 'wporg' ), $provider );
			exit;
		}

		$this->set_2fa_cookies( $user );

		// Must be global because that's how login_header() uses it.
		global $interim_login;
		$interim_login = isset( $_REQUEST['interim-login'] ); // WPCS: override ok.

		if ( $interim_login ) {
			$customize_login = isset( $_REQUEST['customize-login'] );
			if ( $customize_login ) {
				wp_enqueue_script( 'customize-base' );
			}
			$message = '<p class="message">' . __( 'You have logged in successfully.' ) . '</p>';
			$interim_login = 'success'; // WPCS: override ok.
			login_header( '', $message ); ?>
			</div>
			<?php
			/** This action is documented in wp-login.php */
			do_action( 'login_footer' ); ?>
			<?php if ( $customize_login ) : ?>
				<script type="text/javascript">setTimeout( function(){ new wp.customize.Messenger({ url: '<?php echo wp_customize_url(); /* WPCS: XSS OK. */ ?>', channel: 'login' }).send('login') }, 1000 );</script>
			<?php endif; ?>
			</body></html>
			<?php
			exit;
		}
		$redirect_to = apply_filters( 'login_redirect', $_REQUEST['redirect_to'], $_REQUEST['redirect_to'], $user );
		wp_safe_redirect( $redirect_to );

		exit;
	}

	/**
	 * Add short description. @todo
	 *
	 * @since 0.1-dev
	 */
	public static function backup_2fa() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$user = wp_get_current_user();

		$providers = self::get_available_providers_for_user( $user );
		if ( isset( $providers[ $_GET['provider'] ] ) ) {
			$provider = $providers[ $_GET['provider'] ];
		} else {
			wp_die( 'No 2FA provider could be found.', 403 );
		}

		self::login_html( $user, '', $_GET['redirect_to'], '', $provider );

		exit;
	}

	/**
	 * Generates the html form for the second step of the authentication process.
	 *
	 * @since 0.1-dev
	 *
	 * @param WP_User       $user WP_User object of the logged-in user.
	 * @param string        $login_nonce A string nonce stored in usermeta.
	 * @param string        $redirect_to The URL to which the user would like to be redirected.
	 * @param string        $error_msg Optional. Login error message.
	 * @param string|object $provider An override to the provider.
	 */
	public static function login_html( $user, $login_nonce, $redirect_to, $error_msg = '', $provider = null ) {
		if ( empty( $provider ) ) {
			$provider = self::get_primary_provider_for_user( $user->ID );
		} elseif ( is_string( $provider ) && method_exists( $provider, 'get_instance' ) ) {
			$provider = call_user_func( array( $provider, 'get_instance' ) );
		}

		$provider_class = get_class( $provider );

		$available_providers = self::get_available_providers_for_user( $user );
		$backup_providers = array_diff_key( $available_providers, array( $provider_class => null ) );
		$interim_login = isset( $_REQUEST['interim-login'] ); // WPCS: override ok.
		$wp_login_url = wp_login_url();

		$rememberme = $_REQUEST['rememberme'] ?? 0;

		$backup_classname = key( $backup_providers );

		if ( ! function_exists( 'login_header' ) ) {
			// We really should migrate login_header() out of `wp-login.php` so it can be called from an includes file.
			include_once( TWO_FACTOR_DIR . 'includes/function.login-header.php' );
		}

		$wp_error = new \WP_Error();
		if ( isset( $_REQUEST['two-factor-backup-resend'] ) ) {
			$wp_error->add( 'codes-resent', esc_html__( 'Codes were re-sent.', 'wporg' ), 'message' );
		}
		if ( ! empty( $error_msg ) ) {
			$wp_error->add( 'authentication-error', esc_html( $error_msg ) );
		}

		login_header( __( 'Authenticate', 'wporg' ), '', $wp_error );
		?>

			<form name="validate_2fa_form" id="loginform" action="<?php echo esc_url( set_url_scheme( add_query_arg( 'action', 'validate_2fa', $wp_login_url ), 'login_post' ) ); ?>" method="post" autocomplete="off">
				<input type="hidden" name="provider"      id="provider"      value="<?php echo esc_attr( $provider_class ); ?>" />
				<?php if ( $interim_login ) : ?>
					<input type="hidden" name="interim-login" value="1" />
				<?php else : ?>
					<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>" />
				<?php endif; ?>
				<input type="hidden" name="rememberme"    id="rememberme"    value="<?php echo esc_attr( $rememberme ); ?>" />

				<?php $provider->authentication_page( $user ); ?>
			</form>
		</div><!-- Opened in login_header() -->

		<?php if ( 'WPORG_Two_Factor_Primary' === $provider_class && $backup_classname ) : ?>
		<div class="backup-methods-wrap">
			<a href="<?php echo esc_url( add_query_arg( urlencode_deep( array(
				'action'        => 'backup_2fa',
				'provider'      => $backup_classname,
				'redirect_to'   => $redirect_to,
				'rememberme'    => $rememberme,
			) ), $wp_login_url ) ); ?>"><?php esc_html_e( 'Try another way to sign in &rarr;', 'wporg' ); ?></a>
		</div>
		<?php endif; ?>

		<style>
			body:not(.login-action-backup_2fa):not(.login-action-validate_2fa) #login {
				margin-bottom: 0;
			}
			.login-action-backup_2fa #login,
			.login-action-validate_2fa #login {
				margin-bottom: 24px;
			}
			.wp-core-ui.login .button-primary {
				float: none;
			}
			#login .two-factor-email-resend {
				margin-top: 16px;
			}
			.backup-methods-wrap {
				margin: 24px 0;
				text-align: center;
			}
			.backup-methods-wrap a {
				color: #999;
				text-decoration: none;
			}
			/* Prevent Jetpack from hiding our controls, see https://github.com/Automattic/jetpack/issues/3747 */
			.jetpack-sso-form-display #loginform > p,
			.jetpack-sso-form-display #loginform > div {
				display: block;
			}
		</style>

		<?php
		/** This action is documented in wp-login.php */
		do_action( 'login_footer' ); ?>
		<div class="clear"></div>
		</body>
		</html>
		<?php
	}

	public function two_factor_providers() {
		return array(
			'WPORG_Two_Factor_Primary'   => __DIR__ . '/providers/class-wporg-two-factor-primary.php',
			'WPORG_Two_Factor_Secondary' => __DIR__ . '/providers/class-wporg-two-factor-secondary.php',
		);
	}

	/**
	 * Simple handler to enable Two factor for a given user.
	 * NOTE: It's assumed that the Two Factor details have been setup correctly previously.
	 */
	public function enable_two_factor( $user_id ) {
		$result = (
			update_user_meta( $user_id, self::PROVIDER_USER_META_KEY,          'WPORG_Two_Factor_Primary' ) &&
			update_user_meta( $user_id, self::ENABLED_PROVIDERS_USER_META_KEY, [ 'WPORG_Two_Factor_Primary', 'WPORG_Two_Factor_Secondary' ] )
		);

		if ( $result && $user_id == get_current_user_id() ) {
			$user = wp_get_current_user();
			$this->set_2fa_cookies( $user );
		}

		return $result;
	}

	/**
	 * Simple handler to disable Two factor for a given user.
	 */
	public function disable_two_factor( $user_id ) {
		delete_user_meta( $user_id, self::PROVIDER_USER_META_KEY );
		delete_user_meta( $user_id, self::ENABLED_PROVIDERS_USER_META_KEY );
		delete_user_meta( $user_id, Two_Factor_Totp::SECRET_META_KEY );
		return true;
	}

	/**
	 * Displays the UI to set up and remove 2FA.
	 *
	 * @param \WP_User $user User object.
	 */
	public static function user_two_factor_options( $user ) {
		if ( ! function_exists( 'wporg_user_has_restricted_password' ) || ! wporg_user_has_restricted_password( $user->ID ) ) {
			return;
		}

		$key = get_user_meta( $user->ID, Two_Factor_Totp::SECRET_META_KEY, true ) ?: Two_Factor_Totp::generate_key();

		wp_enqueue_script( 'two-factor-client', plugins_url( 'js/client.js', __FILE__ ), [ 'wp-util' ], 1, true );
		wp_localize_script( 'two-factor-client', 'twoFactorClient', [
			'isActive'    => self::is_user_using_two_factor( $user->ID ),
			'qrCode'      => Two_Factor_Totp::get_google_qr_code( 'WordPress.org: ' . $user->user_login, $key, 'WordPress.org' ),
			'key'         => $key,
			'nonce'       => wp_create_nonce( 'user_two_factor_totp_options' ),
			'backupNonce' => wp_create_nonce( 'two-factor-backup-codes-generate-json-' . $user->ID ),
			'userId'      => $user->ID,

			'twoFactorAuthentication'        => esc_html__( 'Two Factor Authentication', 'wporg' ),
			'twoFactor'                      => esc_html__( 'Two Factor', 'wporg' ),
			'disableTwoFactorAuthentication' => esc_html__( 'Disable Two Factor Authentication', 'wporg' ),
			'statusActive'                   => wp_kses_post( __( '<strong>Status:</strong> <span>Active</span>', 'wporg' ) ),
			'twoFactorDescription'           => esc_html__( 'While enabled, logging in to WordPress.org requires you to enter a unique passcode, generated by an app on your mobile device, in addition to your username and password.', 'wporg' ),
			'generateNewBackupCodes'         => esc_html__( 'Generate New Backup Codes', 'wporg' ),
			'backupCodesDescription'         => esc_html__( 'Backup codes let you access your account if your phone is lost, stolen, or if you run it through the washing machine and the bag of rice trick doesn&#8217;t work.', 'worg' ),
			'askToPrintList'                 => esc_html__( 'We ask that you print this list of ten unique, one-time-use backup codes and keep the list in a safe place.', 'wporg' ),
			'backupCodesWarning'             => esc_html__( 'Without access to the app or a backup code, you will lose access to your account.', 'wporg' ),
			'printConfirmation'              => esc_html__( 'I have printed or saved these codes', 'wporg' ),
			'backupCodes'                    => esc_html__( 'Backup Codes', 'wporg' ),
			'copyCodes'                      => esc_html__( 'Copy Codes', 'wporg' ),
			'printCodes'                     => esc_html__( 'Print Codes', 'wporg' ),
			'downloadCodes'                  => esc_html__( 'Download Codes', 'wporg' ),
			'allFinished'                    => esc_html__( 'All Finished!', 'wporg' ),
			'scanThisQrCode'                 => esc_html__( 'Scan this QR code with your authentication app.', 'wporg' ),
			'enterThisTimeCode'              => esc_html__( 'Enter this time code into your authentication app.', 'wporg' ),
			'cantScanTheCode'                => esc_html__( 'Can&#8217;t scan the code?', 'wporg' ),
			'preferToScanTheCode'            => esc_html__( 'Prefer to scan the code?', 'wporg' ),
			'thenEnterTheAuthenticationCode' => esc_html__( 'Then enter the authentication code provided by the app:', 'wporg' ),
			'authenticationCode'             => esc_html__( 'Authentication Code:', 'wporg' ),
			'placeholder'                    => esc_attr__( 'e.g. 123456', 'wporg' ),
			/* translators: 1: URL to Authy; 2: URL to Google Authenticator */
			'notSureWhatThisScreenMeans'     => sprintf( wp_kses_post( __( 'Not sure what this screen means? You may need to download <a href="%1$s">Authy</a> or <a href="%2$s">Google Authenticator</a> for your phone.', 'wporg' ) ), esc_url( 'https://authy.com/download/' ), esc_url( 'https://support.google.com/accounts/answer/1066447?hl=' . get_locale() ) ),
			'cancel'                         => esc_html__( 'Cancel', 'wporg' ),
			'enable'                         => esc_html__( 'Enable', 'wporg' ),
			'getStarted'                     => esc_html__( 'Get Started', 'wporg' ),
			'twoFactorLongDescription'       => esc_html__( 'Two-Step Authentication adds an extra layer of security to your account. Once enabled, logging in to WordPress.org will require you to enter a unique passcode generated by an app on your mobile device, in addition to your username and password.', 'wporg' ),
		] );
		?>

		<h2 class="entry-title"><?php esc_html_e( 'Two Factor Authentication', 'wporg' ); ?></h2>
		<div id="two-factor-client"></div>

		<style>
			#bbpress-forums .two-factor .status {
				margin: 5px 0;
			}
			#bbpress-forums .two-factor .status span {
				color: #4ab866;
				text-transform: uppercase;
			}
			#bbpress-forums #bbp-your-profile input[type="checkbox"] {
				width: auto;
			}
			#bbpress-forums #bbp-your-profile .two-factor [for="print-agreement"] {
				float: none;
				width: auto;
			}
			#bbpress-forums #two-factor-qr-code > div,
			#bbpress-forums #two-factor-key-code > div {
				margin-left: 20%;
				width: 60% !important;
			}
			#bbpress-forums .two-factor .description {
				display: block;
				margin-bottom: 20px;
			}
			.dashicons-clipboard:before {
				transform: rotate( -45deg );
			}
			.dashicons-index-card:before {
				vertical-align: initial;
			}
			.dashicons-download:before {
				vertical-align: text-top;
			}

			.two-factor-backup-codes-wrapper .two-factor-submit {
				vertical-align: middle;
			}

			#bbpress-forums .two-factor button.button-link {
				color: #4ca6cf;
				padding: 0;
			}
			#bbpress-forums .two-factor .key {
				padding: 2rem 0;
			}
		</style>

		<?php
	}

	/**
	 * AJAX handler to verify a user's 2FA code.
	 */
	public function ajax_verify_code() {
		check_ajax_referer( 'user_two_factor_totp_options', '_nonce_user_two_factor_totp_options' );

		$user_id = absint( $_POST['user_id'] );
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			wp_send_json_error( __( 'You do not have permission to edit this user.' ) );
		}

		if ( empty( $_POST['authcode'] ) ) {
			wp_send_json_error( __( 'Please enter a valid authorization code.', 'wporg' ) );
		}

		if ( Two_Factor_Totp::is_valid_authcode( $_POST['key'], $_POST['authcode'] ) ) {
			if ( ! update_user_meta( $user_id, Two_Factor_Totp::SECRET_META_KEY, $_POST['key'] ) ) {
				wp_send_json_error( __( 'Unable to save Two Factor Authentication code. Please try again.', 'wporg' ) );
			}

			if ( ! $this->enable_two_factor( $user_id ) ) {
				wp_send_json_error( __( 'Unable to save Two Factor Authentication code. Please try again.', 'wporg' ) );
			}

			wp_send_json_success();
		}

		wp_send_json_error( __( 'The authentication code you entered was not valid. Please try again.', 'wporg' ) );
	}

	/**
	 * AJAX handler to disable 2FA.
	 */
	public function ajax_disable() {
		check_ajax_referer( 'user_two_factor_totp_options', '_nonce_user_two_factor_totp_options' );

		$user_id = absint( $_POST['user_id'] );
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			wp_send_json_error( __( 'You do not have permission to edit this user.' ) );
		}

		if ( ! $this->disable_two_factor( $user_id ) ) {
			wp_send_json_error( __( 'Unable to remove Two Factor Authentication code. Please try again.', 'wporg' ) );
		}

		wp_send_json_success( __( 'Two Factor authentication disabled. Your account is now less secure.', 'wporg' ) );
	}
}
new WPORG_Two_Factor();
