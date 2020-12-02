<?php
namespace WordPressdotorg\Theme_Preview;
/**
 * Plugin Name: Disallow database writes
 * Description: Causes all write-type queries to fail early before even attempting to hit the database server.
 */

// Disable any write queries by performing an empty query instead.
add_filter( 'query', function( $query ) {
	global $wpdb;

	// Borrowed from HyperDB, only SELECT queries are considered non-write
	if ( ! preg_match( '/^\s*(?:SELECT|SHOW|DESCRIBE|DESC|EXPLAIN)\s/i', $query ) ) {
		if (
			! is_admin() ||
			! function_exists( 'is_user_logged_in' ) ||
			! is_user_logged_in()
		) {
			$query = '';
		}
	}

	return $query;
} );

// Disable update_option() directly.
add_filter( 'pre_update_option', function( $value, $option, $old_value ) {
	if (
		! is_admin() ||
		! function_exists( 'is_user_logged_in' ) ||
		! is_user_logged_in()
	) {
		$value = $old_value;
	}

	return $value;
}, 10, 3 );
