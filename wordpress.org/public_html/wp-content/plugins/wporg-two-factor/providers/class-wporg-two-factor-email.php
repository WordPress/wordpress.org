<?php

require_once TWO_FACTOR_DIR . 'providers/class.two-factor-email.php';

class WPORG_Two_Factor_Email extends Two_Factor_Email {
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

		return wp_mail( $user->user_email, $subject, $message );
	}

}
