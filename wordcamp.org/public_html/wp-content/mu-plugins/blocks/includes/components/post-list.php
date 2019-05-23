<?php
namespace WordCamp\Blocks\Components;

use function WordCamp\Blocks\Utilities\{ render_class_string };

defined( 'WPINC' ) || die();

/**
 * Render the containing HTML structures of a post list.
 *
 * @param array  $rendered_items    Array of rendered post list items.
 * @param string $layout            Whether the layout is `grid` or `list`.
 * @param int    $columns           Number of columns if layout is `grid`. Assumed to be 1 if layout is list.
 * @param array  $container_classes Array of classes that will be added to container.
 *
 * @return string Markup of output layout.
 */
function render_post_list( array $rendered_items, $layout = 'list', $columns = 1, array $container_classes = [] ) {
	if ( count( $rendered_items ) < 1 ) {
		return '';
	}

	$container_classes = array_merge(
		[
			'wordcamp-block',
			'wordcamp-post-list',
			'layout-' . sanitize_html_class( $layout )
		],
		$container_classes
	);

	if ( 'grid' === $layout ) {
		if ( $columns < 2 ) {
			$columns = 2;
		}

		$container_classes[] = 'grid-columns-' . absint( $columns );
	}

	$container_classes = render_class_string( $container_classes );

	ob_start();
	?>
		<ul class="<?php echo esc_attr( $container_classes ); ?>">
			<?php foreach ( $rendered_items as $item ) : ?>
				<li class="wordcamp-post-list__post wordcamp-clearfix">
					<?php echo wp_kses_post( $item ); ?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php

	return ob_get_clean();
}
