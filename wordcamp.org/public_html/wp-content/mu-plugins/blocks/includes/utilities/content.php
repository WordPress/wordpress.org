<?php
namespace WordCamp\Blocks\Utilities;

use WP_Post;

defined( 'WPINC' ) || die();

/**
 * Get the full content of a post, ignoring more and noteaser tags and pagination.
 *
 * This works similarly to `the_content`, including applying filters, but:
 * - It skips all of the logic in `get_the_content` that deals with tags like <!--more--> and
 *   <!--noteaser-->, as well as pagination and global state variables like `$page`, `$more`, and
 *   `$multipage`.
 * - It returns a string of content, rather than echoing it.
 *
 * @param int|WP_Post $post Post ID or post object.
 *
 * @return string The full, filtered post content.
 */
function get_all_the_content( $post ) {
	$post = get_post( $post );

	$content = $post->post_content;

	/** This filter is documented in wp-includes/post-template.php */
	$content = apply_filters( 'the_content', $content );
	$content = str_replace( ']]>', ']]&gt;', $content );

	return $content;
}

/**
 * Convert an array of strings into one string that is a punctuated, human-readable list.
 *
 * @param array $array
 *
 * @return string
 */
function array_to_human_readable_list( array $array ) {
	$count = count( $array );
	$list  = '';

	switch ( $count ) {
		case 0:
			break;
		case 1:
			$list = array_shift( $array );
			break;
		case 2:
			$list = sprintf(
				/* translators: Each %s is a person's name. */
				_x( '%1$s and %2$s', 'list of two items', 'wordcamporg' ),
				array_shift( $array ),
				array_shift( $array )
			);
			break;
		default:
			/* translators: used between list items, there is a space after the comma */
			$item_separator = esc_html__( ', ', 'wordcamporg' );

			$initial = array_slice( $array, 0, $count - 1 );
			$initial = implode( $item_separator, $initial ) . $item_separator;
			$last    = array_slice( $array, -1, 1 )[0];

			$list = sprintf(
				/* translators: 1: A list of items. 2: The last item in a list of items. */
				_x( '%1$s and %2$s', 'list of three or more items', 'wordcamporg' ),
				$initial,
				$last
			);
			break;
	}

	return $list;
}

/**
 * Convert an array of class names into a space-separated string for use in an HTML attribute.
 *
 * @param array $classes
 *
 * @return string
 */
function render_class_string( array $classes ) {
	$classes = array_map( 'sanitize_html_class', $classes );
	$classes = array_filter( $classes );
	$classes = array_unique( $classes );

	return implode( ' ', $classes );
}
