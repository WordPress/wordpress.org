<?php
/**
 * Plugin name: GlotPress: Slack integrations for translate.wordpress.org.
 * Plugin author: dd32, ocean90
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
