<?php
/**
 * Template part for displaying plugin contributors & developers.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package WordPressdotorg\Plugin_Directory\Theme
 */

global $section, $section_slug, $section_content;
?>

<div id="<?php echo esc_attr( $section_slug ); ?>" class="read-more" aria-expanded="false">
	<h2>Contributors & Developers</h2>
	<div>
		<p>This is open source software. The following people have contributed to this plugin.</p>
		<?php echo $section_content; ?>
	</div>
</div>
<button type="button" class="button-link section-toggle" aria-controls="<?php echo esc_attr( $section_slug ); ?>"><?php _e( 'Read more', 'wporg-plugins' ); ?></button>
