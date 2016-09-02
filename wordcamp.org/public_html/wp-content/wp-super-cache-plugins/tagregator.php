<?php

namespace WordCamp\WPSC_Plugins\Tagregator;
defined( 'WPCACHEHOME' ) or die();

add_cacheaction( 'serve_cache_file_init', __NAMESPACE__ . '\prune_tagregator_requests' );

/**
 * Prune cached files for Tagregator requests after 30 seconds.
 *
 * Normally WPSC caches files for `$cache_max_time` (which is currently set to 30 minutes), but Tagregator needs
 * updates in near-real time. We still need to cache them, though, because the server once got overloaded when
 * they were completely uncached.
 */
function prune_tagregator_requests() {
	global $wp_cache_request_uri, $blog_cache_dir, $cache_filename, $wp_cache_rest_prefix;

	if ( false === strpos( $wp_cache_request_uri, "/$wp_cache_rest_prefix/tagregator/" ) ) {
		return;
	}

	$cache_file                  = $blog_cache_dir . $cache_filename;
	$tagregator_cache_expiration = 30; // seconds

	// Prune the file if it's expired
	if ( file_exists( $cache_file ) ) {
		$last_modified_time = filemtime( $cache_file );

		if ( $last_modified_time < time() - $tagregator_cache_expiration ) {
			unlink( $cache_file );
			wp_cache_debug( "Pruned Tagregator cache file because it was older than $tagregator_cache_expiration seconds." );
		}
	}
}
