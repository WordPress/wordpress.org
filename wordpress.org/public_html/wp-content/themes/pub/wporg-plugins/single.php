<?php
/**
 * The template for displaying all single posts.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/#single-post
 *
 * @package WordPressdotorg\Plugin_Directory\Theme
 */

namespace WordPressdotorg\Plugin_Directory\Theme;

get_header(); ?>

	<main id="main" class="site-main" role="main">

		<?php
			while ( have_posts() ) :
				the_post();

				get_template_part( 'template-parts/plugin', 'single' );
			endwhile; // End of the loop.
		?>

	</main><!-- #main -->

<?php
get_footer();
