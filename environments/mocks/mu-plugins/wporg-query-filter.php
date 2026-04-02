<?php
/**
 * Plugin Name: WPORG Query Filter
 * Description: Short-circuits queries to production-only tables that don't exist locally.
 */

namespace WordPressdotorg\Env;

/**
 * Exposes the protected wpdb::get_table_from_query() method.
 */
class Table_Extractor extends \wpdb {
	public function __construct() {
		// No-op: we only need access to get_table_from_query().
	}

	public function get_table( $query ) {
		return $this->get_table_from_query( $query );
	}
}

$table_extractor = new Table_Extractor();

/**
 * Intercept queries to production-only tables and return empty results.
 *
 * Tables filtered: translate_*, trac_*, {prefix}helpscout*, wporg_locales, language_packs.
 */
add_filter( 'query', function ( $query ) use ( $table_extractor ) {
	global $wpdb;

	$table = $table_extractor->get_table( $query );
	if ( ! $table ) {
		return $query;
	}

	// This needs to be an actual query, to prevent WPDB returning $wpdb->last_query.
	$no_op_query = "SELECT * FROM $wpdb->posts WHERE 0=1";

	$blocked_prefixes = [
		'translate_',
		'trac_',
		$wpdb->prefix . 'helpscout',
		'stats_extras',
	];

	foreach ( $blocked_prefixes as $prefix ) {
		if ( str_starts_with( $table, $prefix ) ) {
			return $no_op_query;
		}
	}

	$blocked_tables = [
		'wporg_locales',
		'language_packs',
	];

	if ( in_array( $table, $blocked_tables, true ) ) {
		return $no_op_query;
	}

	// Also block queries that reference missing tables in JOINs (get_table_from_query only returns the primary table).
	foreach ( $blocked_prefixes as $prefix ) {
		if ( str_contains( $query, $prefix ) ) {
			return $no_op_query;
		}
	}

	return $query;
} );
