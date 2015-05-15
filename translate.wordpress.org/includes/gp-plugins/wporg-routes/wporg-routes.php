<?php
/**
 * This plugins overrides some of the default routes of GlotPress.
 *
 * @author ocean90
 */

require_once __DIR__ . '/routes/index.php';
require_once __DIR__ . '/routes/locale.php';

class GP_WPorg_Routes extends GP_Plugin {
	public $id = 'wporg-routes';

	public function __construct() {
		parent::__construct();
		$this->add_action( 'init' );
	}

	public function init() {
		$locale = '(' . implode( '|', array_map( function( $locale ) { return $locale->slug; }, GP_Locales::locales() ) ) . ')';
		$set_slug = '(' . implode( '|', GP::$translation_set->existing_slugs() ) . ')';

		/*
		 * Unset default routes.
		 * The `routes` filter can't be used, see https://glotpress.trac.wordpress.org/ticket/249.
		 */
		unset( GP::$router->urls['/'] );
		unset( GP::$router->urls["get:/languages/$locale/$set_slug"] );
		unset( GP::$router->urls["get:/languages/$locale"] );
		unset( GP::$router->urls['get:/languages'] );

		GP::$router->add( '/', array( 'GP_WPorg_Route_Index', 'get_index' ) );
		GP::$router->add( '/languages', array( 'GP_WPorg_Route_Locale', 'get_locales' ) );
		GP::$router->add( "/languages/$locale/$set_slug/(.+?)", array( 'GP_WPorg_Route_Locale', 'get_locale_projects' ) );
		GP::$router->add( "/languages/$locale/$set_slug", array( 'GP_WPorg_Route_Locale', 'get_locale_projects' ) );
		GP::$router->add( "/languages/$locale", array( 'GP_WPorg_Route_Locale', 'get_locale_projects' ) );
	}
}

GP::$plugins->wporg_routes = new GP_WPorg_Routes;
