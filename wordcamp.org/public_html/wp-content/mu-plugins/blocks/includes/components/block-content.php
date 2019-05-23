<?php
namespace WordCamp\Blocks\Components;

use function WordCamp\Blocks\Utilities\{ render_class_string };

defined( 'WPINC' ) || die();

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
		[ 'wordcamp-block__post-title' ],
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
		[ 'wordcamp-block__post-content' ],
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
		[ 'wordcamp-block__post-permalink' ],
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
