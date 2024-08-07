<?php
/**
 * Template Name: Page with a Forums Sidebar
 *
 * @package WPBBP
 */

get_header(); ?>

	<main id="main" class="site-main page-forums-sidebar" role="main">

		<?php
		while ( have_posts() ) : the_post();

			get_template_part( 'template-parts/content', 'page' );
		endwhile; // End of the loop.
		?>

		<?php get_sidebar(); ?>
	</main>

<?php get_footer(); ?>
