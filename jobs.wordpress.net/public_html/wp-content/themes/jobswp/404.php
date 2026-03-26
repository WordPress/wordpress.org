<?php
/**
 * The template for displaying 404 pages (Not Found).
 *
 * @package jobswp
 */

get_header(); ?>

	<main>
		<section class="no-results not-found" style="text-align:center; padding: 80px 20px;">
			<header class="page-header">
				<h1 class="page-title"><?php _e( 'Oops! That page can&rsquo;t be found.', 'jobswp' ); ?></h1>
			</header>

			<div class="page-content">
				<p><?php _e( 'It looks like nothing was found at this location. Maybe try a search?', 'jobswp' ); ?></p>
				<div style="max-width:400px; margin: 20px auto;">
					<?php get_search_form(); ?>
				</div>
			</div>
		</section>
	</main>

<?php get_footer(); ?>
