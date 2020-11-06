<?php
/**
 * Template Name: Download -> Beta/Nightly
 *
 * Page template for displaying the Beta/Nightly page.
 *
 * @package WordPressdotorg\MainTheme
 */

namespace WordPressdotorg\MainTheme;

$GLOBALS['menu_items'] = [
	'download/releases'     => _x( 'Releases', 'Page title', 'wporg' ),
	'download/beta-nightly' => _x( 'Beta/Nightly', 'Page title', 'wporg' ),
	'download/counter'      => _x( 'Counter', 'Page title', 'wporg' ),
	'download/source'       => _x( 'Source Code', 'Page title', 'wporg' ),
];

// Prevent Jetpack from looking for a non-existent featured image.
add_filter( 'jetpack_images_pre_get_images', function() {
	return new \WP_Error();
} );

/* See inc/page-meta-descriptions.php for the meta description for this page. */

get_header( 'child-page' );
the_post();
?>

	<main id="main" class="site-main col-12" role="main">

		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<header class="entry-header row">
				<?php the_title( '<h1 class="entry-title col-8">', '</h1>' ); ?>
			</header><!-- .entry-header -->

			<div class="entry-content row">
				<section class="col-8">
					<h3><?php esc_html_e( 'Unstable Beta Versions', 'wporg' ); ?></h3>
					<p>
						<?php
						printf(
							/* translators: URL to Core Trac */
							wp_kses_post( __( 'If you are comfortable with PHP and would like to participate in the testing portion of our development cycle and <a href="%s">report bugs you find</a>, beta releases might be for you.', 'wporg' ) ),
							esc_url( 'https://core.trac.wordpress.org/newticket' )
						);
						?>
					</p>

					<p>
						<?php
						printf(
							/* translators: 1: URL to documentation; 2: URL to make/core; 3: URL to beta forum */
							wp_kses_post( __( 'By their nature these releases are unstable and should not be used any place where your data is important. Please <a href="%1$s">backup your database</a> before upgrading to a beta release. To hear about the latest beta releases your best bet is to watch <a href="%2$s">the development blog</a> and <a href="%3$s">the beta forum</a>.', 'wporg' ) ),
							esc_url( __( 'https://wordpress.org/support/article/backing-up-your-database/', 'wporg' ) ),
							esc_url( 'https://make.wordpress.org/core/' ),
							esc_url( __( 'https://wordpress.org/support/forum/alphabeta/', 'wporg' ) )
						);
						?>
					</p>

					<p>
						<?php
						printf(
							/* translators: %s Link to https://wordpress.org/download/releases/#betas */
							__( 'You can find the latest beta releases on the <a href="%s">Beta Releases</a> page.', 'wporg' ),
							'https://wordpress.org/download/releases/#betas'
						);
						?>
					</p>

					<h3><?php esc_html_e( 'Nightly Builds', 'wporg' ); ?></h3>
					<p><?php esc_html_e( 'Development of WordPress moves fairly quickly and day-to-day things break as often as they are fixed. This high churn is part of our development process that aims to produce the most stable releases possible.', 'wporg' ); ?></p>

					<p>
						<?php
						printf(
							/* translators: URL to documentation */
							wp_kses_post( __( 'If you would like to be part of this process, the best place to start is the <a href="%s">Beta Testing Handbook</a>.', 'wporg' ) ),
							esc_url( 'https://make.wordpress.org/core/handbook/testing/beta/' )
						);
						?>
					</p>

					<p>
						<?php
						printf(
							/* translators: %s Link to the latest nightly release ZIP. */
							__( 'You can download the latest nightly release here: <a href="%s">wordpress-latest.zip</a>.', 'wporg' ),
							'https://wordpress.org/nightly-builds/wordpress-latest.zip'
						);
						?>
					</p>

				</section>
			</div><!-- .entry-content -->
		</article><!-- #post-## -->

	</main><!-- #main -->

<?php
get_footer();
