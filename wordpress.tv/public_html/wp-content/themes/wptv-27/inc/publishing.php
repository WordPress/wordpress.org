<?php
/**
 * Publishing hooks: view counter initialization, stats, upload space.
 *
 * Ported from the wptv2 WordPressTV_Theme class (WordPress.tv only).
 *
 * @package WordPressTV
 */

namespace WordPressTV\Publishing;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Makes sure every published post has a `wptv_post_views` meta value.
 *
 * Queries that order by views (such as "Popular") use this meta key, and a
 * post without it would be left out of their results.
 *
 * @param int $post_id Post ID.
 */
function init_post_views( $post_id ) {
	if ( ! get_post_meta( $post_id, 'wptv_post_views', true ) ) {
		update_post_meta( $post_id, 'wptv_post_views', 0 );
	}
}
add_action( 'publish_post', __NAMESPACE__ . '\init_post_views' );

/**
 * Counts newly published videos in WordPress.com stats.
 *
 * The bump_stats_extras() function only exists on WordPress.com. wptv2 defined an empty
 * global stub elsewhere; a function_exists() guard does the same job.
 *
 * @param string $new_status New post status.
 * @param string $old_status Old post status.
 */
function bump_publish_stats( $new_status, $old_status ) {
	if ( 'publish' !== $new_status || 'publish' === $old_status ) {
		return;
	}

	if ( function_exists( 'bump_stats_extras' ) ) {
		bump_stats_extras( 'wptv-activity', 'publish-video' );
	}
}
add_action( 'transition_post_status', __NAMESPACE__ . '\bump_publish_stats', 10, 2 );

/**
 * Raises the upload quota, since videos are uploaded to the media library.
 *
 * The blog_upload_space option is in megabytes: 1024 * 1024 * 25 MB = 25 TB.
 *
 * @return int
 */
function upload_space() {
	return 1024 * 1024 * 25;
}
add_filter( 'pre_option_blog_upload_space', __NAMESPACE__ . '\upload_space' );
