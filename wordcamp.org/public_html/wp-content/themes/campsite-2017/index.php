<?php
/**
 * The main template file
 *
 * @package CampSite_2017
 */

namespace WordCamp\CampSite_2017;

get_header(); ?>

	<div id="primary" class="content-area">
		<main id="main" class="site-main" role="main">

			<?php if ( have_posts() ) :
				if ( is_home() && ! is_front_page() ) : ?>
					<header>
						<h1 class="page-title screen-reader-text">
							<?php single_post_title(); ?>
						</h1>
					</header>

				<?php endif;

				while ( have_posts() ) {
					the_post();
					get_template_part( 'template-parts/content', get_post_format() );
				}

				the_posts_navigation();

			else :

				get_template_part( 'template-parts/content', 'none' );

			endif; ?>

		</main>
	</div>

<?php

get_sidebar();
get_footer();
