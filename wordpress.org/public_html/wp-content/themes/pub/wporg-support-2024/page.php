<?php
/**
 * The template for displaying all pages.
 *
 * This is the template that displays all pages by default.
 * Please note that this is the WordPress construct of pages
 * and that other 'pages' on your WordPress site may use a
 * different template.
 *
 * @package WPBBP
 */

get_header(); ?>

	<main id="main" class="wp-block-group alignfull site-main is-layout-constrained wp-block-group-is-layout-constrained" role="main">

		<div class="wp-block-group alignwide is-layout-flow wp-block-group-is-layout-flow">
		<?php get_sidebar( 'helphub' ); ?>

			<div id="main-content">
				<?php
				while ( have_posts() ) :
					the_post();

					get_template_part( 'template-parts/content', 'page' );
				endwhile; // End of the loop.
				?>
			</div>


		</div>

	</main><!-- #main -->

<?php
get_footer();
