<?php
/**
 * Index Route Class.
 *
 * Provides the route for translate.wordpress.org/.
 */
class WPorg_GP_Route_Index extends GP_Route {

	private $cache_group = 'wporg-translate';

	/**
	 * Prints all exisiting locales as cards.
	 *
	 * Note: Cache gets refreshed via `WPorg_GP_CLI_Update_Caches`.
	 */
	public function get_locales() {
		$existing_locales = wp_cache_get( 'existing-locales', $this->cache_group );
		if ( false === $existing_locales ) {
			$existing_locales = array();
		}

		$locales = array();
		foreach ( $existing_locales as $locale ) {
			$locales[] = GP_Locales::by_slug( $locale );
		}
		usort( $locales, array( $this, '_sort_english_name_callback') );
		unset( $existing_locales );

		$contributors_count = wp_cache_get( 'contributors-count', $this->cache_group );
		if ( false === $contributors_count ) {
			$contributors_count = array();
		}

		$translation_status = wp_cache_get( 'translation-status', $this->cache_group );
		if ( false === $translation_status ) {
			$translation_status = array();
		}

		$this->tmpl( 'index-locales', get_defined_vars() );
	}

	private function _sort_english_name_callback( $a, $b ) {
		return $a->english_name > $b->english_name;
	}
}
