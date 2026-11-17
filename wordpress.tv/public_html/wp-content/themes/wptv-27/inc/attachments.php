<?php
/**
 * Keeps the video attachment title in sync with its post title.
 *
 * The attachment title is shown in VideoPress embeds and thumbnails.
 * Ported from wptv2's wptv_update_attachment_titles(), loaded on every site
 * (see https://meta.trac.wordpress.org/ticket/4667).
 *
 * @package WordPressTV
 */

namespace WordPressTV\Attachments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Copies the post title to its only attachment, unless the attachment title was set by hand.
 *
 * @param int      $post_id     Post ID.
 * @param \WP_Post $post        Post after the update.
 * @param \WP_Post $post_before Post before the update.
 */
function sync_title( $post_id, $post, $post_before ) {
	if ( 'post' !== $post->post_type ) {
		return;
	}

	$attachments = get_posts(
		array(
			'post_type'   => 'attachment',
			'post_parent' => $post_id,
		)
	);

	// Only posts with exactly one attachment are handled.
	if ( ! $attachments || count( $attachments ) > 1 ) {
		return;
	}
	$attachment = $attachments[0];

	if ( $post->post_title === $attachment->post_title ) {
		return;
	}

	// Update only a default (file name) title, or one that matched the previous post title.
	$is_default_title  = sanitize_file_name( $attachment->post_title ) === $attachment->post_title;
	$was_synced_before = $attachment->post_title === $post_before->post_title;
	if ( ! $is_default_title && ! $was_synced_before ) {
		return;
	}

	$attachment->post_title = $post->post_title;
	wp_update_post( $attachment );
}
add_action( 'post_updated', __NAMESPACE__ . '\sync_title', 10, 3 );
