<?php
/**
 * The template for displaying all single photos.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/#single-post
 *
 * @package WordPressdotorg\Photo_Directory\Theme
 */

namespace WordPressdotorg\Photo_Directory\Theme;

get_header(); ?>

	<main id="main" class="site-main wrap" role="main">

		<?php
		while ( have_posts() ) :
			the_post();

			get_template_part( 'template-parts/photo', 'single' );
		endwhile; // End of the loop.
		?>

	</main><!-- #main -->

<?php
get_footer();