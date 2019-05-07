<?php
namespace WordCamp\Blocks\Shared\Content;
defined( 'WPINC' ) || die();

use WP_Post;

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

/**
 * Render HTML for a title with specified heading level and optionally a link.
 *
 * @param string $title
 * @param string $link
 * @param int    $heading_level
 * @param array  $classes
 *
 * @return false|string
 */
function render_item_title( $title, $link = '', $heading_level = 3, array $classes = [] ) {
	$valid_heading_levels = [ 1, 2, 3, 4, 5, 6 ];

	if ( ! in_array( $heading_level, $valid_heading_levels, true ) ) {
		$heading_level = 3;
	}

	$tag = 'h' . $heading_level;

	$classes = render_class_string( array_merge(
		[ 'wordcamp-item-title' ],
		$classes
	) );

	ob_start();
	?>
		<<?php echo esc_html( $tag ); ?> class="<?php echo esc_attr( $classes ); ?>">
			<?php if ( $link ) : ?>
				<a href="<?php echo esc_url( $link ); ?>">
			<?php endif; ?>
					<?php echo wp_kses_post( $title ); ?>
			<?php if ( $link ) : ?>
				</a>
			<?php endif; ?>
		</<?php echo esc_html( $tag ); ?>>
	<?php

	return ob_get_clean();
}

/**
 * Render arbitrary HTML content within a div container.
 *
 * @param string $content
 * @param array  $classes
 *
 * @return false|string
 */
function render_item_content( $content, array $classes = [] ) {
	$classes = render_class_string( array_merge(
		[ 'wordcamp-item-content' ],
		$classes
	) );

	ob_start();
	?>
		<div class="<?php echo esc_attr( $classes ); ?>">
			<?php echo wp_kses_post( wpautop( $content ) ); ?>
		</div>
	<?php

	return ob_get_clean();
}

/**
 * Render HTML for a permalink wrapped in its own paragraph.
 *
 * @param string $link
 * @param string $label
 * @param array  $classes
 *
 * @return false|string
 */
function render_item_permalink( $link, $label = '', array $classes = [] ) {
	if ( ! $label ) {
		$label = __( 'Read more', 'wordcamporg' );
	}

	$classes = render_class_string( array_merge(
		[ 'wordcamp-item-permalink' ],
		$classes
	) );

	ob_start();
	?>
		<p class="<?php echo esc_attr( $classes ); ?>">
			<a href="<?php echo esc_url( $link ); ?>">
				<?php echo esc_html( $label ); ?>
			</a>
		</p>
	<?php

	return ob_get_clean();
}
