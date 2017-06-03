<?php
/**
 * Template Name: Full Width
 *
 * @package CampSite_2017
 */

get_header(); ?>

	<div id="primary" class="content-area full-width">
		<main id="main" class="site-main" role="main">
			<?php

			while ( have_posts() ) {
				the_post();
				get_template_part( 'template-parts/content', 'page' );

				if ( comments_open() || get_comments_number() ) {
					comments_template();
				}
			}

			?>
		</main>
	</div>

<?php get_footer();
