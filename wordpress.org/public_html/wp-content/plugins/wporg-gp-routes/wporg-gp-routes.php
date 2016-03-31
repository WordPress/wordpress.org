<?php
/**
 * Plugin name: GlotPress: Custom Routes
 * Description: Provides custom routes like <code>/locale</code> or <code>/stats</code> for translate.wordpress.org.
 * Version:     1.0
 * Author:      WordPress.org
 * Author URI:  http://wordpress.org/
 * License:     GPLv2 or later
 */

require_once __DIR__ . '/routes/redirector.php';
require_once __DIR__ . '/routes/index.php';
require_once __DIR__ . '/routes/locale.php';
require_once __DIR__ . '/routes/stats-overview.php';
require_once __DIR__ . '/routes/wp-directory.php';
require_once __DIR__ . '/routes/wp-plugins.php';
require_once __DIR__ . '/routes/wp-themes.php';

class WPorg_GP_Routes {

	public function __construct() {
		add_action( 'template_redirect', array( $this, 'register_routes' ), 5 );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$this->register_cli_commands();
		}
	}

	/**
	 * Registers custom routes and removes default routes.
	 *
	 * Removes:
	 *  - API: /languages/$locale
	 *  - /languages/$locale
	 *  - /languages/$locale
	 *  - /languages/$locale/$path
	 *  - /profile/$path
	 *  - /projects/wp-plugins/?
	 *  - /projects/wp-themes/?
	 *
	 * Adds:
	 *  - /
	 *  - /locale/$locale
	 *  - /locale/$locale/$path
	 *  - /locale/$locale/$path/$path
	 *  - /locale/$locale/$path/$path/$path
	 *  - /stats/?
	 *  - /projects/wp-plugins/$project
	 *  - /projects/wp-plugins/$project/contributors
	 *  - /projects/wp-plugins/$project/language-packs
	 *  - /projects/wp-themes/$project
	 *  - /projects/wp-themes/$project/contributors
	 *  - /projects/wp-themes/$project/language-packs
	 */
	public function register_routes() {
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
			GP::$router->remove( "/profile/$path" );

			// Redirect routes.
			GP::$router->prepend( '/languages', array( 'WPorg_GP_Route_Redirector', 'redirect_languages' ) );
			GP::$router->prepend( "/languages/$path", array( 'WPorg_GP_Route_Redirector', 'redirect_languages' ) );
			GP::$router->prepend( '/projects/wp-plugins/?', array( 'WPorg_GP_Route_Redirector', 'redirect_index' ) );
			GP::$router->prepend( '/projects/wp-themes/?', array( 'WPorg_GP_Route_Redirector', 'redirect_index' ) );

			// Register custom routes.
			GP::$router->prepend( '/', array( 'WPorg_GP_Route_Index', 'get_locales' ) );
			GP::$router->prepend( "/locale/$locale", array( 'WPorg_GP_Route_Locale', 'get_locale_projects' ) );
			GP::$router->prepend( "/locale/$locale/$path", array( 'WPorg_GP_Route_Locale', 'get_locale_projects' ) );
			GP::$router->prepend( "/locale/$locale/$path/$path", array( 'WPorg_GP_Route_Locale', 'get_locale_projects' ) );
			GP::$router->prepend( "/locale/$locale/$path/$path/$path", array( 'WPorg_GP_Route_Locale', 'get_locale_project' ) );
			GP::$router->prepend( '/stats/?', array( 'WPorg_GP_Route_Stats', 'get_stats_overview' ) );
			$project = '([^/]*)/?';
			GP::$router->prepend( "/projects/wp-plugins/$project", array( 'WPorg_GP_Route_WP_Plugins', 'get_plugin_projects' ) );
			GP::$router->prepend( "/projects/wp-plugins/$project/contributors", array( 'WPorg_GP_Route_WP_Plugins', 'get_plugin_contributors' ) );
			GP::$router->prepend( "/projects/wp-plugins/$project/language-packs", array( 'WPorg_GP_Route_WP_Plugins', 'get_plugin_language_packs' ) );
			GP::$router->prepend( "/projects/wp-themes/$project", array( 'WPorg_GP_Route_WP_Themes', 'get_theme_projects' ) );
			GP::$router->prepend( "/projects/wp-themes/$project/contributors", array( 'WPorg_GP_Route_WP_Themes', 'get_theme_contributors' ) );
			GP::$router->prepend( "/projects/wp-themes/$project/language-packs", array( 'WPorg_GP_Route_WP_Themes', 'get_theme_language_packs' ) );
		}
	}

	/**
	 * Registers CLI commands if WP-CLI is loaded.
	 */
	function register_cli_commands() {
		require_once __DIR__ . '/cli/update-caches.php';

		WP_CLI::add_command( 'wporg-translate update-cache', 'WPorg_GP_CLI_Update_Caches' );
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
