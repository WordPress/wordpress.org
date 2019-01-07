<?php
/**
 * The template for displaying 404 pages (not found)
 *
 * @link https://codex.wordpress.org/Creating_an_Error_404_Page
 *
 * @package Gutenbergtheme
 */

get_header(); ?>

	<div id="primary" class="content-area">
		<main id="main" class="site-main">

			<section class="error-404 not-found">
				<header class="page-header">
					<h1 class="page-title"><?php esc_html_e( 'Oops! That page can&rsquo;t be found.', 'gutenbergtheme' ); ?></h1>
				</header><!-- .page-header -->

				<div class="page-content">
					<p><?php
						printf(
							__( 'It looks like nothing was found at this location. Maybe try the <a href="%s">Gutenberg Handbook</a> or a search?', 'gutenbergtheme' ),
							home_url( '/handbook/' )
						);
					?></p>

					<?php
						get_search_form();
					?>
				</div><!-- .page-content -->
			</section><!-- .error-404 -->

		</main><!-- #main -->
	</div><!-- #primary -->

<?php
get_footer();
