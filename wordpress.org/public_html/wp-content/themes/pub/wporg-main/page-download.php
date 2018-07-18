<?php
/**
 * Template Name: Download
 *
 * Page template for the Download page.
 *
 * @package WordPressdotorg\MainTheme
 */

namespace WordPressdotorg\MainTheme;

$hosts = [
	'bluehost'      => [
		'description' => __( 'Bluehost has turned passion for WordPress into the fastest, simplest managed platform for your websites. Recommended by WordPress since 2005, each WordPress package offers a free domain, free SSL, and 24/7 support.', 'wporg' ),
		'logo'        => 'images/logo-bluehost.svg',
		'name'        => 'Bluehost',
		'url'         => 'https://www.bluehost.com/wordpress-hosting',
	],
	'dreamhost'     => [
		'description' => __( 'Privacy-focused and dedicated to the Open Web, DreamHost provides some of the most powerful and secure managed WordPress environments in the world.', 'wporg' ),
		'logo'        => 'images/logo-dreamhost.png',
		'name'        => 'DreamHost',
		'url'         => 'https://www.dreamhost.com/wordpress-hosting/',
	],
	'siteground'    => [
		'description' => __( 'SiteGround offers top-tier website performance and support regardless of your technical skill. Join millions of happy clients using their WordPress services to get the help you need at prices you love.', 'wporg' ),
		'logo'        => 'images/logo-siteground.png',
		'name'        => 'Siteground',
		'url'         => 'https://www.siteground.com/wordpress-hosting.htm',
	],
	'wordpress.com' => [
		'description' => __( 'WordPress.com is the easiest way to create a free website or blog. It’s a powerful hosting platform that grows with you. We offer expert support for your WordPress site.', 'wporg' ),
		'logo'        => 'images/logo-wpcom.png',
		'name'        => 'WordPress.com',
		'url'         => 'https://wordpress.com/',
	],
];
shuffle( $hosts );

$menu_items = [
	'download/releases/'     => _x( 'All Releases', 'Page title', 'wporg' ),
	'download/beta-nightly/' => _x( 'Beta/Nightly Versions', 'Page title', 'wporg' ),
	'download/counter/'      => _x( 'Download Counter', 'Page title', 'wporg' ),
	'download/source/'       => _x( 'Source Code', 'Page title', 'wporg' ),
];

$latest_release_version   = WP_CORE_LATEST_RELEASE;
$latest_release_zip_url   = 'https://wordpress.org/latest.zip';
$latest_release_targz_url = 'https://wordpress.org/latest.tar.gz';

if ( defined( 'IS_ROSETTA_NETWORK' ) && IS_ROSETTA_NETWORK ) {
	$rosetta_release = $GLOBALS['rosetta']->rosetta->get_latest_public_release();
	if ( $rosetta_release ) {
		$latest_release_version   = $rosetta_release['version'];
		$latest_release_zip_url   = $rosetta_release['zip_url'];
		$latest_release_targz_url = $rosetta_release['targz_url'];
	}
}

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
						<a class="button button-primary button-xl" href="<?php echo esc_url( $latest_release_zip_url ); ?>">
							<span class="dashicons-before dashicons-download">
								<?php
								echo esc_html( apply_filters( 'no_orphans', sprintf(
									/* translators: WordPress version. */
									__( 'Download WordPress %s', 'wporg' ),
									$latest_release_version
								) ) );
								?>
							</span>
						</a>
						<p>
							<a href="<?php echo esc_url( $latest_release_targz_url ); ?>">
								<?php esc_html_e( 'Download .tar.gz', 'wporg' ); ?>
							</a>
						</p>
					</div>

					<aside class="col-6">
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
					</aside>

					<aside class="col-6">
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

					<aside class="col-6">
						<h4><?php esc_html_e( 'Requirements', 'wporg' ); ?></h4>
						<p>
							<?php
							printf(
								/* translators: 1: URL to PHP website; 2: URL to MySQL website; 3: URL to MariaDB website */
								wp_kses_post( __( 'We recommend servers running version 7.2 or greater of <a href="%1$s">PHP</a> and <a href="%2$s">MySQL</a> version 5.6 <em>OR</em> <a href="%3$s">MariaDB</a> version 10.0 or greater.', 'wporg' ) ),
								esc_url( 'http://www.php.net/' ),
								esc_url( 'https://www.mysql.com/' ),
								esc_url( 'https://mariadb.org/' )
							);
							?>
							<br>
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

					<aside class="col-6">
						<h4><?php esc_html_e( 'More resources', 'wporg' ); ?></h4>
						<ul>
							<?php foreach ( $menu_items as $slug => $text ) : ?>
								<li><a href="<?php echo esc_url( home_url( $slug ) ); ?>"><?php echo esc_html( $text ); ?></a></li>
							<?php endforeach; ?>
						</ul>
					</aside>
				</section>

				<section class="hosting row gutters between">
					<div class="parallelogram"></div>
					<span class="dashicons dashicons-cloud"></span>
					<h2><?php esc_html_e( 'WordPress Hosting', 'wporg' ); ?></h2>
					<p class="subheading col-8"><?php esc_html_e( 'Choosing a hosting provider can be difficult, so we have selected a few of the best to get you started.', 'wporg' ); ?></p>

					<?php foreach ( array_rand( $hosts, 2 ) as $host ) : ?>
					<div class="host col-6">
						<img src="<?php echo esc_url( get_theme_file_uri( $hosts[ $host ]['logo'] ) ); ?>" class="logo" />
						<p><?php echo esc_html( $hosts[ $host ]['description'] ); ?></p>
						<a href="<?php echo esc_url( $hosts[ $host ]['url'] ); ?>">
							<?php
							/* translators: Name of hosting company */
							printf( esc_html__( 'Visit %s', 'wporg' ), esc_html( $hosts[ $host ]['name'] ) );
							?>
						</a>
					</div>
					<?php endforeach; ?>

					<a href="https://wordpress.org/hosting/" class="call-to-action col-12"><?php esc_html_e( 'See all of our recommended hosts', 'wporg' ); ?></a>
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
