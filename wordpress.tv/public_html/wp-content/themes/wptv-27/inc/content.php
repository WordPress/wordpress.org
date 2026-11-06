<?php
/**
 * Content output filters: document titles, excerpts, Media RSS.
 *
 * @package WordPressTV
 */

namespace WordPressTV\Content;

use WordPressTV\Video;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Customizes the document title of taxonomy archives.
 *
 * @param array $title Document title parts.
 * @return array
 */
function document_title_parts( $title ) {
	$object = get_queried_object();
	if ( ! $object instanceof \WP_Term ) {
		return $title;
	}

	if ( is_tax( 'language' ) ) {
		/* translators: %s: The language name. */
		$title['title'] = sprintf( __( 'Videos in %s', 'wptv' ), $object->name );
	} elseif ( is_tax( 'event' ) ) {
		/* translators: %s: The event name. */
		$title['title'] = sprintf( __( 'Videos from %s', 'wptv' ), $object->name );
	} elseif ( is_tax( 'speakers' ) ) {
		/* translators: %s: The speaker name. */
		$title['title'] = sprintf( __( 'Videos of %s', 'wptv' ), $object->name );
	} elseif ( is_category() ) {
		/* translators: %s: Category name. */
		$title['title'] = sprintf( __( '%s Videos', 'wptv' ), $object->name );
	} elseif ( is_tag() ) {
		/* translators: %s: Tag name. */
		$title['title'] = sprintf( __( 'Videos tagged %s', 'wptv' ), $object->name );
	}

	return $title;
}
add_filter( 'document_title_parts', __NAMESPACE__ . '\document_title_parts' );

/**
 * Appends the presentation slides link to the excerpt.
 *
 * @param string $excerpt Post excerpt.
 * @return string
 */
function excerpt_slides( $excerpt ) {
	$slides = get_post_meta( get_the_ID(), '_wptv_slides_url', true );

	if ( ! empty( $slides ) ) {
		$excerpt .= '<p><a href="' . esc_url( $slides ) . '">Presentation Slides &raquo;</a></p>';
	}

	return $excerpt;
}
add_filter( 'get_the_excerpt', __NAMESPACE__ . '\excerpt_slides' );

/*
 * Media RSS (WordPress.com only; the filters do nothing elsewhere).
 * All videos are uploaded by the same anonymous user, so its avatar is left out.
 */
add_filter( 'mrss_avatar_user', '__return_false' );

/**
 * Adds the original uploaded video file to the Media RSS output.
 *
 * @param array $medias Media RSS items.
 * @return array
 */
function mrss_add_original( $medias ) {
	$original_url = Video\get_attachment_url();

	if ( $original_url ) {
		$medias[] = array(
			'content' => array(
				'attr' => array(
					'medium'    => 'video',
					'isDefault' => 'false',
					'url'       => $original_url,
				),
			),
		);
	}

	return $medias;
}
add_filter( 'mrss_media', __NAMESPACE__ . '\mrss_add_original' );
