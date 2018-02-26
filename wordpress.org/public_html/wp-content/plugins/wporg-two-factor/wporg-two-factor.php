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

	public function __construct() {
		add_filter( 'two_factor_providers', [ $this, 'two_factor_providers' ] );

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

		wp_clear_auth_cookie();

		self::show_two_factor_login( $user );
		exit;
	}

	/**
	 * Login form validation.
	 *
	 * @since 0.1-dev
	 */
	public static function login_form_validate_2fa() {
		if ( ! isset( $_POST['wp-auth-id'], $_POST['wp-auth-nonce'] ) ) {
			return;
		}

		$user = get_userdata( $_POST['wp-auth-id'] );
		if ( ! $user ) {
			return;
		}

		$nonce = $_POST['wp-auth-nonce'];
		if ( true !== self::verify_login_nonce( $user->ID, $nonce ) ) {
			wp_safe_redirect( get_bloginfo( 'url' ) );
			exit;
		}

		if ( isset( $_POST['provider'] ) ) {
			$providers = self::get_available_providers_for_user( $user );
			if ( isset( $providers[ $_POST['provider'] ] ) ) {
				$provider = $providers[ $_POST['provider'] ];
			} else {
				wp_die( esc_html__( 'Cheatin&#8217; uh?' ), 403 );
			}
		} else {
			$provider = self::get_primary_provider_for_user( $user->ID );
		}

		// Allow the provider to re-send codes, etc.
		if ( true === $provider->pre_process_authentication( $user ) ) {
			$login_nonce = self::create_login_nonce( $user->ID );
			if ( ! $login_nonce ) {
				wp_die( esc_html__( 'Failed to create a login nonce.', 'two-factor' ) );
			}

			self::login_html( $user, $login_nonce['key'], $_REQUEST['redirect_to'], '', $provider );
			exit;
		}

		// Ask the provider to verify the second factor.
		if ( true !== $provider->validate_authentication( $user ) ) {
			do_action( 'wp_login_failed', $user->user_login );

			$login_nonce = self::create_login_nonce( $user->ID );
			if ( ! $login_nonce ) {
				wp_die( esc_html__( 'Failed to create a login nonce.', 'two-factor' ) );
			}

			self::login_html( $user, $login_nonce['key'], $_REQUEST['redirect_to'], esc_html__( 'ERROR: Invalid verification code.', 'two-factor' ), $provider );
			exit;
		}

		self::delete_login_nonce( $user->ID );

		$rememberme = false;
		if ( isset( $_REQUEST['rememberme'] ) && $_REQUEST['rememberme'] ) {
			$rememberme = true;
		}

		wp_set_auth_cookie( $user->ID, $rememberme );

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
		if ( ! isset( $_GET['wp-auth-id'], $_GET['wp-auth-nonce'], $_GET['provider'] ) ) {
			return;
		}

		$user = get_userdata( $_GET['wp-auth-id'] );
		if ( ! $user ) {
			return;
		}

		$nonce = $_GET['wp-auth-nonce'];
		if ( true !== self::verify_login_nonce( $user->ID, $nonce ) ) {
			wp_safe_redirect( get_bloginfo( 'url' ) );
			exit;
		}

		$providers = self::get_available_providers_for_user( $user );
		if ( isset( $providers[ $_GET['provider'] ] ) ) {
			$provider = $providers[ $_GET['provider'] ];
		} else {
			wp_die( esc_html__( 'Cheatin&#8217; uh?' ), 403 );
		}

		self::login_html( $user, $_GET['wp-auth-nonce'], $_GET['redirect_to'], '', $provider );

		exit;
	}

	public function two_factor_providers( $providers) {
		$wporg_providers = array(
			'WPORG_Two_Factor_Primary'   => __DIR__ . '/providers/class-wporg-two-factor-primary.php',
			'WPORG_Two_Factor_Secondary' => __DIR__ . '/providers/class-wporg-two-factor-secondary.php',
		);

		return $wporg_providers;
	}

	/**
	 * Simple handler to enable Two factor for a given user.
	 * NOTE: It's assumed that the Two Factor details have been setup correctly previously.
	 */
	public static function enable_two_factor( $user_id ) {
		return (
			update_user_meta( $user_id, self::PROVIDER_USER_META_KEY,          'WPORG_Two_Factor_Primary' ) &&
			update_user_meta( $user_id, self::ENABLED_PROVIDERS_USER_META_KEY, [ 'WPORG_Two_Factor_Primary', 'WPORG_Two_Factor_Secondary' ] )
		);
	}

	/**
	 * Simple handler to disable Two factor for a given user.
	 */
	public static function disable_two_factor( $user_id ) {
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
		wp_enqueue_script( 'two-factor-edit', plugins_url( 'js/profile-edit.js' , __FILE__ ), [ 'jquery' ], 1, true );
		wp_localize_script( 'two-factor-edit', 'two_factor_edit', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
		) );

		$key       = get_user_meta( $user->ID, Two_Factor_Totp::SECRET_META_KEY, true );
		$is_active = self::is_user_using_two_factor( $user->ID );
		?>

		<h2 class="entry-title"><?php esc_html_e( 'Two Factor Authentication', 'wporg' ); ?></h2>
		<fieldset id="two-factor-active" class="bbp-form two-factor" <?php if ( ! $is_active ) { echo 'style="display:none;"'; } ?>>
			<legend><?php esc_html_e( 'Two Factor Authentication', 'wporg' ); ?></legend>
			<div>
				<label for=""><?php esc_html_e( 'Two Factor', 'worg' ); ?></label>
				<fieldset class="bbp-form">
					<div>
						<button type="cancel" class="button button-secondary alignright"><?php esc_html_e( 'Disable Two Factor Authentication', 'wporg' ); ?></button>
						<p class="status"><?php echo wp_kses_post( __( '<strong>Status:</strong> <span>Active</span>', 'wporg' ) ); ?></p>
					</div>
					<p><?php esc_html_e( 'While enabled, logging in to WordPress.org requires you to enter a unique passcode, generated by an app on your mobile device, in addition to your username and password.', 'wporg' ); ?></p>
				</fieldset>
			</div>

			<div>
				<label for=""><?php esc_html_e( 'Backup Codes', 'worg' ); ?></label>
				<fieldset class="bbp-form">
					<?php wp_nonce_field( 'two-factor-backup-codes-generate-json-' . $user->ID, '_nonce-backup-codes' ); ?>
					<div id="two-factor-backup-codes-button">
						<div><button type="button" id="generate-backup-codes" class="button button-secondary"><?php esc_html_e( 'Generate New Backup Codes', 'wporg' ); ?></button></div>
						<p><?php esc_html_e( 'Backup codes let you access your account if your phone is lost, stolen, or if you run it through the washing machine and the bag of rice trick doesn&#8217;t work.', 'worg' ); ?></p>
					</div>
					<div class="two-factor-backup-codes-wrapper" style="display:none;">
						<p class="description"><?php esc_html_e( 'We ask that you print this list of ten unique, one-time-use backup codes and keep the list in a safe place.', 'wporg' ); ?></p>
						<ol id="two-factor-backup-codes-list"></ol>
						<div><small><?php esc_html_e( 'Without access to the app or a backup code, you will lose access to your account.', 'wporg' ); ?></small></div>
						<div>
							<input type="checkbox" id="print-agreement" name="print-agreement" />
							<label for="print-agreement"><?php esc_html_e( 'I have printed or saved these codes', 'wporg' ); ?></label>
							<span class="button-group">
								<button type="button" class="button button-secondary dashicons-before dashicons-clipboard" id="two-factor-backup-codes-copy" title="<?php esc_attr_e( 'Copy Codes', 'wporg' ); ?>"><span class="screen-reader-text"><?php esc_html_e( 'Copy Codes', 'wporg' ); ?></span></button>
								<button type="button" class="button button-secondary dashicons-before dashicons-index-card" id="two-factor-backup-codes-print" title="<?php esc_attr_e( 'Print Codes', 'wporg' ); ?>"><span class="screen-reader-text"><?php esc_html_e( 'Print Codes', 'wporg' ); ?></span></button>
								<a href="" class="button button-secondary dashicons-before dashicons-download" id="two-factor-backup-codes-download" title="<?php esc_attr_e( 'Download Codes', 'wporg' ); ?>" download="two-factor-backup-codes.txt"><span class="screen-reader-text"><?php esc_html_e( 'Download Codes', 'wporg' ); ?></span></a>
							</span>
							<button type="submit" class="button button-secondary" disabled="disabled"><?php esc_html_e( 'All Finished!', 'wporg' ); ?></button>
						</div>
					</div>
				</fieldset>
			</div>
		</fieldset>
		<?php
		if ( empty( $key ) ) {
			$key = Two_Factor_Totp::generate_key();
		}

		wp_nonce_field( 'user_two_factor_totp_options', '_nonce_user_two_factor_totp_options' );
		?>
		<fieldset id="two-factor-start" class="bbp-form two-factor" <?php if ( $is_active ) { echo 'style="display:none;"'; } ?>>
			<legend><?php esc_html_e( 'Two Factor Authentication', 'wporg' ); ?></legend>
			<div><?php esc_html_e( 'Two-Step Authentication adds an extra layer of security to your account. Once enabled, logging in to WordPress.org will require you to enter a unique passcode generated by an app on your mobile device, in addition to your username and password.', 'wporg' ); ?></div>
			<div><button type="button" id="two-factor-start-toggle" class="button button-primary"><?php esc_html_e( 'Get Started', 'wporg' ); ?></button></div>
		</fieldset>

		<fieldset id="two-factor-qr-code" class="bbp-form two-factor" style="display: none;">
			<legend><?php esc_html_e( 'Two Factor Authentication', 'wporg' ); ?></legend>
			<div>
				<p><?php esc_html_e( 'Scan this QR code with your mobile app.', 'wporg' ); ?></p>
				<p><button type="button" class="button-link"><?php esc_html_e( 'Can&#8217;t scan the code?', 'wporg' ); ?></button></p>
				<img src="<?php echo esc_url( Two_Factor_Totp::get_google_qr_code( 'wordpress.org:' . $user->user_login, $key, 'wordpress.org' ) ); ?>" id="two-factor-totp-qrcode" />
				<p><?php esc_html_e( 'Then enter the authentication code provided by the app:', 'wporg' ); ?></p>
				<p>
					<label class="screen-reader-text" for="two-factor-totp-authcode"><?php esc_html_e( 'Authentication Code:', 'wporg' ); ?></label>
					<input type="hidden" name="two-factor-totp-key" value="<?php echo esc_attr( $key ) ?>" />
					<input type="tel" name="two-factor-totp-authcode" class="input" value="" size="20" pattern="[0-9]*" placeholder="<?php esc_attr_e( 'e.g. 123456', 'wporg' ); ?>" />
				</p>
				<small class="description">
					<?php
					/* translators: 1: URL to Authy; 2: URL to Google Authenticator */
					printf( wp_kses_post( __( 'Not sure what this screen means? You may need to download <a href="%1$s">Authy</a> or <a href="%2$s">Google Authenticator</a> for your phone.', 'wporg' ) ), esc_url( 'https://authy.com/download/' ), esc_url( 'https://support.google.com/accounts/answer/1066447?hl=' . get_locale() ) );
					?>
				</small>
				<button type="cancel" class="button button-secondary alignleft"><?php esc_html_e( 'Cancel', 'wporg' ); ?></button>
				<button type="submit" class="button button-primary alignright"><?php esc_html_e( 'Enable', 'wporg' ); ?></button>
			</div>
		</fieldset>

		<fieldset id="two-factor-key-code" class="bbp-form two-factor" style="display: none;">
			<legend><?php esc_html_e( 'Two Factor Authentication', 'wporg' ); ?></legend>
			<div>
				<p><?php esc_html_e( 'Enter this time code into your mobile app.', 'wporg' ); ?></p>
				<p><button type="button" class="button-link"><?php esc_html_e( 'Prefer to scan the code?', 'wporg' ); ?></button></p>
				<p class="key"><strong><?php echo esc_html( $key ); ?></strong></p>
				<p><?php esc_html_e( 'Then enter the authentication code provided by the app:', 'wporg' ); ?></p>
				<p>
					<label class="screen-reader-text" for="two-factor-totp-authcode"><?php esc_html_e( 'Authentication Code:', 'wporg' ); ?></label>
					<input type="hidden" name="two-factor-totp-key" value="<?php echo esc_attr( $key ) ?>" />
					<input type="tel" name="two-factor-totp-authcode" class="input" value="" size="20" pattern="[0-9]*" placeholder="<?php esc_attr_e( 'e.g. 123456', 'wporg' ); ?>" />
				</p>
				<small class="description">
					<?php
					/* translators: 1: URL to Authy; 2: URL to Google Authenticator */
					printf( wp_kses_post( __( 'Not sure what this screen means? You may need to download <a href="%1$s">Authy</a> or <a href="%2$s">Google Authenticator</a> for your phone.', 'wporg' ) ), esc_url( 'https://authy.com/download/' ), esc_url( 'https://support.google.com/accounts/answer/1066447?hl=' . get_locale() ) );
					?>
				</small>
				<button type="cancel" class="button button-secondary alignleft"><?php esc_html_e( 'Cancel', 'wporg' ); ?></button>
				<button type="submit" class="button button-primary alignright"><?php esc_html_e( 'Enable', 'wporg' ); ?></button>
			</div>
		</fieldset>

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

			.two-factor-backup-codes-wrapper [type="submit"] {
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

			if ( ! self::enable_two_factor( $user_id ) ) {
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

		if ( ! self::disable_two_factor( $user_id ) ) {
			wp_send_json_error( __( 'Unable to remove Two Factor Authentication code. Please try again.', 'wporg' ) );
		}

		wp_send_json_success( __( 'Two Factor authentication disabled. Your account is now less secure.', 'wporg' ) );
	}
}
new WPORG_Two_Factor();
