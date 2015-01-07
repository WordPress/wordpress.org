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
				<?php the_content(); ?>
			</div><!-- .entry-content -->

			<?php edit_post_link( __( 'Edit', '_s' ), '<footer class="entry-footer"><span class="edit-link">', '</span></footer><!-- .entry-footer -->' ); ?>
		</article><!-- #post-## -->

	<?php
	endwhile;
endif;

get_footer();
