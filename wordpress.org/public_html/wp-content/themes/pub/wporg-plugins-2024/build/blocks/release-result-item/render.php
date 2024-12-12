<?php
/**
 * Block Name: Release Result Item
 * Description: A block to display release result item view.
 *
 * @package wporg
 */

?>

<li <?php echo wp_kses_data( get_block_wrapper_attributes() ); ?>>
	<div>
		<?php if ( 'error' === $block->attributes['status'] ) : ?>
			<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
				<circle cx="12" cy="12" r="8.25" stroke="#E26F56" stroke-width="1.5"/>
				<rect x="16" y="15.0176" width="1.3894" height="9.92426" transform="rotate(135 16 15.0176)" fill="#E26F56"/>
				<rect x="15.0175" y="8" width="1.3894" height="9.92426" transform="rotate(45 15.0175 8)" fill="#E26F56"/>
			</svg>
			<?php elseif ( 'warning' === $block->attributes['status'] ) : ?>
				<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
					<path fill-rule="evenodd" clip-rule="evenodd" d="M19.6 12C19.6 16.1974 16.1974 19.6 12 19.6C7.80264 19.6 4.4 16.1974 4.4 12C4.4 7.80264 7.80264 4.4 12 4.4C16.1974 4.4 19.6 7.80264 19.6 12ZM21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12ZM12.6789 14.093L12.8067 6.91631H11.1884L11.3163 14.093H12.6789ZM11.3016 16.7984C11.495 16.9886 11.7279 17.0837 12 17.0837C12.1771 17.0837 12.3394 17.0394 12.487 16.9509C12.6346 16.8624 12.7526 16.7443 12.8412 16.5967C12.933 16.4459 12.9805 16.2803 12.9838 16.0999C12.9805 15.831 12.8822 15.6015 12.6887 15.4113C12.4952 15.2178 12.2657 15.1211 12 15.1211C11.7279 15.1211 11.495 15.2178 11.3016 15.4113C11.1081 15.6015 11.013 15.831 11.0163 16.0999C11.013 16.3721 11.1081 16.6049 11.3016 16.7984Z" fill="#B7B35B"/>
				</svg>
			<?php elseif ( 'success' === $block->attributes['status'] ) : ?>
				<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
					<path d="M8.25 13L10.8785 15L15.5 9" stroke="#008A20" stroke-width="1.5"/>
					<circle cx="12" cy="12" r="8.25" stroke="#008A20" stroke-width="1.5"/>
				</svg>
			<?php else : ?>
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><path d="M16.7 7.1l-6.3 8.5-3.3-2.5-.9 1.2 4.5 3.4L17.9 8z"></path></svg>
			<?php endif; ?>
	</div>

	<?php echo wp_kses_post( $block->inner_html ); ?>
</li>
