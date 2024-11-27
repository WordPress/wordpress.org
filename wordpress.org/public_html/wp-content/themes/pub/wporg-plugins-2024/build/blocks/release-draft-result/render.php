<?php

if ( ! isset( $block->attributes['text'] ) || ! isset( $block->attributes['type'] ) ) {
	return;
}

?>

<li <?php echo get_block_wrapper_attributes(); // phpcs:ignore ?>>
	<?php echo wp_kses_post( $block->attributes['text'] ) ?>
</li>