<?php

require_once TWO_FACTOR_DIR . 'providers/class.two-factor-totp.php';

class WPORG_Two_Factor_Primary extends Two_Factor_Totp {
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
	 * Returns the name of the provider.
	 */
	public function get_label() {
		return _x( 'Time Based One-Time Password (Google Authenticator, Authy, etc)', 'Provider Label', 'wporg' );
	}

	public function validate_authentication( $user, $code = '' ) {
		$key = get_user_meta( $user->ID, self::SECRET_META_KEY, true );

		if ( ! $code ) {
			$code = $_REQUEST['authcode'];
		}

		return $this->is_valid_authcode( $key, $code );
	}
}