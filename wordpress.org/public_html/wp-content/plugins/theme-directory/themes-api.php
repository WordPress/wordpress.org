<?php
/**
 * Adjustments for the Themes API.
 */

// Mark it as a global group whenever the theme directory is loaded.
wp_cache_add_global_groups( 'theme-update-check' );

/**
 * Updates the update-check cache when a new version of a theme gets approved.
 *
 * @param int    $post_id         Post ID.
 * @param string $current_version The approved theme version.
 */
function wporg_themes_update_check( $post_id, $current_version ) {
	$slug = get_post( $post_id )->post_name;

	$theme_meta = array(
		'current_version' => $current_version,
		'requires'        => wporg_themes_get_version_meta( $post_id, '_requires', $current_version ),
		'requires_php'    => wporg_themes_get_version_meta( $post_id, '_requires_php', $current_version ),
	);

	wp_cache_set( $slug, $theme_meta, 'theme-update-check' );

	// Delete the error cache if this theme is new.
	wp_cache_delete( $slug, 'theme_information_error' );
}
add_action( 'wporg_themes_update_version_live', 'wporg_themes_update_check', 10, 2 );

/**
 * Clears the update-check and information caches when a theme is suspended or reinstated.
 *
 * @param int $post_id Post ID.
 */
function wporg_themes_clear_theme_caches( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post || 'repopackage' !== $post->post_type ) {
		return;
	}

	wp_cache_delete( $post->post_name, 'theme-update-check' );
	wp_cache_delete( $post->post_name, 'theme_information_error' );
	wp_cache_delete( 'theme-info:' . $post->post_name, 'theme-info' );
}
add_action( 'suspend_repopackage', 'wporg_themes_clear_theme_caches' );
add_action( 'publish_repopackage', 'wporg_themes_clear_theme_caches' );
