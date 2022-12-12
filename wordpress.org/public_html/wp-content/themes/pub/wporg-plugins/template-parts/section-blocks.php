<?php
/**
 * Template part for displaying a plugin readme section.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package WordPressdotorg\Plugin_Directory\Theme
 */

global $section_slug, $section_title, $section_content;

$prefix = in_array( $section_slug, array( 'screenshots', 'faq', 'blocks' ), true ) ? '' : 'tab-';

$classes = [ 'plugin-' . $section_slug, 'section' ];
$classes = implode( ' ', $classes );

$allowed_svg = array(
	'svg'   => array(
		'class' => true,
		'aria-hidden' => true,
		'aria-labelledby' => true,
		'role' => true,
		'xmlns' => true,
		'width' => true,
		'height' => true,
		'viewbox' => true,
	),
	'g'     => array( 'fill' => true ),
	'title' => array( 'title' => true ),
	'path'  => array(
		'd' => true,
		'fill' => true,
		'transform' => true,
	),
);
?>

<div id="<?php echo esc_attr( $prefix . $section_slug ); ?>" class="<?php echo esc_attr( $classes ); ?>">
	<h2 id="<?php echo esc_attr( $section_slug . '-header' ); ?>"><?php echo esc_html( $section_title ); ?></h2>

	<p><?php printf( esc_html( _n( 'This plugin provides %d block.', 'This plugin provides %d blocks.', count( $section_content ), 'wporg-plugins'  ) ), count( $section_content ) ); ?></p>
	<ul class="plugin-blocks-list">
		<?php
		foreach ( $section_content as $block ) :
			$block_name = isset( $block->title ) ? $block->title : false;
			if ( ! $block_name ) {
				$block_name = isset( $block->name ) ? $block->name : false;
			}
			if ( ! $block_name ) {
				// If we still have no name, we don't have a valid block.
				continue;
			}

			$block_icon = '';
			$block_styles = [];
			if ( isset( $block->icon->src ) ) {
				$block_icon = $block->icon->src;

				if ( isset( $block->icon->foreground ) ) {
					$block_styles[] = 'color: ' . sanitize_hex_color( $block->icon->foreground );
				}

				if ( isset( $block->icon->background ) ) {
					$block_styles[] = 'background-color: ' . sanitize_hex_color( $block->icon->background );
				}

			} elseif ( isset( $block->icon ) && is_string( $block->icon ) ) {
				$block_icon = $block->icon;
			}

			$block_classes = 'plugin-blocks-list-item';
			$block_classes .= isset( $block->description ) ? ' has-description' : '';
			$block_style   = $block_styles ? 'style="' . implode('; ', $block_styles ) . '"' : '';
			?>
			<li class="<?php echo esc_attr( $block_classes ); ?>">
				<?php if ( false !== strpos( $block_icon, '<svg' ) ) : ?>
					<span class="block-icon" <?php echo $block_style; ?>>
						<?php echo wp_kses( str_replace( '<svg ', '<svg role="img" aria-hidden="true" focusable="false" ', $block_icon ), $allowed_svg ); ?>
					</span>
				<?php elseif ( $block_icon ) : ?>
					<span class="block-icon dashicons dashicons-<?php echo esc_attr( $block_icon ); ?>" <?php echo $block_style; ?>></span>
				<?php else : ?>
					<span class="block-icon dashicons dashicons-block-default"></span>
				<?php endif; ?>
				<span class="block-title"><?php echo esc_html( $block_name ); ?></span>
				<?php if ( isset( $block->description ) ) : ?>
					<span class="block-description"><?php echo esc_html( $block->description ); ?></dd>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
</div>
