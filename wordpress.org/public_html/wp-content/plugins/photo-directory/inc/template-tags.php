<?php

namespace WordPressdotorg\Photo_Directory\Template_Tags;

use WordPressdotorg\Photo_Directory\Moderation;
use WordPressdotorg\Photo_Directory\Photo;
use WordPressdotorg\Photo_Directory\Registrations;

/**
 * Generates, and optionally outputs, a list of colors, each linked to their
 * archive, that are associated with a given photo post.
 *
 * @param int|WP_Post|null $post Optional. Photo post ID or post object. Defaults
 *                               to global $post.
 * @param bool             $echo Optional. Echo the markup? Default true.
 * @return string The markup for the post's list of colors.
 */
function show_colors( $post = 0, $echo = true ) {
	$post = get_post( $post );

	if ( ! $post ) {
		return;
	}

	$colors = Photo::get_colors( $post->ID );

	if ( ! $colors ) {
		return;
	}

	$output = '<div class="photo-colors">';
	$output .= '<span class="photo-meta-label photo-colors-label">' . __( 'Colors: ', 'wporg-photos' ) . '</span>';

	$colors_output = [];
	foreach ( $colors as $color ) {
		$colors_output[] = sprintf(
			'<span class="photo-color photo-color-%s"><a href="%s">%s</a></span>',
			esc_attr( $color->slug ),
			esc_url( get_term_link( $color->slug, Registrations::get_taxonomy( 'colors' ) ) ),
			$color->name
		);
	}
	$output .= implode( ', ', $colors_output );

	$output .= '</div>' . "\n";

	if ( $echo ) {
		echo $output;
	}

	return $output;
}

/**
 * Generates, and optionally outputs, a list of categories, each linked to their
 * archive, that are associated with a given photo post.
 *
 * @param int|WP_Post|null $post Optional. Photo post ID or post object. Defaults
 *                               to global $post.
 * @param bool             $echo Optional. Echo the markup? Default true.
 * @return string The markup for the post's list of colors.
 */
function show_categories( $post = 0, $echo = true ) {
	$post = get_post( $post );

	if ( ! $post ) {
		return;
	}

	$categories = Photo::get_categories( $post->ID );

	if ( ! $categories ) {
		return;
	}

	$output = '<div class="photo-categories">';
	$output .= '<span class="photo-meta-label photo-categories-label">' . __( 'Categories: ', 'wporg-photos' ) . '</span>';

	$cats_output = [];
	foreach ( $categories as $cat ) {
		$cats_output[] = sprintf(
			'<a class="photo-category photo-category-%s" href="%s">%s</a>',
			esc_attr( $cat->slug ),
			esc_url( get_term_link( $cat->slug, Registrations::get_taxonomy( 'categories' ) ) ),
			$cat->name
		);
	}
	$output .= implode( ', ', $cats_output );

	$output .= '</div>' . "\n";

	if ( $echo ) {
		echo $output;
	}

	return $output;
}

/**
 * Generates, and optionally outputs, a list of tags, each linked to their
 * archive, that are associated with a given photo post.
 *
 * @param int|WP_Post|null $post Optional. Photo post ID or post object. Defaults
 *                               to global $post.
 * @param bool             $echo Optional. Echo the markup? Default true.
 * @return string The markup for the post's list of tags.
 */
function show_tags( $post = 0, $echo = true ) {
	$post = get_post( $post );

	if ( ! $post ) {
		return;
	}

	$tags = Photo::get_tags( $post->ID );

	if ( ! $tags ) {
		return;
	}

	$output = '<div class="photo-tags">';
	$output .= '<span class="photo-meta-label photo-tags-label">' . __( 'Tags: ', 'wporg-photos' ) . '</span>' . "\n";
	$output .= "<ul>\n";

	foreach ( $tags as $tag ) {
		$output .= sprintf(
			'<li class="photo-tag photo-tag-%s"><a href="%s">%s</a></li>',
			esc_attr( $tag->slug ),
			esc_url( get_term_link( $tag->slug, Registrations::get_taxonomy( 'tags' ) ) ),
			$tag->name
		);
	}

	$output .= "</ul>\n";
	$output .= '</div>' . "\n";

	if ( $echo ) {
		echo $output;
	}

	return $output;
}

/**
 * Generates, and optionally outputs, a list of moderation flags that are
 * associated with a given photo post.
 *
 * @param int|WP_Post|null $post Optional. Photo post ID or post object. Defaults
 *                               to global $post.
 * @param bool             $echo Optional. Echo the markup? Default true.
 * @return string The markup for the post's list of moderation flags.
 */
function show_moderation_flags( $post = 0, $echo = true ) {
	if ( ! current_user_can( 'delete_others_photos' ) ) {
		return;
	}

	$post = get_post( $post );

	if ( ! $post ) {
		return;
	}

	$moderation_flags = Moderation::output_moderation_flags( $post, false );

	if ( ! $moderation_flags ) {
		return;
	}

	$output = '<div class="photo-moderation-flags">';
	$output .= __( 'Moderation flags:', 'wporg-photos' );
	$output .= $moderation_flags;
	$output .= '</div>' . "\n";

	if ( $echo ) {
		echo $output;
	}

	return $output;
}

/**
 * Generates, and optionally outputs, a list of photo EXIF data.
 *
 * @param int|WP_Post|null $post Optional. Photo post ID or post object. Defaults
 *                               to global $post.
 * @param bool             $echo Optional. Echo the markup? Default true.
 * @return string The markup for the post's list of moderation flags.
 */
function show_exif( $post = 0, $echo = true ) {
	$post = get_post( $post );

	if ( ! $post ) {
		return;
	}

	$exif = Photo::get_exif( $post, [ 'aperture', 'focal_length', 'iso', 'shutter_speed' ] );
	if ( ! $exif ) {
		return;
	}

	$output = '<ul class="photo-exif">';

	$exif_output = [];
	foreach ( $exif as $key => $item ) {
		$exif_output[] = sprintf(
			'<li><span class="photo-exif photo-exif-%s">%s: <strong>%s</strong></span></li>',
			esc_attr( $key ),
			$item['label'],
			$item['value']
		);
	}
	$output .= implode( "\n", $exif_output );

	$output .= '</ul>' . "\n";

	if ( $echo ) {
		echo $output;
	}

	return $output;
}

/**
 * Generates, and optionally outputs, the markup for showing the orientation for
 * a photo.
 *
 * @param int|WP_Post|null $post Optional. Photo post ID or post object. Defaults
 *                               to global $post.
 * @param bool             $echo Optional. Echo the markup? Default true.
 * @return string The markup for the post's associated photo's orientation.
 */
function show_orientation( $post = 0, $echo = true ) {
	$post = get_post( $post );

	if ( ! $post ) {
		return '';
	}

	$orientation = Photo::get_orientation( $post->ID );

	if ( ! $orientation ) {
		return '';
	}

	$output = sprintf(
		'<div class="photo-orientation"><span>%s: <strong><a href="%s">%s</a></strong></span></div>' . "\n",
		__( 'Orientation', 'wporg-photos' ),
		esc_url( get_term_link( $orientation->term_id ) ),
		$orientation->name
	);

	if ( $echo ) {
		echo $output;
	}

	return $output;
}

/**
 * Generates, and optionally outputs, the markup for showing the full-size
 * dimensions for a photo.
 *
 * @param int|WP_Post|null $post Optional. Photo post ID or post object. Defaults
 *                               to global $post.
 * @param bool             $echo Optional. Echo the markup? Default true.
 * @return string The markup for the post's associated photo's full-size dimensions.
 */
function show_dimensions( $post = 0, $echo = true ) {
	$post = get_post( $post );

	if ( ! $post ) {
		return '';
	}

	$image_src = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), 'original' );

	if ( ! $image_src ) {
		return '';
	}

	$output = sprintf(
		'<div class="photo-dimensions"><span>%s: <strong>%s</strong></span></div>' . "\n",
		__( 'Dimensions', 'wporg-photos' ),
		$image_src[1] . ' &times; ' . $image_src[2]
	);

	if ( $echo ) {
		echo $output;
	}

	return $output;
}

/**
 * Returns, and optionally outputs, the markup for showing the publish date of
 * a photo.
 *
 * @param int|WP_Post|null $post Optional. Photo post ID or post object. Defaults
 *                               to global $post.
 * @param bool             $echo Optional. Echo the markup? Default true.
 * @return string The markup for the post's publish date.
 */
function show_publish_date( $post = 0, $echo = true ) {
	$post = get_post( $post );

	if ( ! $post ) {
		return '';
	}

	if ( 'publish' !== $post->post_status ) {
		return '';
	}

	$output = '<div class="photo-publish-date">';
	$output .= sprintf(
		/* translators: %s: date when photo was published */
		__( 'Published on %s', 'wporg-photos' ),
		get_the_date( _x( 'F d, Y', 'photo published date format', 'wporg-photos' ), $post->ID )
	);
	$output .= "</div>\n";

	if ( $echo ) {
		echo $output;
	}

	return $output;
}
