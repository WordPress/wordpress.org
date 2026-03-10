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
 * Tables filtered: translate_*, trac_*, {prefix}svn_access, {prefix}helpscout*, wporg_locales, language_packs.
 */
add_filter( 'query', function ( $query ) use ( $table_extractor ) {
	global $wpdb;

	$table = $table_extractor->get_table( $query );
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
		$wpdb->prefix . 'svn_access',
		$wpdb->prefix . 'helpscout',
		$wpdb->prefix . 'helpscout_meta',
		'wporg_locales',
		'language_packs',
	];

	if ( in_array( $table, $blocked_tables, true ) ) {
		return '';
	}

	return $query;
} );
