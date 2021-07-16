<?php
/**
 * The template for displaying 404 pages (not found).
 *
 * @link https://codex.wordpress.org/Creating_an_Error_404_Page
 *
 * @package WordPressdotorg\Pattern_Directory\Theme
 */

namespace WordPressdotorg\Pattern_Directory\Theme;

get_header(); ?>

	<main id="main" class="site-main col-12" role="main">

		<div class="error-404 not-found">
			<div class="page-container aligncenter">
				<header class="page-header">
					<h1 class="page-title"><?php esc_html_e( 'Oops! This page doesn&rsquo;t exist.', 'wporg-patterns' ); ?></h1>
				</header><!-- .page-header -->

				<p>
					<?php
					printf(
						/* translators: %1$s: WordPress.org URL, %2$s: Patterns home URL. */
						__( 'Head to <a href="%1$s">the WordPress.org home</a>, view <a href="%2$s">all block patterns</a>, or try searching.', 'wporg-patterns' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						esc_url( get_home_url() ),
						esc_url( get_home_url() )
					);
					?>
				</p>
				<?php get_search_form(); ?>
			</div><!-- .page-container -->
		</div><!-- .error-404 -->

	</main><!-- #main -->
<?php

get_footer();
