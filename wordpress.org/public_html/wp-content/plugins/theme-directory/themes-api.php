<?php
/**
 * Adjustments for the Themes API.
 */

/**
 * Updates the update-check cache when a new version of a theme gets approved.
 *
 * @param int    $post_id         Post ID.
 * @param string $current_version The approved theme version.
 */
function wporg_themes_update_check( $post_id, $current_version ) {
	$slug        = get_post( $post_id )->post_name;
	$cache_group = 'theme-update-check';
	wp_cache_add_global_groups( $cache_group );

	wp_cache_set( "themeid:{$slug}", $post_id, $cache_group );
	wp_cache_set( "themevers:{$slug}", $current_version, $cache_group );
}
add_action( 'wporg_themes_update_version_live', 'wporg_themes_update_check', 10, 2 );
