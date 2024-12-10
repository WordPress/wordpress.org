<?php

if ( ! $block->context['postId'] ) {
	return;
}

$release_post = get_post( $block->context['postId'] );
if ( ! $release_post ) {
	return;
}

if ( 'draft' != $release_post->post_status ) {
	return;
}
?>

<div <?php echo wp_kses_data( get_block_wrapper_attributes() ); ?>>
	<?php echo get_post_status_object( $post->post_status )->label; ?>
</div>
