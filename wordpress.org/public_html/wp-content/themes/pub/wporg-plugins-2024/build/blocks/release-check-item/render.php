<?php

$wrapper_attributes = get_block_wrapper_attributes(
	[
		'class' => $block->attributes['status'],
	]
);


// $copy = '';

if ( ! empty( $block->inner_html ) ) {
	var_dump( $block->inner_html );
}

?>

<li <?php echo wp_kses_data( $wrapper_attributes ); // phpcs:ignore ?>>
	<details>
		<summary>
			<?php if ( 'error' === $block->attributes['status'] ) : ?>
				<svg xmlns="http://www.w3.org/2000/svg" fill="#cf2e2e" viewBox="0 0 24 24" width="34" height="34" aria-hidden="true" focusable="false">
					<path d="M12 13.06l3.712 3.713 1.061-1.06L13.061 12l3.712-3.712-1.06-1.06L12 10.938 8.288 7.227l-1.061 1.06L10.939 12l-3.712 3.712 1.06 1.061L12 13.061z"></path>
				</svg>
			<?php elseif ( 'warning' === $block->attributes['status']  ) : ?>	
				<svg xmlns="http://www.w3.org/2000/svg" fill="#b7b35c" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false">
					<path d="M12 3.2c-4.8 0-8.8 3.9-8.8 8.8 0 4.8 3.9 8.8 8.8 8.8 4.8 0 8.8-3.9 8.8-8.8 0-4.8-4-8.8-8.8-8.8zm0 16c-4 0-7.2-3.3-7.2-7.2C4.8 8 8 4.8 12 4.8s7.2 3.3 7.2 7.2c0 4-3.2 7.2-7.2 7.2zM11 17h2v-6h-2v6zm0-8h2V7h-2v2z"></path>
				</svg>
			<?php endif; ?>
		</summary>
	</details>

</li>
