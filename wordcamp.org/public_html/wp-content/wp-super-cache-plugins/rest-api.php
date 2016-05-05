<?php

namespace WordCamp\WPSC_Plugins\REST_API;
defined( 'WPCACHEHOME' ) or die();

add_cacheaction( 'cache_init', __NAMESPACE__ . '\prune_tagregator_requests' );

/**
 * Prune cached files for Tagregator requests after 30 seconds.
 *
 * Normally WPSC caches files for `$cache_max_time` (which is currently set to 30 minutes), but Tagregator needs
 * updates in near-real time. We still need to cache them, though, because the server once got overloaded when
 * they were all uncached.
 *
 * @todo After upgrading to v2 of REST API, it would be faster to use strpos() instead of preg_match(). That might
 * be worth it since this runs on every request, including cached ones.
 */
function prune_tagregator_requests() {
	global $blog_cache_dir, $wp_cache_request_uri, $wp_cache_gzip_encoding;

	$tagregator_request_pattern = '#^(\/wp-json).*(\/posts).*(type\[\]=tggr-)#'; // matches v1 and v2 of the REST API

	if ( 1 !== preg_match( $tagregator_request_pattern, urldecode( $_SERVER['REQUEST_URI'] ) ) ) {
		return;
	}
	
	/*
	 * The `cache_init` action is too early for this, but the others are too late, so we have to mimic some WPSC
	 * code from `wp-cache-phase1.php` here, in order to accurately derive `$cache_file`.
	 *
	 * The request URI was decoded above to make the matching regex more intuitive, but it's left encoded here
	 * because `$cache_file` is partially derived from the request URI, so changing it would cause a mismatch
	 * between the filename we generate and the filename that WPSC generates.
	 */
	$wp_cache_request_uri        = $_SERVER['REQUEST_URI'];
	$wp_cache_gzip_encoding      = '';
	$init_data                   = wp_super_cache_init();
	$cache_file                  = $blog_cache_dir . $init_data['cache_filename'];
	$tagregator_cache_expiration = 30; // seconds

	// Prune the file if it's expired
	if ( file_exists( $cache_file ) ) {
		$last_modified_time = filemtime( $cache_file );

		if ( $last_modified_time < time() - $tagregator_cache_expiration ) {
			unlink( $cache_file );
		}
	}
}
