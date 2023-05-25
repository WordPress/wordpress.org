<?php
namespace WordPressdotorg\MU_Plugin\Cache_wp_count_posts;
/**
 * Plugin Name: Add caching to wp_count_posts() on the front-end.
 * Description: The 'counts' group is a non-persistent cache group, this adds some caching to wp_count_posts() outside of admin contexts.
 */

// Don't cache in the admin.
if ( is_admin() ) {
	return;
}

/**
 * Pre-set any cached 'counts' for this site (The 'counts' group is non-persistent)
 *
 * The cache item will be empty, unless wp_count_posts() is used on the front-end.
 */
function init() {
	$caches = wp_cache_get( cache_key(), 'wp_count_posts' ) ?: [];

	foreach ( $caches as $cache_key => $counts ) {
		wp_cache_set( $cache_key, $counts, 'counts' );
	}
}
add_action( 'init', __NAMESPACE__ . '\init', 8 );

/**
 * Filter wp_count_posts() and store any new values in the cache for the next page load.
 *
 * If the $perm is set to anything non-falsey and the user is logged in, the value will not be cached.
 * 
 * @param stdClass $counts An object containing the current post_type's post
 *                         counts by status.
 * @param string   $type   Post type.
 * @param string   $perm   The permission to determine if the posts are 'readable'
 *                         by the current user.
 * @return stdClass $counts unchanged.
 */
function filter_wp_count_posts( $counts, $post_type, $perm ) {
	// $perm is permission, and ends up being per-user specific caches. Don't cache those.
	if ( $perm && is_user_logged_in() ) {
		return $counts;
	}

	// This function is marked private, and may be removed in the future.
	if ( ! function_exists( '_count_posts_cache_key' ) ) {
		return $counts;
	}

	$cache_key = _count_posts_cache_key( $post_type, $perm );
	$caches    = wp_cache_get( cache_key(), 'wp_count_posts' ) ?: [];

	if ( empty( $caches[ $cache_key ] ) ) {
		$caches[ $cache_key ] = $counts;
		wp_cache_set( cache_key(), $caches, 'wp_count_posts', HOUR_IN_SECONDS );	
	}

	return $counts;
}
add_filter( 'wp_count_posts', __NAMESPACE__ . '\filter_wp_count_posts', 20, 3 );

/**
 * Generate the cache key to look in for the wp_count_posts() cache.
 *
 * We're keying by the posts last_modified date to avoid needing to hook to post_status change events.
 */
function cache_key() {
	return wp_cache_get_last_changed( 'posts' ) . 'wp_count_posts';
}