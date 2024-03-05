<?php
/**
 * Template Name: Page with a Forums Sidebar
 *
 * @package WPBBP
 */

get_header(); ?>

	<main id="main" class="site-main page-forums-sidebar wp-block-group alignfull is-layout-constrained wp-block-group-is-layout-constrained" role="main">

		<div class="wp-block-group alignwide is-layout-flow wp-block-group-is-layout-flow clear">

			<?php
			while ( have_posts() ) : the_post();

				get_template_part( 'template-parts/content', 'page' );
			endwhile; // End of the loop.
			?>

			<?php get_sidebar(); ?>

		</div>

	</main>

<?php get_footer(); ?>
