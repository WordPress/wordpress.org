<?php
/**
 * Register custom routes for translate.wordpress.org.
 *
 * @author ocean90
 */

require_once __DIR__ . '/routes/redirector.php';
require_once __DIR__ . '/routes/index.php';
require_once __DIR__ . '/routes/locale.php';

class GP_WPorg_Routes extends GP_Plugin {
	public $id = 'wporg-routes';

	public function __construct() {
		parent::__construct();
		$this->add_action( 'init' );
	}

	public function init() {
		// Bail for API requests.
		$request_uri = GP::$router->request_uri();
		if ( gp_startswith( $request_uri, '/' . GP::$router->api_prefix . '/' ) ) {
			return;
		}

		$path = '(.+?)';
		$locale = '(' . implode( '|', array_map( function( $locale ) { return $locale->slug; }, GP_Locales::locales() ) ) . ')';

		// Unset default routes.
		unset( GP::$router->urls['/'] );
		unset( GP::$router->urls["get:/languages/$locale/$path"] );
		unset( GP::$router->urls["get:/languages/$locale"] );
		unset( GP::$router->urls['get:/languages'] );

		// Redirect routes.
		GP::$router->add( "/languages/$path", array( 'GP_WPorg_Route_Redirector', 'redirect_languages' ) );
		GP::$router->add( '/languages', array( 'GP_WPorg_Route_Redirector', 'redirect_languages' ) );

		// Register custom routes.
		GP::$router->add( '/', array( 'GP_WPorg_Route_Index', 'get_locales' ) );
		GP::$router->add( "/locale/$locale/$path/$path/$path", array( 'GP_WPorg_Route_Locale', 'get_locale_project' ) );
		GP::$router->add( "/locale/$locale/$path/$path", array( 'GP_WPorg_Route_Locale', 'get_locale_projects' ) );
		GP::$router->add( "/locale/$locale/$path", array( 'GP_WPorg_Route_Locale', 'get_locale_projects' ) );
		GP::$router->add( "/locale/$locale", array( 'GP_WPorg_Route_Locale', 'get_locale_projects' ) );
	}
}

GP::$plugins->wporg_routes = new GP_WPorg_Routes;
