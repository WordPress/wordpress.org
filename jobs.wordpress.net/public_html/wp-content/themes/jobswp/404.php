<?php
/**
 * The template for displaying 404 pages (Not Found).
 *
 * @package jobswp
 */

get_header(); ?>

<?php get_sidebar(); ?>

	<div id="primary" class="content-area grid_9">
		<main id="main" class="site-main" role="main">

			<section class="error-404 not-found">
				<header class="page-header">
					<h1 class="page-title"><?php _e( 'Oops! That page can&rsquo;t be found.', 'jobswp' ); ?></h1>
				</header><!-- .page-header -->

				<div class="page-content">
					<p><?php _e( 'It looks like nothing was found at this location. Maybe try using the search form above?', 'jobswp' ); ?></p>
				</div><!-- .page-content -->
			</section><!-- .error-404 -->

		</main><!-- #main -->
	</div><!-- #primary -->

<?php get_footer(); ?>