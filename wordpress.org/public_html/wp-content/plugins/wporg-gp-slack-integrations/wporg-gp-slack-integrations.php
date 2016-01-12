<?php
/**
 * Plugin name: GlotPress: Slack Integrations
 * Description: Provides Slack integrations like logging translation warnings for translate.wordpress.org.
 * Version:     1.0
 * Author:      WordPress.org
 * Author URI:  http://wordpress.org/
 * License:     GPLv2 or later
 */

require_once __DIR__ . '/integrations/log-warnings.php';

class WPorg_GP_Slack_Integrations {

	/**
	 * Holds the logger for warnings instance.
	 *
	 * @var WPorg_GP_Slack_Log_Warnings
	 */
	public $log_warnings;

	public function __construct() {
		add_action( 'gp_init', array( $this, 'init_integrations' ) );
	}

	/**
	 * Initializes custom Slack integrations.
	 */
	public function init_integrations() {
		$this->log_warnings = new WPorg_GP_Slack_Log_Warnings();
	}
}

function wporg_gp_slack_integrations() {
	global $wporg_gp_slack_integrations;

	if ( ! isset( $wporg_gp_slack_integrations ) ) {
		$wporg_gp_slack_integrations = new WPorg_GP_Slack_Integrations();
	}

	return $wporg_gp_slack_integrations;
}
add_action( 'plugins_loaded', 'wporg_gp_slack_integrations' );
