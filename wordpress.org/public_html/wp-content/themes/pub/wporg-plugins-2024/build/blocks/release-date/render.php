<?php
/**
 * Render the release date block.
 *
 * @package wporg-plugins
 */

if ( ! $block->context['postId'] ) {
	return;
}

$release_post = get_post( $block->context['postId'] );
if ( ! $release_post ) {
	return;
}

if ( 'publish' !== $release_post->post_status ) {
	return 3;
}

echo do_blocks( '<!-- wp:post-date {"style":{"spacing":{"margin":{"top":"0","bottom":"0"}}},"fontSize":"extra-small"} /-->' );
