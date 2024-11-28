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

<div <?php echo wp_kses_data( get_block_wrapper_attributes() ); ?>>
	<?php echo get_post_status_object( $post->post_status )->label; ?>
</div>
