<?php

namespace WordCamp\Blocks\Shared\Components;

/**
 * @since Blocks v1.0
 * Provides rendering for grid-layout component.
 *
 * @param string $layout            Whether the layout is `grid` or `list`.
 * @param int    $columns           Number of columns if layout is `grid`. Assumed to be 1 if layout is grid.
 * @param array  $children          Array of posts to rendered inside layout. Should be output markup.
 * @param array  $container_classes Array of classes that will be added to container.
 *
 * @return string Markup of output layout.
 */
function render_grid_layout( $layout, $columns, $children, $container_classes ) {
	if ( ! is_array( $children ) || count( $children ) === 0 ) {
		return '';
	}

	$container_classes[] = 'layout-' . sanitize_html_class( $layout );
	$container_classes[] = 'wordcamp-block-post-list';

	if ( 'grid' === $layout ) {
		$container_classes[] = 'grid-columns-' . absint( $columns );
	}
	$container_classes = implode( ' ', $container_classes );

	ob_start();
	?>
	<ul class="<?php echo esc_attr( $container_classes ); ?>">
		<?php foreach ( $children as $child ) { ?>
			<li class="wordcamp-block-post-list-item wordcamp-grid-layout-item wordcamp-clearfix">
				<?php printf( wp_kses_post( $child ) ); ?>
			</li>
		<?php } ?>
	</ul>
	<?php

	return ob_get_clean();
}
