<?php

namespace WordPressdotorg\GlotPress\Routes\Routes;

use GP_Locales;
use GP_Route;
use WordPressdotorg\GlotPress\Routes\Plugin;

/**
 * Index Route Class.
 *
 * Provides the route for translate.wordpress.org/.
 */
class Index extends GP_Route {

	/**
	 * Prints all exisiting locales as cards.
	 */
	public function get_locales() {
		$existing_locales = Plugin::get_existing_locales();

		$locales = array();
		foreach ( $existing_locales as $locale ) {
			$locales[] = GP_Locales::by_slug( $locale );
		}
		usort( $locales, array( $this, '_sort_english_name_callback' ) );
		unset( $existing_locales );

		$contributors_count = Plugin::get_contributors_count();
		$translation_status = Plugin::get_translation_status();

		$this->tmpl( 'index-locales', get_defined_vars() );
	}

	private function _sort_english_name_callback( $a, $b ) {
		return $a->english_name <=> $b->english_name;
	}
}
