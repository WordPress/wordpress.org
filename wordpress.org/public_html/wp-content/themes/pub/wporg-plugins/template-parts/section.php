<?php
/**
 * Template part for displaying a plugin readme section.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package WordPressdotorg\Plugin_Directory\Theme
 */

global $section, $section_slug, $section_content, $section_read_more;

?>

<div
	id="<?php echo esc_attr( $section_slug ); ?>"
	class="plugin-<?php echo esc_attr( $section_slug ); ?> section <?php if ( $section_read_more ) { echo 'read-more'; } ?>"
>
	<h2><?php echo $section['title']; ?></h2>
	<?php echo $section_content; ?>
</div>
<?php if ( $section_read_more ) : ?>
<button
	type="button"
	class="button-link section-toggle"
	aria-controls="<?php echo esc_attr( $section_slug ); ?>"
	aria-expanded="false"
	data-show-less="<?php esc_attr_e( 'Show less', 'wporg-plugins' ); ?>"
	data-read-more="<?php esc_attr_e( 'Read more', 'wporg-plugins' ); ?>"
><?php _e( 'Read more', 'wporg-plugins' ); ?></button>
<?php endif; ?>
