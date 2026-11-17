<?php
/**
 * Template part for displaying a plugin readme section.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package WordPressdotorg\Plugin_Directory\Theme
 */

use WordPressdotorg\Plugin_Directory\Plugin_I18n;

global $section_slug, $section_title, $section_content;

$prefix = in_array( $section_slug, array( 'screenshots', 'faq', 'blocks' ), true ) ? '' : 'tab-';

$classes = [ 'plugin-' . $section_slug, 'section' ];
$classes = implode( ' ', $classes );

// Presentation attributes allowed on every SVG shape and group.
$svg_presentation_attributes = array(
	'fill'              => true,
	'fill-rule'         => true,
	'fill-opacity'      => true,
	'clip-rule'         => true,
	'stroke'            => true,
	'stroke-width'      => true,
	'stroke-linecap'    => true,
	'stroke-linejoin'   => true,
	'stroke-miterlimit' => true,
	'stroke-dasharray'  => true,
	'stroke-opacity'    => true,
	'opacity'           => true,
	'transform'         => true,
);

$allowed_svg = array(
	'svg'      => array(
		'class'           => true,
		'aria-hidden'     => true,
		'aria-labelledby' => true,
		'role'            => true,
		'focusable'       => true,
		'xmlns'           => true,
		'width'           => true,
		'height'          => true,
		'viewbox'         => true,
	) + $svg_presentation_attributes,
	'g'        => $svg_presentation_attributes,
	'title'    => array( 'title' => true ),
	'path'     => array( 'd' => true ) + $svg_presentation_attributes,
	'circle'   => array(
		'cx' => true,
		'cy' => true,
		'r'  => true,
	) + $svg_presentation_attributes,
	'ellipse'  => array(
		'cx' => true,
		'cy' => true,
		'rx' => true,
		'ry' => true,
	) + $svg_presentation_attributes,
	'rect'     => array(
		'x'      => true,
		'y'      => true,
		'width'  => true,
		'height' => true,
		'rx'     => true,
		'ry'     => true,
	) + $svg_presentation_attributes,
	'line'     => array(
		'x1' => true,
		'y1' => true,
		'x2' => true,
		'y2' => true,
	) + $svg_presentation_attributes,
	'polyline' => array( 'points' => true ) + $svg_presentation_attributes,
	'polygon'  => array( 'points' => true ) + $svg_presentation_attributes,
	// Keep clip and mask definitions so their shapes are not drawn as content.
	'defs'     => array(),
	'clippath' => array(),
	'mask'     => array(),
);
?>

<div id="<?php echo esc_attr( $prefix . $section_slug ); ?>" class="<?php echo esc_attr( $classes ); ?>">
	<h2 id="<?php echo esc_attr( $section_slug . '-header' ); ?>"><?php echo esc_html( $section_title ); ?></h2>

	<p><?php printf( esc_html( _n( 'This plugin provides %d block.', 'This plugin provides %d blocks.', count( $section_content ), 'wporg-plugins'  ) ), count( $section_content ) ); ?></p>
	<ul class="plugin-blocks-list">
		<?php
		foreach ( $section_content as $block ) :
			$block_name = isset( $block->title ) ? Plugin_I18n::instance()->translate( 'block_title:' . md5( $block->title ), $block->title, array( 'code_i18n' => true ) ) : false;
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
					<span class="block-icon" <?php echo $block_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Style attribute built above from sanitize_hex_color() values. ?>>
						<?php echo wp_kses( str_replace( '<svg ', '<svg role="img" aria-hidden="true" focusable="false" ', $block_icon ), $allowed_svg ); ?>
					</span>
				<?php elseif ( $block_icon ) : ?>
					<span class="block-icon dashicons dashicons-<?php echo esc_attr( $block_icon ); ?>" <?php echo $block_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Style attribute built above from sanitize_hex_color() values. ?>></span>
				<?php else : ?>
					<span class="block-icon dashicons dashicons-block-default"></span>
				<?php endif; ?>
				<span class="block-title"><?php echo esc_html( $block_name ); ?></span>
				<?php if ( isset( $block->description ) ) : ?>
					<span class="block-description"><?php echo esc_html( Plugin_I18n::instance()->translate( 'block_description:' . md5( $block->description ), $block->description, array( 'code_i18n' => true ) ) ); ?></span>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
</div>
