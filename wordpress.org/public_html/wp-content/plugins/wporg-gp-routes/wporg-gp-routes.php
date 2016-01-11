<?php
/**
 * Plugin name: GlotPress: Register custom routes for translate.wordpress.org.
 * Plugin author: ocean90, obenland, dd32
 */

require_once __DIR__ . '/routes/redirector.php';
require_once __DIR__ . '/routes/index.php';
require_once __DIR__ . '/routes/locale.php';
require_once __DIR__ . '/routes/stats-overview.php';
require_once __DIR__ . '/routes/wp-plugins.php';

class WPorg_GP_Routes {

	public function __construct() {
		add_action( 'init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		$request_uri = GP::$router->request_uri();
		$path = '(.+?)';
		$locale = '(' . implode( '|', array_map( function( $locale ) { return $locale->slug; }, GP_Locales::locales() ) ) . ')';

		if ( gp_startswith( $request_uri, '/' . GP::$router->api_prefix . '/' ) ) { // API requests.
			// Delete default routes.
			GP::$router->remove("/languages/$locale");
		} else {
			// Delete default routes.
			GP::$router->remove("/languages/$locale");
			GP::$router->remove("/languages/$locale/$path");

			// Redirect routes.
			GP::$router->prepend( '/languages', array( 'WPorg_GP_Route_Redirector', 'redirect_languages' ) );
			GP::$router->prepend( "/languages/$path", array( 'WPorg_GP_Route_Redirector', 'redirect_languages' ) );

			// Register custom routes.
			GP::$router->prepend( '/', array( 'WPorg_GP_Route_Index', 'get_locales' ) );
			GP::$router->prepend( "/locale/$locale", array( 'WPorg_GP_Route_Locale', 'get_locale_projects' ) );
			GP::$router->prepend( "/locale/$locale/$path", array( 'WPorg_GP_Route_Locale', 'get_locale_projects' ) );
			GP::$router->prepend( "/locale/$locale/$path/$path", array( 'WPorg_GP_Route_Locale', 'get_locale_projects' ) );
			GP::$router->prepend( "/locale/$locale/$path/$path/$path", array( 'WPorg_GP_Route_Locale', 'get_locale_project' ) );
			GP::$router->prepend( '/stats/?', array( 'WPorg_GP_Route_Stats', 'get_stats_overview' ) );
			$project = '([^/]*)/?';
			GP::$router->prepend( "/projects/wp-plugins/$project", array( 'WPorg_GP_Route_WP_Plugins', 'get_plugin_projects' ) );
		}
	}
}

function wporg_gp_routes() {
	global $wporg_gp_routes;

	if ( ! isset( $wporg_gp_routes ) ) {
		$wporg_gp_routes = new WPorg_GP_Routes();
	}

	return $wporg_gp_routes;
}
add_action( 'plugins_loaded', 'wporg_gp_routes' );
