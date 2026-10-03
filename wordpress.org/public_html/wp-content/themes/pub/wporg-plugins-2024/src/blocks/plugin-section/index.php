<?php
/**
 * Block Name: Plugin Section
 * Description: Display a section of a plugin.
 *
 * @package wporg
 */

namespace WordPressdotorg\Theme\Plugins_2024\Plugin_Section_Block;

use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\Template;

add_action( 'init', __NAMESPACE__ . '\init' );

/**
 * Registers the block using the metadata loaded from the `block.json` file.
 * Behind the scenes, it registers also all assets so they can be enqueued
 * through the block editor in the corresponding context.
 *
 * @see https://developer.wordpress.org/reference/functions/register_block_type/
 */
function init() {
	register_block_type(
		dirname( __DIR__, 3 ) . '/build/blocks/plugin-section',
		array(
			'render_callback' => __NAMESPACE__ . '\render',
		)
	);
}

/**
 * Render the block content.
 *
 * @param array    $attributes Block attributes.
 * @param string   $content    Block default content.
 * @param WP_Block $block      Block instance.
 *
 * @return string Returns the block markup.
 */
function render( $attributes, $content, $block ) {
	$_post_id = $block->context['postId'];
	$slug = $attributes['section'];
	if ( ! $_post_id ) {
		return;
	}

	$titles = Template::get_plugin_section_titles();
	$section_title = $titles[ $slug ] ?? '';

	$content = Plugin_Directory::instance()->split_post_content_into_pages( get_the_content( null, null, $_post_id ) );

	if ( 'blocks' === $slug ) {
		$section_content = render_section_blocks( $_post_id );
	} else if ( in_array( $slug, [ 'screenshots', 'reviews', 'developers' ] ) ) {
		// Do the shortcode manually so that we know if it's empty.
		$section_content = do_shortcode( $content[ $slug ] );
	} else {
		$section_content = $content[ $slug ];
	}

	if ( empty( $section_content ) ) {
		return '';
	}

	$wrapper_attributes = get_block_wrapper_attributes( [ 'class' => "is-section-$slug" ] );
	return sprintf(
		'<div %1$s id="%2$s">%3$s %4$s</div>',
		$wrapper_attributes,
		esc_attr( $slug ),
		'<h2 class="wp-block-heading has-inter-font-family has-heading-3-font-size">' . $section_title . '</h2>',
		trim( apply_filters( 'the_content', $section_content, $slug ) )
	);
}

/**
 * Render the blocks for the section.
 */
function render_section_blocks( $_post_id ) {
	$blocks = get_post_meta( $_post_id, 'all_blocks', true );
	if ( ! $blocks ) {
		return '';
	}

	ob_start();

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
	<ul class="plugin-blocks-list">
		<?php
		foreach ( $blocks as $block ) :
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
			$block_style   = $block_styles ? 'style="' . implode( '; ', $block_styles ) . '"' : '';
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
	<?php

	return ob_get_clean();
}
