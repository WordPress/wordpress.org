<?php

require_once TWO_FACTOR_DIR . 'providers/class.two-factor-backup-codes.php';

class WPORG_Two_Factor_Backup_Codes extends Two_Factor_Backup_Codes {
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

	public function validate_authentication( $user, $code = '' ) {
		return $this->validate_code( $user, $code );
	}

}