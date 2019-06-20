<?php

namespace WordPressdotorg\GlotPress\Discussion;

use GP;
use GP_Locales;

class Plugin {

	/**
	 * @var Plugin The singleton instance.
	 */
	private static $instance;


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
		add_action( 'plugins_loaded', [ $this, 'plugins_loaded' ] );
	}
}
