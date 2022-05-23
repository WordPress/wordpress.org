<?php
/**
 * Template Name: Download
 *
 * Page template for the Download page.
 *
 * @package WordPressdotorg\MainTheme
 */

namespace WordPressdotorg\MainTheme;
use WordPressdotorg\API\Serve_Happy\RECOMMENDED_PHP;

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
		'logo'        => 'images/logo-siteground.svg',
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
$latest_release_zip_ts    = defined( 'WPORG_WP_RELEASES_PATH' ) ? filemtime( WPORG_WP_RELEASES_PATH . 'wordpress-' . WP_CORE_LATEST_RELEASE . '.zip' ) : time();
$latest_release_zip_url   = 'https://wordpress.org/latest.zip';
$latest_release_targz_url = 'https://wordpress.org/latest.tar.gz';

if ( defined( 'IS_ROSETTA_NETWORK' ) && IS_ROSETTA_NETWORK ) {
	$rosetta_release = $GLOBALS['rosetta']->rosetta->get_latest_public_release();
	if ( $rosetta_release ) {
		$locale                   = get_locale();
		$latest_release_version   = $rosetta_release['version'];
		$latest_release_zip_ts    = $rosetta_release['builton'];
		$latest_release_zip_url   = home_url( "latest-{$locale}.zip" );
		$latest_release_targz_url = home_url( "latest-{$locale}.tar.gz" );
	}
}

// Prevent Jetpack from looking for a non-existent featured image.
add_filter( 'jetpack_images_pre_get_images', function() {
	return new \WP_Error();
} );

// Add Schema.org structured data.
add_action( 'wp_head', function() use( $latest_release_version, $latest_release_zip_url, $latest_release_zip_ts ) {
	$graph_tags  = custom_open_graph_tags();
	$description = $graph_tags[ 'description' ] ?? '';
	?>
	<script type="application/ld+json">
	[
		{
			"@context": "http://schema.org",
			"@type": [
				"SoftwareApplication",
				"Product"
			],
			"name": "WordPress",
			"operatingSystem": [ "Linux", "Windows", "Unix", "Apache", "NGINX" ],
			"url": "<?php the_permalink(); ?>",
			"description": <?php echo wp_json_encode( $description ); ?>,
			"softwareVersion": <?php echo wp_json_encode( $latest_release_version ); ?>,
			"fileFormat": "application/zip",
			"downloadUrl": "<?php echo esc_url( $latest_release_zip_url ); ?>",
			"dateModified": "<?php echo gmdate( 'Y-m-d\TH:i:s\+00:00', $latest_release_zip_ts ); ?>",
			"applicationCategory": "WebApplication",
			"offers": {
				"@type": "Offer",
				"url": "<?php the_permalink(); ?>",
				"price": "0.00",
				"priceCurrency": "USD",
				"seller": {
					"@type": "Organization",
					"name": "WordPress.org",
					"url": "https://wordpress.org"
				}
			}
		}
	]
	</script>
	<?php
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
						/* translators: WordPress market share: 39 - Note: The following percent sign is '%%' for escaping purposes; */
						esc_html__( 'Use the software that powers over %s%% of the web.', 'wporg' ),
						esc_html( number_format_i18n( WP_MARKET_SHARE ) )
					);
					?>
				</p>
			</header><!-- .entry-header -->

			<div class="entry-content row">
				<p>
					<?php printf(
						wp_kses_data( __(
							'There are several ways to get WordPress. The <strong>easiest</strong> is <a href="%1$s">through a hosting provider</a>, but sometimes <strong>tech-savvy</strong> folks prefer to <a href="%2$s">download and install</a> it themselves.',
							'wporg'
						) ),
						'#hosting',
						'#download-install'
					); ?>
				</p>

				<p>
					<?php printf(
						wp_kses_data( __( 'Either way, you can use your WordPress through a web browser and with <a href="%s">our mobile apps</a>.', 'wporg' ) ),
						'#mobile'
					); ?>
				</p>

				<aside id="mobile" class="apps-mobile">
					<span class="dashicons dashicons-smartphone"></span>
					<h2><?php esc_html_e( 'Inspiration strikes anywhere, anytime', 'wporg' ); ?></h2>
					<p class="subheading"><?php esc_html_e( 'Create or update content on the go with our mobile apps.', 'wporg' ); ?></p>

					<div class="web-stores">
						<a href="https://itunes.apple.com/app/apple-store/id335703880?pt=299112&ct=wordpress.org&mt=8" class="button-ios" >
							<img src="<?php echo esc_url( get_theme_file_uri( 'images/badge-apple.png' ) ); ?>" alt="<?php esc_attr_e( 'Available in the Apple App Store', 'wporg' ); ?>" />
						</a>
						<a href="http://play.google.com/store/apps/details?id=org.wordpress.android" class="button-android">
							<img src="<?php echo esc_url( get_theme_file_uri( 'images/badge-google-play.png' ) ); ?>"  alt="<?php esc_attr_e( 'Available in the Google Play Store', 'wporg' ); ?>" />
						</a>
					</div>
					<a href="https://apps.wordpress.com/mobile/" class="call-to-action"><?php esc_html_e( 'Learn more about our mobile apps', 'wporg' ); ?></a>
				</aside>

				<aside id="hosting" class="hosting row gutters between">
					<div class="parallelogram"></div>
					<span class="dashicons dashicons-cloud"></span>
					<h2><?php esc_html_e( 'WordPress Hosting', 'wporg' ); ?></h2>
					<p class="subheading col-8"><?php esc_html_e( 'Choosing a hosting provider can be difficult, so we have selected a few of the best to get you started.', 'wporg' ); ?></p>

					<?php foreach ( array_rand( $hosts, 2 ) as $host ) : ?>

					<div class="host col-6" data-nosnippet>
						<img src="<?php echo esc_url( get_theme_file_uri( $hosts[ $host ]['logo'] ) ); ?>" class="logo" alt="<?php
							/* translators: %s: Name of hosting company */
							printf( esc_attr__( '%s company logo', 'wporg' ), esc_html( $hosts[ $host ]['name'] ) );
							?>"
						/>
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
				</aside>

				<section id="download-install" class="download row gutters between">
					<h2><?php esc_html_e( 'Priceless, and also free', 'wporg' ); ?></h2>
					<p class="subheading"><?php esc_html_e( 'Download WordPress and use it on your site.', 'wporg' ); ?></p>
					<div class="call-to-action col-12">
						<a id="download-wordpress" class="button button-primary button-xl" href="<?php echo esc_url( $latest_release_zip_url ); ?>">
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
								esc_url( __( 'https://wordpress.org/support/article/how-to-install-wordpress/', 'wporg' ) )
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
								'https://wordpress.org/list/'
							);
							?>
						</p>
					</aside>

					<aside class="col-6">
						<h4><?php esc_html_e( 'Requirements', 'wporg' ); ?></h4>
						<p>
							<?php
							printf(
								/* translators: 1: PHP version; 2: URL to PHP website; 3: URL to MySQL website; 4: MySQL version; 5: URL to MariaDB website; 6: MariaDB version */
								wp_kses_post( __( 'We recommend servers running version %1$s or greater of <a href="%2$s">PHP</a> and <a href="%3$s">MySQL</a> version %4$s <em>OR</em> <a href="%5$s">MariaDB</a> version %6$s or greater.', 'wporg' ) ),
								RECOMMENDED_PHP,
								'https://www.php.net/',
								'https://www.mysql.com/',
								'5.7',
								'https://mariadb.org/',
								'10.3'
							);
							?>
							<br>
							<?php
							printf(
								/* translators: 1: URL to Apache website; 2: URL to Nginx website */
								wp_kses_post( __( 'We also recommend either <a href="%1$s">Apache</a> or <a href="%2$s">Nginx</a> as the most robust options for running WordPress, but neither is required.', 'wporg' ) ),
								'https://httpd.apache.org/',
								'https://nginx.org/'
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

				<div id="after-download" class="modal" role="dialog" aria-modal="true" tabindex="0">
					<div role="document">
						<header class="entry-header">
							<h1 id="after-download-title" class="entry-title"><?php esc_html_e( 'Hooray!', 'wporg' ); ?></h1>
							<p class="entry-description"><?php esc_html_e( 'You&#8217;re on your way with the latest WordPress!', 'wporg' ); ?></p>
						</header>
						<div>
						<p>
							<?php
							printf(
								__( 'For help getting started, check out our <a href="%s">Documentation and Support Forums</a>.', 'wporg' ),
								esc_url( __( 'https://wordpress.org/support/', 'wporg' ) )
							);
							?>
						</p>
						<p>
							<?php
							printf(
								/* translators: 1: URL to WordPress Meetup group, 2: URL to WordCamp Central */
								__( 'Meet other WordPress enthusiasts and share your knowledge at a <a href="%1$s">WordPress meetup group</a> or a <a href="%2$s">WordCamp</a>.', 'wporg' ),
								esc_url( __( 'https://www.meetup.com/pro/wordpress/', 'wporg' ) ),
								esc_url( __( 'https://central.wordcamp.org/', 'wporg' ) )
							);
							?>
						</p>
						<p>
							<?php
							printf(
								__( 'To support education about WordPress and open source software, please donate to the <a href="%s">WordPress Foundation</a>.', 'wporg' ),
								esc_url( __( 'https://wordpressfoundation.org/donate/', 'wporg' ) )
							);
							?>
						</p>
						</div>
					</div>
				</div>

			</div><!-- .entry-content -->
		</article><!-- #post-## -->
	</main><!-- #main -->

<?php
get_footer();
