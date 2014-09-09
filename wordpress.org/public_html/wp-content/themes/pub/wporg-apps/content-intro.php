<?php
/**
 * The template used for displaying page content in page.php
 *
 * @package wpmobileapps
 */
?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<div class="entry-content">
		<?php the_content(); ?>
	</div><!-- .entry-content -->
	<div class="intro-image">
		<div>
		<img src="<?php echo esc_url( get_template_directory_uri() . '/images/iphone-portrait-cut.svg' ); ?>" />
		</div>
	</div>
</article><!-- #post-## -->
