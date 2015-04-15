<?php
/**
 * Template file for the Upload page.
 *
 * @package wporg-themes
 */

get_header();

if ( have_posts() ) :
	while ( have_posts() ) :
		the_post();
		?>

		<article id="post-<?php the_ID(); ?>" <?php post_class( 'wrap' ); ?>>
			<header class="entry-header">
				<h2 class="entry-title"><?php _e( 'Add Your Theme to the Directory', 'wporg-themes' ); ?></h2>
			</header><!-- .entry-header -->

			<div class="entry-content">
				<?php do_shortcode( '[wporg-themes-upload]' ); ?>
			</div><!-- .entry-content -->

		</article><!-- #post-## -->

	<?php
	endwhile;
endif;

get_footer();
