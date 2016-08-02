<?php
/**
 * The template for displaying 404 pages (not found).
 *
 * @link https://codex.wordpress.org/Creating_an_Error_404_Page
 *
 * @package WordPressdotorg\Plugin_Directory\Theme
 */

namespace WordPressdotorg\Plugin_Directory\Theme;

get_header(); ?>

	<main id="main" class="site-main" role="main">

		<section class="error-404 not-found">
			<header class="page-header">
				<h1 class="page-title"><?php esc_html_e( 'Oops! That page can&rsquo;t be found.', 'wporg-plugins' ); ?></h1>
			</header><!-- .page-header -->

			<div class="page-content">
				<p><?php printf( __( 'Try searching from the field above, or go to the <a href="%s">home page</a>.', 'wporg-plugins' ), get_home_url() ); ?></p>

				<div class="logo-swing">
					<img src="http://messislore.com/images/wp-logo-blue-trans-blur.png" class="wp-logo" />
					<img src="http://messislore.com/images/wp-logo-blue.png" class="wp-logo" />
				</div>
			</div><!-- .page-content -->
		</section><!-- .error-404 -->

	</main><!-- #main -->

<?php
get_footer();
