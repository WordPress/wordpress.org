<?php
/**
 * Plugin Name: WordPress.org Theme Directory Download Stats (local stub)
 * Description: Serves theme download counts from seeded post meta. Production
 *              reads these from a bb_themes_stats table that does not exist
 *              locally. (The shared environments/mocks/mu-plugins query filter
 *              already handles other production-only tables such as
 *              wporg_locales.)
 *
 * @package theme-directory-env
 */

/**
 * Shim theme download counts. Production reads from a bb_themes_stats table that
 * does not exist locally; return the count seeded onto the repopackage post.
 *
 * @param string $query Database query.
 * @return string Possibly-rewritten query.
 */
function wporg_themes_env_shim_downloads_query( $query ) {
	global $wpdb;

	if ( preg_match( '!^SELECT.+FROM bb_themes_stats WHERE slug = (.+)$!i', $query, $m ) ) {
		$theme = trim( $m[1], '\'" ' );
		$posts = get_posts( array(
			'post_type'   => 'repopackage',
			'post_status' => 'any',
			'name'        => $theme,
			'numberposts' => 1,
		) );
		$count = $posts ? ( $posts[0]->downloads ?? 0 ) : 0;

		$query = $wpdb->prepare( 'SELECT %d', $count );
	}

	return $query;
}
add_filter( 'query', 'wporg_themes_env_shim_downloads_query' );
