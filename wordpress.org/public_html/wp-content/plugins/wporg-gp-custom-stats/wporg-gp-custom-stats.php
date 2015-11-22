<?php
/**
 * Plugin name: GlotPress: Custom stats for translate.wordpress.org.
 * Plugin author: dd32
 */

require_once __DIR__ . '/stats/user.php';
require_once __DIR__ . '/stats/project.php';

class WPorg_GP_Custom_Stats {

	public $user;
	public $project;

	public function __construct() {
		add_action( 'gp_init', array( $this, 'init_stats' ) );
	}

	public function init_stats() {

		$this->user    = new WPorg_GP_User_Stats();
		$this->project = new WPorg_GP_Project_Stats();
	}
}

function wporg_gp_custom_stats() {
	global $wporg_gp_custom_stats;

	if ( ! isset( $wporg_gp_custom_stats ) ) {
		$wporg_gp_custom_stats = new WPorg_GP_Custom_Stats();
	}

	return $wporg_gp_custom_stats;
}
add_action( 'plugins_loaded', 'wporg_gp_custom_stats' );
