<?php
/**
 * This plugins overrides some of the default routes of GlotPress.
 *
 * @author ocean90
 */
class GP_WPorg_Routes extends GP_Plugin {
	public $id = 'wporg-routes';

	public function __construct() {
		parent::__construct();
		$this->add_action( 'init' );
	}

	public function init() {
		/*
		 * Unset default routes.
		 * The `routes` filter can't be used, see https://glotpress.trac.wordpress.org/ticket/249.
		 */
		unset( GP::$router->urls['/'] );
		unset( GP::$router->urls['get:/languages'] );

		GP::$router->add( '/', array( 'GP_WPorg_Route_Index', 'index' ) );
		GP::$router->add( '/languages', array( 'GP_WPorg_Route_Locale', 'locales_get' ) );
	}
}

class GP_WPorg_Route_Locale extends GP_Route {

	public function locales_get() {
		$locales = array();
		$existing_locales = GP::$translation_set->existing_locales();
		foreach ( $existing_locales as $locale ) {
			$locales[] = GP_Locales::by_slug( $locale );
		}
		usort( $locales, array( $this, 'sort_locales') );
		unset( $existing_locales );

		$contributors_count = wp_cache_get( 'contributors-count', 'wporg-translate' );
		if ( false === $contributors_count ) {
			$contributors_count = array();
		}

		$translation_status = wp_cache_get( 'translation-status', 'wporg-translate' );
		if ( false === $translation_status ) {
			$translation_status = array();
		}

		$this->tmpl( 'locales', get_defined_vars() );
	}

	private function sort_locales( $a, $b ) {
		return $a->english_name > $b->english_name;
	}
}

class GP_WPorg_Route_Index extends GP_Route {

	public function index() {
		$this->redirect( gp_url( '/languages' ) );
	}
}

GP::$plugins->wporg_routes = new GP_WPorg_Routes;
