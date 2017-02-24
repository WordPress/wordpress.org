<?php

namespace WordPressdotorg\GlotPress\Routes;

use GP;
use GP_Locales;
use WP_CLI;

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

	/**
	 * Initializes the plugin.
	 */
	public function plugins_loaded() {
		if ( file_exists( WPORGPATH . 'extend/plugins-plugins/_plugin-icons.php' ) ) {
			include_once WPORGPATH . 'extend/plugins-plugins/_plugin-icons.php';
		}

		add_action( 'template_redirect', [ $this, 'register_routes' ], 5 );
		add_filter( 'gp_locale_glossary_path_prefix', [ $this, 'set_locale_glossary_path_prefix' ] );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$this->register_cli_commands();
		}
	}

	/**
	 * Changes the path prefix for a locale glossary to '/locale'.
	 *
	 * @return string '/locale'
	 */
	public function set_locale_glossary_path_prefix() {
		return '/locale';
	}

	/**
	 * Registers custom routes and removes default routes.
	 *
	 * Removes:
	 *  - API: /languages/$locale
	 *  - /languages/$locale
	 *  - /languages/$locale
	 *  - /languages/$locale/$path
	 *  - (/languages)/$locale/$dir/glossary
	 *  - (/languages)/$locale/$dir/glossary/-new
	 *  - (/languages)/$locale/$dir/glossary/-delete
	 *  - (/languages)/$locale/$dir/glossary/-export
	 *  - (/languages)/$locale/$dir/glossary/-import
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
	 *  - (/locale)/$locale/$dir/glossary
	 *  - (/locale)/$locale/$dir/glossary/-new
	 *  - (/locale)/$locale/$dir/glossary/-delete
	 *  - (/locale)/$locale/$dir/glossary/-export
	 *  - (/locale)/$locale/$dir/glossary/-import
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
		$dir = '([^_/][^/]*)';
		$project = '([^/]*)/?';

		$locale = '(' . implode( '|', array_map( function( $locale ) { return $locale->slug; }, GP_Locales::locales() ) ) . ')';

		if ( gp_startswith( $request_uri, '/' . GP::$router->api_prefix . '/' ) ) { // API requests.
			// Delete default routes.
			GP::$router->remove( "/languages/$locale" );
		} else {
			// Delete default routes.
			GP::$router->remove( "/languages/$locale" );
			GP::$router->remove( "/languages/$locale/$path" );
			GP::$router->remove( "(/languages)/$locale/$dir/glossary" );
			GP::$router->remove( "(/languages)/$locale/$dir/glossary", 'post' );
			GP::$router->remove( "(/languages)/$locale/$dir/glossary/-new", 'post' );
			GP::$router->remove( "(/languages)/$locale/$dir/glossary/-delete", 'post' );
			GP::$router->remove( "(/languages)/$locale/$dir/glossary/-export" );
			GP::$router->remove( "(/languages)/$locale/$dir/glossary/-import" );
			GP::$router->remove( "(/languages)/$locale/$dir/glossary/-import", 'post' );

			GP::$router->remove( '/profile' );
			GP::$router->remove( "/profile/$path" );

			// Redirect routes.
			GP::$router->prepend( '/languages', array( __NAMESPACE__ . '\Routes\Redirector', 'redirect_languages' ) );
			GP::$router->prepend( "/languages/$path", array( __NAMESPACE__ . '\Routes\Redirector', 'redirect_languages' ) );
			GP::$router->prepend( '/projects/wp-plugins/?', array( __NAMESPACE__ . '\Routes\Redirector', 'redirect_index' ) );
			GP::$router->prepend( '/projects/wp-themes/?', array( __NAMESPACE__ . '\Routes\Redirector', 'redirect_index' ) );
			GP::$router->prepend( "/projects/wp/$path/$locale/$dir/glossary", array( __NAMESPACE__ . '\Routes\Redirector', 'redirect_old_glossary' ) );

			// Register custom routes.
			GP::$router->prepend( '/', array( __NAMESPACE__ . '\Routes\Index', 'get_locales' ) );
			GP::$router->prepend( "/locale/$locale", array( __NAMESPACE__ . '\Routes\Locale', 'get_locale_projects' ) );
			GP::$router->prepend( "/locale/$locale/$path", array( __NAMESPACE__ . '\Routes\Locale', 'get_locale_projects' ) );
			GP::$router->prepend( "/locale/$locale/$path/$path", array( __NAMESPACE__ . '\Routes\Locale', 'get_locale_projects' ) );
			GP::$router->prepend( "/locale/$locale/$path/$path/$path", array( __NAMESPACE__ . '\Routes\Locale', 'get_locale_project' ) );
			GP::$router->prepend( "(/locale)/$locale/$dir/glossary", array( 'GP_Route_Glossary_Entry', 'glossary_entries_get' ) );
			GP::$router->prepend( "(/locale)/$locale/$dir/glossary", array( 'GP_Route_Glossary_Entry', 'glossary_entries_post' ), 'post' );
			GP::$router->prepend( "(/locale)/$locale/$dir/glossary/-new", array( 'GP_Route_Glossary_Entry', 'glossary_entry_add_post' ), 'post' );
			GP::$router->prepend( "(/locale)/$locale/$dir/glossary/-delete", array( 'GP_Route_Glossary_Entry', 'glossary_entry_delete_post' ), 'post' );
			GP::$router->prepend( "(/locale)/$locale/$dir/glossary/-export", array( 'GP_Route_Glossary_Entry', 'export_glossary_entries_get' ) );
			GP::$router->prepend( "(/locale)/$locale/$dir/glossary/-import", array( 'GP_Route_Glossary_Entry', 'import_glossary_entries_get' ) );
			GP::$router->prepend( "(/locale)/$locale/$dir/glossary/-import", array( 'GP_Route_Glossary_Entry', 'import_glossary_entries_post' ), 'post' );
			GP::$router->prepend( '/stats', array( __NAMESPACE__ . '\Routes\Stats', 'get_stats_overview' ) );
			GP::$router->prepend( '/consistency', array( __NAMESPACE__ . '\Routes\Consistency', 'get_search_form' ) );

			// Project routes.
			GP::$router->prepend( "/projects/wp-plugins/$project", array( __NAMESPACE__ . '\Routes\WP_Plugins', 'get_plugin_projects' ) );
			GP::$router->prepend( "/projects/wp-plugins/$project/contributors", array( __NAMESPACE__ . '\Routes\WP_Plugins', 'get_plugin_contributors' ) );
			GP::$router->prepend( "/projects/wp-plugins/$project/language-packs", array( __NAMESPACE__ . '\Routes\WP_Plugins', 'get_plugin_language_packs' ) );
			GP::$router->prepend( "/projects/wp-themes/$project", array( __NAMESPACE__ . '\Routes\WP_Themes', 'get_theme_projects' ) );
			GP::$router->prepend( "/projects/wp-themes/$project/contributors", array( __NAMESPACE__ . '\Routes\WP_Themes', 'get_theme_contributors' ) );
			GP::$router->prepend( "/projects/wp-themes/$project/language-packs", array( __NAMESPACE__ . '\Routes\WP_Themes', 'get_theme_language_packs' ) );

			if ( defined( 'TRANSLATE_MAINTENANCE_ACTIVE' ) ) {
				GP::$router->prepend( '.*', array( __NAMESPACE__ . '\Routes\Maintenance', 'show_maintenance_message' ) );
			}
		}
	}

	/**
	 * Registers CLI commands if WP-CLI is loaded.
	 */
	function register_cli_commands() {
		WP_CLI::add_command( 'wporg-translate update-cache', __NAMESPACE__ . '\CLI\Update_Caches' );
	}
}
