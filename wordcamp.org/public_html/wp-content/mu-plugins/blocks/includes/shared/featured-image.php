<?php

namespace WordCamp\Blocks\Shared\Components;

/**
 * Provides render backend for FeaturedImage component.
 *
 * @param array    $class_names        Additional classes to add inside <img> tag.
 * @param \WP_Post $post               Current post object. This will be used to calculate srcset attribute.
 * @param int      $width              Width of the image.
 *
 * @return string Output markup for featured image.
 */
function render_featured_image( $class_names, $post, $width, $image_link = '' ) {
	$class_names[]     = 'wordcamp-featured-image';
	$class_names       = implode( ' ', $class_names );
	$container_classes = "wordcamp-image-container wordcamp-featured-image-container $class_names";
	$attachment_id     = get_post_thumbnail_id( $post->ID );
	$image_data        = wp_get_attachment_metadata( $attachment_id );
	$size              = 'post-thumbnail';

	if ( is_array( $image_data ) && isset( $image_data['sizes'] ) && isset( $image_data['sizes']['full'] ) ) {
		$aspect_ratio = $image_data['sizes']['full']['height'] / $image_data['sizes']['full']['width'];
		$height       = $aspect_ratio * $width;
		$size         = array( $width, $height );
	}

	$image = render_featured_image_element( $post, $size, $class_names );
	ob_start();

	?>
		<div class="<?php echo esc_attr( $container_classes ); ?>">
			<?php if ( '' !== $image_link ) { ?>
				<div class="components-disabled">
					<a href="<?php echo esc_html( $image_link ); ?>" class="wordcamp-image-link wordcamp-featured-image-link">
						<?php echo wp_kses_post( $image ); ?>
					</a>
				</div>
			<?php } else { ?>
					<?php echo wp_kses_post( $image ); ?>
			<?php } ?>
		</div>
	<?php

	return ob_get_clean();
}

/**
 * Helper method to render thumbnail image.
 *
 * @param \WP_Post     $post
 * @param string|array $size
 * @param string       $class_names
 *
 * @return string
 */
function render_featured_image_element( $post, $size, $class_names ) {
	return get_the_post_thumbnail(
		$post,
		$size,
		array(
			'class' => esc_attr( $class_names ),
			'alt'   => esc_attr( $post->post_name ),
		)
	);
}
