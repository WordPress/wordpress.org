<?php
/**
 * Register custom routes for translate.wordpress.org.
 *
 * @author ocean90, obenland, dd32
 */

require_once __DIR__ . '/routes/redirector.php';
require_once __DIR__ . '/routes/index.php';
require_once __DIR__ . '/routes/locale.php';
require_once __DIR__ . '/routes/stats-overview.php';
require_once __DIR__ . '/routes/wp-plugins.php';

class GP_WPorg_Routes extends GP_Plugin {
	public $id = 'wporg-routes';

	public function __construct() {
		parent::__construct();
		$this->add_action( 'init' );
	}

	public function init() {
		$request_uri = GP::$router->request_uri();
		$path = '(.+?)';
		$locale = '(' . implode( '|', array_map( function( $locale ) { return $locale->slug; }, GP_Locales::locales() ) ) . ')';

		if ( gp_startswith( $request_uri, '/' . GP::$router->api_prefix . '/' ) ) { // API requests.
			// Delete default routes.
			GP::$router->remove( "/languages/$locale" );
		} else {
			// Delete default routes.
			GP::$router->remove( "/languages/$locale" );
			GP::$router->remove( "/languages/$locale/$path" );

			// Redirect routes.
			GP::$router->prepend( '/languages', array( 'GP_WPorg_Route_Redirector', 'redirect_languages' ) );
			GP::$router->prepend( "/languages/$path", array( 'GP_WPorg_Route_Redirector', 'redirect_languages' ) );
			GP::$router->prepend( '/projects/wp-plugins/?', array( 'GP_WPorg_Route_Redirector', 'redirect_index' ) );
			GP::$router->prepend( '/projects/wp-themes/?', array( 'GP_WPorg_Route_Redirector', 'redirect_index' ) );

			// Register custom routes.
			GP::$router->prepend( '/', array( 'GP_WPorg_Route_Index', 'get_locales' ) );
			GP::$router->prepend( "/locale/$locale", array( 'GP_WPorg_Route_Locale', 'get_locale_projects' ) );
			GP::$router->prepend( "/locale/$locale/$path", array( 'GP_WPorg_Route_Locale', 'get_locale_projects' ) );
			GP::$router->prepend( "/locale/$locale/$path/$path", array( 'GP_WPorg_Route_Locale', 'get_locale_projects' ) );
			GP::$router->prepend( "/locale/$locale/$path/$path/$path", array( 'GP_WPorg_Route_Locale', 'get_locale_project' ) );
			GP::$router->prepend( '/stats/?', array( 'GP_WPorg_Route_Stats', 'get_stats_overview' ) );
			$project = '([^/]*)/?';
			GP::$router->prepend( "/projects/wp-plugins/$project", array( 'GP_WPorg_Route_WP_Plugins', 'get_plugin_projects' ) );
		}
	}
}

GP::$plugins->wporg_routes = new GP_WPorg_Routes;
