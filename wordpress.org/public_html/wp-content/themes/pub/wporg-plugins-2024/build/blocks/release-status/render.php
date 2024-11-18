<?php

$current_post_id = $block->context['postId'];
if ( ! $current_post_id ) {
	return;
}

$post = get_post( $block->context['postId'] );
if ( ! $post ) {
    return;
}
?>

<div <?php echo get_block_wrapper_attributes(); // phpcs:ignore ?>>
<?php echo get_post_status_object($post->post_status)->label; ?>
</div>
