<?php

namespace WordPressdotorg\GlotPress\Theme_Directory;

use WP_CLI;

class Plugin {

	/**
	 * @var Plugin The singleton instance.
	 */
	private static $instance;

	/**
	 *
	 * @var Sync\Translation_Sync
	 */
	public $translation_sync = null;

	/**
	 * Returns always the same instance of this plugin.
	 *
	 * @return Plugin
	 */
	public static function get_instance() {
		if ( ! ( self::$instance instanceof Plugin ) ) {
			self::$instance = new Plugin();
		}
		return self::$instance;
	}

	/**
	 * Instantiates a new Plugin object.
	 */
	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );
	}

	/**
	 * Initializes the plugin.
	 */
	public function plugins_loaded() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$this->register_cli_commands();
		}
	}

	/**
	 * Registers CLI commands if WP-CLI is loaded.
	 */
	function register_cli_commands() {
		WP_CLI::add_command( 'wporg-translate set-theme-project', __NAMESPACE__ . '\CLI\Set_Theme_Project' );
	}
}
