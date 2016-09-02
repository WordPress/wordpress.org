<?php
/**
 * Template part for displaying plugin reviews.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package WordPressdotorg\Plugin_Directory\Theme
 */

global $section, $section_slug, $section_content;
?>

<div id="<?php echo esc_attr( $section_slug ); ?>" class="read-more" aria-expanded="false">
	<h2><?php echo $section['title']; ?></h2>
	<?php echo $section_content; ?>
</div>
<a class="reviews-link" href="<?php echo esc_url( 'https://wordpress.org/support/plugin/' . get_post()->post_name . '/reviews/' ); ?>">
	<?php printf( __( 'Read all %s reviews', 'wporg-plugins' ), array_sum( get_post_meta( get_the_ID(), 'ratings', true ) ) ); ?>
</a>
