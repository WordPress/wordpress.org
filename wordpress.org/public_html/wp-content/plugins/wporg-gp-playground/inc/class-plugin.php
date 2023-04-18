<?php

namespace WordPressdotorg\GlotPress\Playground;

use GP;
use GP_Locales;

class Plugin {

	/**
	 * @var Plugin The singleton instance.
	 */
	private static $instance;

	/**
	 * @var array
	 */
	private $queue = array();

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
		add_action( 'template_redirect', array( $this, 'register_routes' ), 5 );
	}

	/**
	 * Registers custom routes.
	 */
	public function register_routes() {
		$dir      = '([^_/][^/]*)';
		$path     = '(.+?)';
		$projects = 'projects';
		$project  = $projects . '/' . $path;
		$locale   = '(' . implode( '|', wp_list_pluck( GP_Locales::locales(), 'slug' ) ) . ')';
		$set      = "$project/$locale/$dir";

		GP::$router->prepend( "/$set/playground", array( __NAMESPACE__ . '\Routes\Route', 'playground' ) );
		GP::$router->prepend( '/plugin-proxy', array( __NAMESPACE__ . '\Routes\Route', 'plugin_proxy' ) );
	}
}
