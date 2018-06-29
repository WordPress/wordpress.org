<?php
/**
 * Template Name: About -> Requirements
 *
 * Page template for displaying the Requirements page.
 *
 * @package WordPressdotorg\MainTheme
 */

namespace WordPressdotorg\MainTheme;

$GLOBALS['menu_items'] = [
	'about/requirements' => _x( 'Requirements', 'Page title', 'wporg' ),
	'about/features'     => _x( 'Features', 'Page title', 'wporg' ),
	'about/security'     => _x( 'Security', 'Page title', 'wporg' ),
	'about/roadmap'      => _x( 'Roadmap', 'Page title', 'wporg' ),
	'about/history'      => _x( 'History', 'Page title', 'wporg' ),
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
			<header class="entry-header">
				<h1 class="entry-title col-8"><?php the_title(); ?></h1>
			</header><!-- .entry-header -->

			<div class="entry-content row">
				<section class="col-8">
					<h3><?php esc_html_e( 'To run WordPress we recommend your host supports:', 'wporg' ); ?></h3>

					<ul>
						<li>
							<?php
							/* translators: 1: URL to PHP; 2: PHP Version */
							printf( wp_kses_post( __( '<a href="%1$s">PHP</a> version %2$s or greater.', 'wporg' ) ), esc_url( 'http://www.php.net/' ), '7.2' );
							?>
						</li>
						<li>
							<?php
							/* translators: 1: URL to MySQL; 2: MySQL Version; 3: URL to MariaDB; 4: MariaDB Version */
							printf( wp_kses_post( __( '<a href="%1$s">MySQL</a> version %2$s or greater <em>OR</em> <a href="%3$s">MariaDB</a> version %4$s or greater.', 'wporg' ) ), esc_url( 'https://www.mysql.com/' ), '5.6', esc_url( 'https://mariadb.org/' ), '10.0' );
							?>
						</li>
						<li>
							<?php
							/* translators: URL to news post */
							printf( wp_kses_post( __( '<a href="%s">HTTPS</a> support', 'wporg' ) ), esc_url( 'https://wordpress.org/news/2016/12/moving-toward-ssl/' ) );
							?>
						</li>
					</ul>

					<p>
						<?php
						/* translators: 1: URL to Apache; 2: URL to Nginx; 3: URL to hosting page */
						printf( wp_kses_post( __( 'That&#8217;s really it. We recommend <a href="%1$s">Apache</a> or <a href="%2$s">Nginx</a> as the most robust and featureful server for running WordPress, but any server that supports PHP and MySQL will do. That said, we can&#8217;t test every possible environment and <a href="%3$s">each of the hosts on our hosting page</a> supports the above and more with no problems.', 'wporg' ) ), esc_url( 'https://httpd.apache.org/' ), esc_url( 'https://nginx.org/' ), esc_url( home_url( '/hosting/' ) ) );
						?>
					</p>

					<p>
						<?php
						printf(
							/* translators: 1: PHP Version including; 2: MySQL Version */
							wp_kses_post( __( 'Note: If you are in a legacy environment where you only have older PHP or MySQL versions, WordPress also works with PHP %1$s+ and MySQL %2$s+, but these versions have reached official End Of Life and as such <strong>may expose your site to security vulnerabilities</strong>.', 'wporg' ) ),
							'5.2.4',
							'5.0'
						);
						?>
					</p>

					<h3><?php esc_html_e( 'Ask for it', 'wporg' ); ?></h3>

					<p><?php esc_html_e( 'Here&#8217;s a letter you can send to your host; copy and paste!', 'wporg' ); ?></p>

					<blockquote>
						<p><?php esc_html_e( 'I&#8217;m interested in running the open-source WordPress &lt;https://wordpress.org/&gt; web software and I was wondering if my account supported the following:', 'wporg' ); ?></p>

						<ul>
							<li>
								<?php
								/* translators: PHP Version */
								printf( esc_html__( 'PHP %s or greater', 'wporg' ), '7.2' );
								?>
							</li>
							<li>
								<?php
								/* translators: 1: MySQL version; 2: MariaDB Version */
								printf( esc_html__( 'MySQL %1$s or greater OR MariaDB %2$s or greater', 'wporg' ), '5.6', '10.0' );
								?>
							</li>
							<li><?php esc_html_e( 'Nginx or Apache with mod_rewrite module', 'wporg' ); ?></li>
							<li><?php esc_html_e( 'HTTPS support', 'wporg' ); ?></li>
						</ul>

						<p><?php esc_html_e( 'Thanks!', 'wporg' ); ?></p>
					</blockquote>

					<h3><?php esc_html_e( 'Not required, but recommended for better security', 'wporg' ); ?></h3>

					<p><?php esc_html_e( 'Hosting is more secure when PHP applications, like WordPress, are run using your account&#8217;s username instead of the server&#8217;s default shared username. Ask your potential host what steps they take to ensure the security of your account.', 'wporg' ); ?></p>
				</section>
			</div><!-- .entry-content -->

		</article><!-- #post-## -->

	</main><!-- #main -->

<?php
get_footer();
