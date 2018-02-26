<?php

class WPORG_Two_Factor_Email extends Two_Factor_Provider {

	const TOKEN_META_KEY = '_two_factor_email_token';

	public static function get_instance() {
		static $instance;
		if ( ! $instance ) {
			$class = __CLASS__;
			$instance = new $class;
		}
		return $instance;
	}

	public function get_label() {
		return 'Email'; // Not marked for translation as this shouldn't be called/displayed.
	}

	public function generate_token( $user_id ) {
		$token = $this->get_code();
		update_user_meta( $user_id, static::TOKEN_META_KEY, wp_hash( $token ) );
		return $token;
	}

	public function user_has_token( $user_id ) {
		return (bool) $this->get_user_token( $user_id );
	}

	public function get_user_token( $user_id ) {
		$hashed_token = get_user_meta( $user_id, static::TOKEN_META_KEY, true );

		if ( ! empty( $hashed_token ) && is_string( $hashed_token ) ) {
			return $hashed_token;
		}

		return false;
	}

	public function validate_token( $user_id, $token ) {
		$hashed_token = $this->get_user_token( $user_id );

		// Bail if token is empty or it doesn't match.
		if ( empty( $hashed_token ) || ( wp_hash( $token ) !== $hashed_token ) ) {
			return false;
		}

		// Ensure that the token can't be re-used.
		$this->delete_token( $user_id );

		return true;
	}

	public function delete_token( $user_id ) {
		delete_user_meta( $user_id, static::TOKEN_META_KEY );
	}

	public function validate_authentication( $user, $code = '' ) {
		if ( empty( $user->ID ) || ! $code ) {
			return false;
		}

		return $this->validate_token( $user->ID, $code );
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
		$subject = __( 'Your login confirmation code for WordPress.org', 'wporg' );
		/* translators: %s: token */
		$message = __( 'Please enter the following verification code on WordPress.org to complete your login:', 'wporg' );
		$message .= "\n" . $token;

		return wp_mail( $user->user_email, $subject, $message );
	}

	public function authentication_page( $user ) {
		// N/A
	}

	public function pre_process_authentication( $user ) {
		// N/A
		return false;
	}

	public function is_available_for_user( $user ) {
		return true;
	}
}
