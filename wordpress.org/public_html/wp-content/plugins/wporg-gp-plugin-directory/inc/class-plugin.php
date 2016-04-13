<?php

namespace WordPressdotorg\GlotPress\Plugin_Directory;

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
		$cache_purger = new Cache_Purge\Cache_Purger();
		$cache_purger->register_events();

		$this->translation_sync = new Sync\Translation_Sync();
		$this->translation_sync->register_events();

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$this->register_cli_commands();
		}
	}

	/**
	 * Registers CLI commands if WP-CLI is loaded.
	 */
	function register_cli_commands() {
		WP_CLI::add_command( 'wporg-translate import-plugin-translations', __NAMESPACE__ . '\CLI\Import_Plugin_Translations' );
		WP_CLI::add_command( 'wporg-translate set-plugin-project', __NAMESPACE__ . '\CLI\Set_Plugin_Project' );
		WP_CLI::add_command( 'wporg-translate delete-plugin-project', __NAMESPACE__ . '\CLI\Delete_Plugin_Project' );
	//	WP_CLI::add_command( 'wporg-translate sync-plugin-translations', __NAMESPACE__ . '\CLI\Sync_Plugin_Translations' );
	}
}
