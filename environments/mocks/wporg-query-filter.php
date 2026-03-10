<?php
/**
 * Plugin Name: WPORG Query Filter
 * Description: Short-circuits queries to production-only tables that don't exist locally.
 */

namespace WordPressdotorg\Env;

/**
 * Intercept queries to production-only tables and return empty results.
 *
 * Tables filtered: translate_*, trac_*, wp_svn_access, wp_helpscout, wp_helpscout_meta, wporg_locales, language_packs.
 */
add_filter( 'query', function ( $query ) {
	global $wpdb;

	$table = $wpdb->get_table_from_query( $query );
	if ( ! $table ) {
		return $query;
	}

	$blocked_prefixes = [
		'translate_',
		'trac_',
	];

	foreach ( $blocked_prefixes as $prefix ) {
		if ( str_starts_with( $table, $prefix ) ) {
			return '';
		}
	}

	$blocked_tables = [
		'wp_svn_access',
		'wp_helpscout',
		'wp_helpscout_meta',
		'wporg_locales',
		'language_packs',
	];

	if ( in_array( $table, $blocked_tables, true ) ) {
		return '';
	}

	return $query;
} );
