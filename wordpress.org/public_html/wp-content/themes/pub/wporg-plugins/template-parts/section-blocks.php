<?php
/**
 * Template part for displaying a plugin readme section.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package WordPressdotorg\Plugin_Directory\Theme
 */

global $section, $section_slug, $section_content;

$prefix = in_array( $section_slug, array( 'screenshots', 'faq', 'blocks' ), true ) ? '' : 'tab-';

$classes = [ 'plugin-' . $section_slug, 'section' ];
$classes = implode( ' ', $classes );
?>

<div id="<?php echo esc_attr( $prefix . $section_slug ); ?>" class="<?php echo esc_attr( $classes ); ?>">
	<h2 id="<?php echo esc_attr( $section_slug . '-header' ); ?>"><?php echo esc_html( $section['title'] ); ?></h2>

	<p><?php printf( esc_html( _n( 'This plugin provides %d block.', 'This plugin provides %d blocks.', count( $section_content ), 'wporg-plugins'  ) ), count( $section_content ) ); ?></p>
	<dl>
		<?php foreach ( $section_content as $block ) : ?>
			<dt><?php if ( isset( $block->name ) ) echo esc_html( $block->name ); ?></dt>
				<dd><?php if ( isset( $block->title ) ) echo esc_html( $block->title ); ?></dd>
		<?php endforeach; ?>
	</dl>
</div>
