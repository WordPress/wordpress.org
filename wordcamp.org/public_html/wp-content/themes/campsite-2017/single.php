<?php
/**
 * The template for displaying all single posts
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/#single-post
 *
 * @package CampSite_2017
 */

namespace WordCamp\CampSite_2017;

get_header(); ?>

	<div id="primary" class="content-area">
		<main id="main" class="site-main" role="main">
		<?php

		while ( have_posts() ) {
			the_post();
			get_template_part( 'template-parts/content', get_post_format() );
			the_post_navigation();

			// If comments are open or we have at least one comment, load up the comment template.
			if ( comments_open() || get_comments_number() ) {
				comments_template();
			}
		}

		?>
		</main>
	</div>

<?php

get_sidebar();
get_footer();
