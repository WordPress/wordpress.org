<?php

namespace WordPressdotorg\Rosetta;

class Plugin {

	/**
	 * @var Plugin The singleton instance.
	 */
	private static $instance;

	/**
	 * Array of sites.
	 *
	 * @var \WordPressdotorg\Rosetta\Site\Site[]
	 */
	private $sites = [];

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

		$this->sites = [
			Site\Global_WordPress_Org::class,
			Site\Translate_WordPress_Org::class,
			Site\Locale_Main::class,
			Site\Locale_Team::class,
			Site\Locale_Support::class,
		];
	}

	/**
	 * Initializes the plugin.
	 */
	public function plugins_loaded() {
		$current_site = get_site( get_current_blog_id() );

		// Site specific customizations.
		foreach ( $this->sites as $site ) {
			if ( $site::test( $current_site ) ) {
				/** @var \WordPressdotorg\Rosetta\Site\Site $site_instance */
				$site_instance = new $site();
				$site_instance->register_events();
				break;
			}
		}

		// Customizations for all sites.
	}
}
