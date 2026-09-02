<?php
/**
 * Stands in for the wporg locales mu-plugin, which this environment does not
 * install. `get_locale_slug()` reads nothing but the `slug` property.
 *
 * @package WordPressdotorg\Openverse\Theme\Tests
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Locales;

if ( ! function_exists( __NAMESPACE__ . '\get_locales' ) ) {
	/**
	 * The locales the theme's tests refer to.
	 *
	 * @return object[] Keyed by WP locale.
	 */
	function get_locales(): array {
		return array(
			'ru_RU' => (object) array( 'slug' => 'ru' ),
		);
	}
}
