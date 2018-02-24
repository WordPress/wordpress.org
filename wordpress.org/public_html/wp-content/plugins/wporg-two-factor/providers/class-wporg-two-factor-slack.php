<?php

require_once TWO_FACTOR_DIR . 'providers/class.two-factor-email.php';

class WPORG_Two_Factor_Slack extends Two_Factor_Email {

	/**
	 * The user meta token key.
	 *
	 * @type string
	 */
	const TOKEN_META_KEY = '_two_factor_slack_token';

	/**
	 * Name of the input field used for code resend.
	 *
	 * @var string
	 */
	const INPUT_NAME_RESEND_CODE = 'two-factor-slack-code-resend';

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
		return _x( 'Slack', 'Provider Label', 'wporg' );
	}

	/**
	 * Whether this Two Factor provider is configured and available for the user specified.
	 *
	 * @since 0.1-dev
	 *
	 * @param WP_User $user WP_User object of the logged-in user.
	 * @return boolean
	 */
	public function is_available_for_user( $user ) {
		// TODO Check if the user has a 2FA slack account.
		return false;
	}

	/**
	 * Generate and email the user token.
	 *
	 * @since 0.1-dev
	 *
	 * @param WP_User $user WP_User object of the logged-in user.
	 */
	public function generate_and_email_token( $user ) {
		$token = $this->generate_token( $user->ID );

		/* translators: %s: site name */
		$subject = wp_strip_all_tags( sprintf( __( 'Your login confirmation code for %s', 'wporg' ), get_bloginfo( 'name' ) ) );
		/* translators: %s: token */
		$message = wp_strip_all_tags( sprintf( __( 'Enter %s to log in.', 'wporg' ), $token ) );

		$who = '@dd32';

		return slack_dm( $subject . "\n" . $message, $who );
	}

}
