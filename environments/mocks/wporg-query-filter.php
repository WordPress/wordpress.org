<?php
/**
 * Plugin Name: WPORG Query Filter
 * Description: Short-circuits queries to production-only tables that don't exist locally.
 */

namespace WordPressdotorg\Env;

/**
 * Intercept queries to production-only tables and return empty results.
 *
 * Tables filtered: translate_*, trac_plugins, svn_access, helpscout, helpscout_meta.
 */
add_filter( 'query', function ( $query ) {
	// Normalize whitespace for matching.
	$normalized = preg_replace( '/\s+/', ' ', trim( $query ) );

	$blocked_patterns = [
		'translate_projects',
		'translate_translation_sets',
		'translate_translations',
		'trac_plugins',
		'svn_access',
		'helpscout',
		'wporg_locales',
		'language_packs',
	];

	foreach ( $blocked_patterns as $pattern ) {
		if ( stripos( $normalized, $pattern ) !== false ) {
			return '';
		}
	}

	return $query;
} );
