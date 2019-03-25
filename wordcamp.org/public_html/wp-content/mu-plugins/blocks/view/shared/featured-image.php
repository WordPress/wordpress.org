<?php

namespace WordCamp\Blocks\Shared\Components;

/**
 * Provides render backend for FeaturedImage component.
 *
 * @param array    $class_names        Additional classes to add inside <img> tag.
 * @param \WP_Post $post               Current post object. This will be used to calculate srcset attribute.
 * @param string   $selected_image_url URL for selected sized image.
 * @param int      $height             Height of the image
 * @param int      $width              Width of the image
 *
 * @return string Output markup for featured image.
 */
function render_featured_image( $class_names, $post, $height, $width ) {
	$class_names[] = 'wordcamp-featured-image';
	$class_names[] = 'wordcamp-featured-image-' . $post->post_name;
	$class_names = implode( ' ', $class_names );
	$attachment_id = get_post_thumbnail_id( $post->ID );
	$image_data = wp_get_attachment_metadata( $attachment_id );
	$size = 'post-thumbnail';
	if ( is_array( $image_data ) && isset( $image_data['sizes'] ) && isset( $image_data['sizes']['full'] ) ) {
		$aspect_ratio = $image_data['sizes']['full']['height'] / $image_data['sizes']['full']['width'];
		$height = $aspect_ratio * $width;
		$size = array( $width, $height );
	}

	return get_the_post_thumbnail(
		$post,
		$size,
		array(
			'class' => esc_attr( $class_names ),
			'alt'   => esc_attr( $post->post_name ),
		)
	);
}
