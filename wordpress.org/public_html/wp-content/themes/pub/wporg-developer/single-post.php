<?php
/**
 * The Template for displaying all single posts.
 *
 * @package wporg-developer
 */

get_header(); ?>

	<div id="primary" class="content-area has-sidebar">

		<header class="page-header">
			<h1 class="page-title"><?php _e( 'Handbook', 'wporg' ); ?></h1>
		</header><!-- .page-header -->

		<main id="main" class="site-main" role="main">

		<?php while ( have_posts() ) : the_post(); ?>

			<?php get_template_part( 'content', 'single' ); ?>

			<?php
				// If comments are open or we have at least one comment, load up the comment template
				if ( comments_open() || '0' != get_comments_number() ) :
					comments_template();
				endif;
			?>

		<?php endwhile; // end of the loop. ?>

		</main><!-- #main -->
		<?php get_sidebar(); ?>
	</div><!-- #primary -->
<?php get_footer(); ?>