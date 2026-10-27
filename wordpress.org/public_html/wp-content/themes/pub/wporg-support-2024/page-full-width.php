<?php
/**
 * Template Name: Full-width Page
 *
 * @package WPBBP
 */

get_header(); ?>

	<main id="main" class="wp-block-group alignfull site-main page-full-width is-layout-constrained wp-block-group-is-layout-constrained" role="main">

		<div class="wp-block-group alignwide is-layout-flow wp-block-group-is-layout-flow">

			<?php
			while ( have_posts() ) : the_post();

				get_template_part( 'template-parts/content', 'page' );
			endwhile; // End of the loop.
			?>

		</div>

	</main><!-- #main -->

<?php
get_footer();
