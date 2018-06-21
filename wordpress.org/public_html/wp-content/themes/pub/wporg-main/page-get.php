<?php
/**
 * Template Name: Get
 *
 * Page template for the Get WordPress page.
 *
 * @package WordPressdotorg\MainTheme
 */

namespace WordPressdotorg\MainTheme;

// Prevent Jetpack from looking for a non-existent featured image.
add_filter( 'jetpack_images_pre_get_images', function() {
	return new \WP_Error();
} );

/* See inc/page-meta-descriptions.php for the meta description for this page. */

get_header();
the_post();
?>
	<main id="main" class="site-main col-12" role="main">

		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<header class="entry-header">
				<h1 class="entry-title"><?php esc_html_e( 'Get WordPress', 'wporg' ); ?></h1>

				<p class="entry-description">
					<?php
					printf(
						/* translators: WordPress market share: 30 - Note: The following percent sign is '%%' for escaping purposes; */
						esc_html__( 'Use the software that powers over %s%% of the web.', 'wporg' ),
						esc_html( number_format_i18n( WP_MARKET_SHARE ) )
					);
					?>
				</p>
			</header><!-- .entry-header -->

			<div class="entry-content row">

				<section class="download row gutters between">
					<h2><?php esc_html_e( 'Priceless, and also free', 'wporg' ); ?></h2>
					<p class="subheading"><?php esc_html_e( 'Download WordPress and use it on your site.', 'wporg' ); ?></p>
					<div class="call-to-action col-12">
						<a class="button button-primary button-xl" href="<?php echo esc_url( home_url( 'latest.zip' ) ); ?>">
							<span class="dashicons-before dashicons-download">
								<?php
								echo apply_filters( 'no_orphans', sprintf(
									/* translators: WordPress version. */
									esc_html__( 'Download WordPress %s', 'wporg' ),
									esc_html( WP_CORE_LATEST_RELEASE )
								) );
								?>
							</span>
						</a>
					</div>
					<aside class="col-8">
						<h4><?php esc_html_e( 'Installation', 'wporg' ); ?></h4>
						<p>
							<?php
							printf(
								/* translators: URL to installation guide */
								wp_kses_post( __( 'With our famous 5-minute installation, setting up WordPress for the first time is simple. We’ve created a <a href="%1$s">handy guide</a> to see you through the installation process.', 'wporg' ) ),
								esc_url( 'https://codex.wordpress.org/Installing_WordPress#Famous_5-Minute_Installation' )
							);
							?>
						</p>
						<h4><?php esc_html_e( 'Release notifications', 'wporg' ); ?></h4>
						<p>
							<?php
							printf(
								/* translators: URL to WordPress mailing list */
								wp_kses_post( __( 'Want to get notified about WordPress releases? Join the <a href="%1$s">WordPress Announcements mailing list</a> and we will send a friendly message whenever there is a new stable release.', 'wporg' ) ),
								esc_url( 'https://wordpress.org/list/' )
							);
							?>
						</p>
					</aside>

					<aside class="col-4">
						<h4><?php esc_html_e( 'Requirements', 'wporg' ); ?></h4>
						<p class="aside">
							<?php
							printf(
								/* translators: 1: URL to PHP website; 2: URL to MySQL website; 3: URL to MariaDB website */
								wp_kses_post( __( 'We recommend servers running version 5.6 or greater of <a href="%1$s">PHP</a>/<a href="%2$s">MySQL</a> and version 10.0 or greater of <a href="%3$s">MariaDB</a>.', 'wporg' ) ),
								esc_url( 'http://www.php.net/' ),
								esc_url( 'https://www.mysql.com/' ),
								esc_url( 'https://mariadb.org/' )
							);
							?>
						</p>

						<p class="aside">
							<?php
							printf(
								/* translators: 1: URL to Apache website; 2: URL to Nginx website */
								wp_kses_post( __( 'We also recommend either <a href="%1$s">Apache</a> or <a href="%1$s">Nginx</a> as the most robust options for running WordPress, but neither is required.', 'wporg' ) ),
								esc_url( 'https://httpd.apache.org/' ),
								esc_url( 'https://nginx.org/' )
							);
							?>
						</p>
					</aside>
					<a href="<?php echo esc_url( home_url( '/download/' ) ); ?>" class="call-to-action col-12"><?php esc_html_e( 'Discover other ways to get WordPress', 'wporg' ); ?></a>
				</section>

				<section class="hosting row gutters between">
					<span class="dashicons dashicons-cloud"></span>
					<h2><?php esc_html_e( 'WordPress Hosting', 'wporg' ); ?></h2>
					<p class="subheading col-8"><?php esc_html_e( 'Choosing a hosting provider can be difficult, so we have selected a few of the best to get you started.', 'wporg' ); ?></p>

					<div class="host col-6">
						<img src="<?php echo esc_url( get_theme_file_uri( 'images/logo-bluehost.png' ) ); ?>" class="logo__bluehost" />
						<p><?php esc_html_e( 'Our optimized hosting is fast, secure, and simple. We are turning our passion for WordPress into the most amazing managed platform for your WordPress websites ever.', 'wporg' ); ?></p>
						<a href="https://www.bluehost.com/wordpress-hosting"><?php esc_html_e( 'Visit Bluehost', 'wporg' ); ?></a>
					</div>
					<div class="host col-6">
						<img src="<?php echo esc_url( get_theme_file_uri( 'images/logo-wpcom.png' ) ); ?>" class="logo__wpcom" />
						<p><?php esc_html_e( 'WordPress.com is the easiest way to create a free website or blog. It’s a powerful hosting platform that grows with you. We offer expert support for your WordPress site.', 'wporg' ); ?></p>
						<a href="https://wordpress.com/"><?php esc_html_e( 'Visit WordPress.com', 'wporg' ); ?></a>
					</div>
					<a href="<?php echo esc_url( home_url( '/hosting/' ) ); ?>" class="call-to-action col-12"><?php esc_html_e( 'See all of our recommended hosts', 'wporg' ); ?></a>
				</section>

				<section class="apps-mobile first-sm">
					<span class="dashicons dashicons-smartphone"></span>
					<h2><?php esc_html_e( 'Inspiration strikes anywhere, anytime', 'wporg' ); ?></h2>
					<p class="subheading"><?php esc_html_e( 'Create or update content on the go with our mobile apps.', 'wporg' ); ?></p>

					<div class="web-stores">
						<a href="http://appstore.com/WordPress" class="button-ios"><img src="<?php echo esc_url( get_theme_file_uri( 'images/badge-apple.png' ) ); ?>" /></a>
						<a href="http://play.google.com/store/apps/details?id=org.wordpress.android" class="button-android"><img src="<?php echo esc_url( get_theme_file_uri( 'images/badge-google-play.png' ) ); ?>" /></a>
					</div>
					<a href="https://apps.wordpress.com/mobile/" class="call-to-action"><?php esc_html_e( 'Learn more about our mobile apps', 'wporg' ); ?></a>
				</section>

			</div><!-- .entry-content -->
		</article><!-- #post-## -->
	</main><!-- #main -->

<?php
get_footer();
