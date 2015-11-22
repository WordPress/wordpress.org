<?php
/**
 * Index Route Class.
 *
 * Provides the route for translate.wordpress.org/.
 */
class WPorg_GP_Route_Index extends GP_Route {

	/**
	 * Prints all exisiting locales as cards.
	 */
	public function get_locales() {
		$locales = array();
		$existing_locales = GP::$translation_set->existing_locales();
		foreach ( $existing_locales as $locale ) {
			$locales[] = GP_Locales::by_slug( $locale );
		}
		usort( $locales, array( $this, '_sort_english_name_callback') );
		unset( $existing_locales );

		$contributors_count = wp_cache_get( 'contributors-count', 'wporg-translate' );
		if ( false === $contributors_count ) {
			$contributors_count = array();
		}

		$translation_status = wp_cache_get( 'translation-status', 'wporg-translate' );
		if ( false === $translation_status ) {
			$translation_status = array();
		}

		$this->tmpl( 'index-locales', get_defined_vars() );
	}

	private function _sort_english_name_callback( $a, $b ) {
		return $a->english_name > $b->english_name;
	}
}
