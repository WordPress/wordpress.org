<?php

if ( ! $block->context['postId'] ) {
	return;
}

$post = get_post( $block->context['postId'] );
if ( ! $post ) {
	return;
}

if( 'draft' !== $post->post_status ) {
	return;
}

?>

<div <?php echo get_block_wrapper_attributes(); // phpcs:ignore ?>>
	<?php echo get_post_status_object( $post->post_status )->label; ?>
</div>
