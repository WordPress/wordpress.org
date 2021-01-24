<?php
/**
 * Namespaced functions.
 */

namespace WordPressdotorg\I18nTeams;

use GP_Locales;

/**
 * Inits the plugin.
 */
function bootstrap() {
	add_filter( 'term_link', __NAMESPACE__ . '\link_locales', 10, 3 );

	Locales\bootstrap();
}

/**
 * Links #locale to the teams page.
 *
 * @param string $termlink Term link URL.
 * @param object $term     Term object.
 * @param string $taxonomy Taxonomy slug.
 * @return string URL to teams page of a locale.
 */
function link_locales( $termlink, $term, $taxonomy ) {
	if ( 'post_tag' !== $taxonomy ) {
		return $termlink;
	}

	static $available_locales;

	if ( ! isset( $available_locales ) ) {
		$available_locales = get_locales();
		$available_locales = wp_list_pluck( $available_locales, 'wp_locale' );
		$available_locales = array_flip( $available_locales );
	}

	if ( isset( $available_locales[ $term->name ] ) || isset( $available_locales[ $term->slug ] ) ) {
		return sprintf( 'https://make.wordpress.org/polyglots/teams/?locale=%s', $term->name );
	}

	return $termlink;
}


/**
 * Retrieves GlotPress locales that have a wp_locale, sorted alphabetically.
 *
 * @return array
 */
function get_locales() {
	require_once GLOTPRESS_LOCALES_PATH;

	$locales = GP_Locales::locales();
	$locales = array_filter( $locales, fn( $locale ) => isset( $locale->wp_locale ) );
	unset( $locales['en'] );
	usort( $locales, fn( $a, $b ) => strcmp( $a->english_name, $b->english_name ) );

	return $locales;
}
