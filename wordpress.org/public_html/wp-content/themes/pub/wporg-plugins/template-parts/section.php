<?php
/**
 * Template part for displaying a plugin readme section.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package WordPressdotorg\Plugin_Directory\Theme
 */

global $section, $section_slug, $section_content, $section_read_more;

$prefix = in_array( $section_slug, array( 'screenshots', 'faq' ), true ) ? '' : 'tab-';

$classes = [ 'plugin-' . $section_slug, 'section' ];
if ( $section_read_more ) {
	$classes[] = 'read-more';
}
$classes = implode( ' ', $classes );
?>

<div id="<?php echo esc_attr( $prefix . $section_slug ); ?>" class="<?php echo esc_attr( $classes ); ?>">
	<h2 id="<?php echo esc_attr( $section_slug . '-header' ); ?>"><?php echo esc_html( $section['title'] ); ?></h2>
	<?php echo $section_content; ?>
</div>
<?php if ( $section_read_more ) : ?>
	<button
		type="button"
		class="button-link section-toggle"
		aria-controls="<?php echo esc_attr( $prefix . $section_slug ); ?>"
		aria-describedby="<?php echo esc_attr( $section_slug . '-header' ); ?>"
		aria-expanded="false"
		data-show-less="<?php esc_attr_e( 'Show less', 'wporg-plugins' ); ?>"
		data-read-more="<?php esc_attr_e( 'Read more', 'wporg-plugins' ); ?>"
	>
	<?php esc_html_e( 'Read more', 'wporg-plugins' ); ?>
	</button>
<?php endif; ?>
