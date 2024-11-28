<?php

if ( ! $block->context['postId'] ) {
	return;
}

$post = get_post( $block->context['postId'] );
if ( ! $post ) {
	return;
}

if( 'publish' !== $post->post_status ) {
	return;
}

echo do_blocks( '<!-- wp:post-date {"style":{"spacing":{"margin":{"top":"0","bottom":"0"}}},"fontSize":"extra-small"} /-->' );
