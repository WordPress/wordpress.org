<?php

require_once __DIR__ . '/class-wporg-two-factor-backup-codes.php';

class WPORG_Two_Factor_Secondary extends WPORG_Two_Factor_Backup_Codes { // Temporarily
// class WPORG_Two_Factor_Secondary extends Two_Factor_Provider { // When it's a proper wrapper.

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
		return _x( 'Backup Method', 'Provider Label', 'wporg' );
	}

	// protected $providers = [];

	protected function __construct() {
		/*
		$providers = [
			'WPORG_Two_Factor_Email'        => __DIR__ . '/class-wporg-two-factor-email.php',
			'WPORG_Two_Factor_Backup_Codes' => __DIR__ . '/class-wporg-two-factor-backup-codes.php',
			'WPORG_Two_Factor_Slack'        => __DIR__ . '/class-wporg-two-factor-slack.php'

		];
		*/
		return parent::__construct();
	}
}
