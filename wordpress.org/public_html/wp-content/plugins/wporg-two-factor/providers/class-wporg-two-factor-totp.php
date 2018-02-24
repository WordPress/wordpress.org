<?php

require_once TWO_FACTOR_DIR . 'providers/class.two-factor-totp.php';

class WPORG_Two_Factor_Totp extends Two_Factor_Totp {
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

	public function __construct() {
		// Ensure that the Two_Factor_Totp-specific code is added.
		add_action( 'two-factor-user-options-' . __CLASS__, array( $this, 'user_two_factor_options' ) );

		return parent::__construct();
	}

	/**
	 * Returns the name of the provider.
	 */
	public function get_label() {
		return _x( 'Time Based One-Time Password (Google Authenticator, Authy, etc)', 'Provider Label', 'wporg' );
	}
}