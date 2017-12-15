<?php
/**
 * The template for displaying 404 pages (not found).
 *
 * @link https://codex.wordpress.org/Creating_an_Error_404_Page
 *
 * @package WordPressdotorg\Plugin_Directory\Theme
 */

namespace WordPressdotorg\Theme;

get_header(); ?>

	<main id="main" class="site-main col-12" role="main">

		<section class="error-404 not-found">
			<header class="page-header">
				<h1 class="page-title"><?php esc_html_e( 'Oops! That page can&rsquo;t be found.', 'wporg' ); ?></h1>
			</header><!-- .page-header -->

			<div class="page-content">
				<p>
					<?php
					printf(
						/* translators: Home URL. */
						__( 'Try searching from the field above, or go to the <a href="%s">home page</a>.', 'wporg' ), // phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped
						esc_url( get_home_url() )
					);
					?>
				</p>

				<div class="logo-swing">
					<img src="<?php echo esc_url( get_theme_file_uri( '/images/wp-logo-blue-trans-blur.png' ) ); ?>" class="wp-logo" />
					<img id="hinge" src="<?php echo esc_url( get_theme_file_uri( '/images/wp-logo-blue.png' ) ); ?>" class="wp-logo hinge" />
				</div>
			</div><!-- .page-content -->
		</section><!-- .error-404 -->

	</main><!-- #main -->

	<script>
		setTimeout( function() {
			document.getElementById( 'hinge' ).hidden = true;
		}, 1900 );
	</script>

<?php

get_footer();
