<?php
/**
 * The template for displaying 404 pages (Not Found).
 *
 * @package wporg-developer
 */

get_header(); ?>

	<div id="primary" class="content-area">
		<main id="main" class="site-main" role="main">

			<section class="error-404 not-found">
				<header class="page-header">
					<h1 class="page-title"><?php _e( 'That page can&rsquo;t be found.', 'wporg' ); ?></h1>
				</header><!-- .page-header -->

				<div class="reference-landing">
					<div class="search-guide section clear">
						<h4 class="ref-intro"><?php _e( 'It looks like nothing was found at this location. Maybe try a search?', 'wporg' ); ?></h4>
						<?php get_search_form(); ?>
					</div><!-- /search-guide -->
				</div><!-- .reference-landing -->
			</section><!-- .error-404 -->

		</main><!-- #main -->
	</div><!-- #primary -->

<?php get_footer(); ?>