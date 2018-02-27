<?php

class WPORG_Two_Factor_Secondary extends Two_Factor_Provider { // When it's a proper wrapper.

	/**
	 * Ensures only one instance of this class exists in memory at any one time.
	 *
	 * @since 0.1-dev
	 */
	static function get_instance() {
		static $instance;
		$class = __CLASS__;
		if ( ! is_a( $instance, $class ) ) {
			$instance = new $class;
		}
		return $instance;
	}

	public function get_label() {
		return 'WordPress.org Secondary 2FA Provider'; // Not translated as it's not displayed, this is purely for debugging & the parent plugin.
	}

	public function authentication_page( $user ) {
		if ( ! $user ) {
			return;
		}

		// If this is the first load, resend the codes.
		if ( ! isset( $_REQUEST['two-factor-backup-code'] ) ) {
			$this->send_codes_to_user( $user, true );
		}

		require_once( ABSPATH . '/wp-admin/includes/template.php' );

		$email_enabled = isset( $this->providers['WPORG_Two_Factor_Email'] ) && $this->providers['WPORG_Two_Factor_Email']->is_available_for_user( $user );
		$slack_enabled = isset( $this->providers['WPORG_Two_Factor_Slack'] ) && $this->providers['WPORG_Two_Factor_Slack']->is_available_for_user( $user );

		if ( $email_enabled && $slack_enabled ) {
			echo '<p class="intro">' . __( 'Enter the verification code sent to your Email, Slack, or a backup code.', 'wporg' ) . '</p>';
		} elseif ( $email_enabled ) {
			echo '<p class="intro">' . __( 'Enter the verification code sent to your Email, or a backup code.', 'wporg' ) . '</p>';
		} else {
			echo '<p class="intro">' . __( 'Enter a backup code.', 'wporg' ) . '</p>';
		}
		?>

		<p>
			<label for="authcode"><?php esc_html_e( 'Verification Code:', 'wporg' ); ?></label>
			<input type="tel" name="two-factor-backup-code" id="authcode" class="input" value="" size="20" pattern="[0-9]{6,20}" title="<?php esc_attr_e( 'Codes are at least 6 decimal digits', 'wporg' ); ?>" />
			<?php submit_button( __( 'Authenticate', 'wporg' ), 'primary', 'submit', false ); ?>
		</p>

		<script type="text/javascript">
			setTimeout( function(){
				var d;
				try{
					d = document.getElementById('authcode');
					d.value = '';
					d.focus();
				} catch(e){}
			}, 200);
		</script>

		<?php if ( $email_enabled || $slack_enabled ) : ?>
		<p class="two-factor-email-resend">
			<button type="submit" class="button-link" name="two-factor-backup-resend"><span class="dashicons-before dashicons-controls-repeat"><?php esc_html_e( 'Resend Code', 'wporg' ); ?></span></button>
		</p>
		<?php endif;
	}

	function is_available_for_user( $user ) { return true; }

	protected $providers = [];

	protected function __construct() {
		$providers = [
			'WPORG_Two_Factor_Email'        => __DIR__ . '/class-wporg-two-factor-email.php',
			'WPORG_Two_Factor_Backup_Codes' => __DIR__ . '/class-wporg-two-factor-backup-codes.php',
			'WPORG_Two_Factor_Slack'        => __DIR__ . '/class-wporg-two-factor-slack.php'
		];
		$providers = apply_filters( 'wporg_two_factor_secondary_providers', $providers );

		foreach ( $providers as $class => $path ) {
			include_once( $path );

			if ( class_exists( $class ) ) {
				try {
					$this->providers[ $class ] = call_user_func( array( $class, 'get_instance' ) );
				} catch ( Exception $e ) {
					unset( $this->providers[ $class ] );
				}
			}
		}

		return parent::__construct();
	}

	public function pre_process_authentication( $user ) {
		if ( isset( $_REQUEST['two-factor-backup-resend'] ) ) {
			return $this->send_codes_to_user( $user, true );
		}

		return false;
	}

	// Send codes to the user based on the providers available.
	//
	protected function send_codes_to_user( $user, $resend = false ) {
		$result = false;

		if (
			isset( $this->providers['WPORG_Two_Factor_Email'] ) &&
			$this->providers['WPORG_Two_Factor_Email']->is_available_for_user( $user )
		) {
			if (
				$resend |
				! $this->providers['WPORG_Two_Factor_Email']->user_has_token( $user->ID )
			) {
				$result = true;
				$this->providers['WPORG_Two_Factor_Email']->generate_and_email_token( $user );
			}
		}

		if (
			isset( $this->providers['WPORG_Two_Factor_Slack'] ) &&
			$this->providers['WPORG_Two_Factor_Slack']->is_available_for_user( $user )
		) {
			if (
				$resend ||
				! $this->providers['WPORG_Two_Factor_Slack']->user_has_token( $user->ID )
			) {
				$result = true;
				$this->providers['WPORG_Two_Factor_Slack']->generate_and_slack_token( $user );
			}
		}

		return $result;
	}

	function validate_authentication( $user ) {
		if ( empty( $_POST['two-factor-backup-code'] ) ) {
			return false;
		}

		$backup_code = $_POST['two-factor-backup-code'];

		$authenticated = false;

		foreach ( $this->providers as $provider ) {
			if (
				$provider->is_available_for_user( $user ) &&
				$provider->validate_authentication( $user, $backup_code )
			) {
				$authenticated = true;
				break;
			}
		}

		// Also check the Primary method for the user just in case.
		$primary_provider = WPORG_Two_Factor_Primary::get_instance();
		if (
			! $authenticated &&
			$primary_provider->is_available_for_user( $user ) &&
			$primary_provider->validate_authentication( $user, $backup_code )
		) {
			$authenticated = true;
		}

		if ( $authenticated ) {
			foreach ( $this->providers as $provider ) {
				if ( is_callable( [ $provider, 'delete_token' ] ) ) {
					$provider->delete_token( $user->ID );
				}
			}
		}

		return $authenticated;
	}
}
