<?php
/**
 * Template Name: Full-width Page
 *
 * @package WPBBP
 */

get_header(); ?>

	<main id="main" class="site-main page-full-width" role="main">

		<?php
		while ( have_posts() ) : the_post();

			get_template_part( 'template-parts/content', 'page' );
		endwhile; // End of the loop.
		?>

	</main><!-- #main -->

<?php
get_footer();
