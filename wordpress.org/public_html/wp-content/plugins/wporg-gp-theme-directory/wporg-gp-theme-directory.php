<?php
/**
 * Plugin name: GlotPress: Theme Directory Bridge
 * Description: Provides CLI commands to import themes into translate.wordpress.org or to mark themes as inactive.
 * Version:     1.0
 * Author:      WordPress.org
 * Author URI:  http://wordpress.org/
 * License:     GPLv2 or later
 */

class WPorg_GP_Theme_Directory {
	public $master_project   = 'wp-themes';

	public function __construct() {

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$this->register_cli_commands();
		}
	}

	/**
	 * Registers CLI commands if WP-CLI is loaded.
	 */
	function register_cli_commands() {
		require_once __DIR__ . '/cli/set-theme-project.php';

		WP_CLI::add_command( 'wporg-translate set-theme-project', 'WPorg_GP_CLI_Set_Theme_Project' );
	}
}

function wporg_gp_theme_directory() {
	global $wporg_gp_theme_directory;

	if ( ! isset( $wporg_gp_theme_directory ) ) {
		$wporg_gp_theme_directory = new WPorg_GP_Theme_Directory();
	}

	return $wporg_gp_theme_directory;
}
add_action( 'plugins_loaded', 'wporg_gp_theme_directory' );
