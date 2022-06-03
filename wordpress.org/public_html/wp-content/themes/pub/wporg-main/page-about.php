<?php
/**
 * Template Name: About
 *
 * Page template for displaying the About page.
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
				<h1 class="entry-title"><?php esc_html_e( 'Democratize Publishing', 'wporg' ); ?></h1>

				<p class="entry-description">
					<?php esc_html_e( 'The freedom to build. The freedom to change. The freedom to share.', 'wporg' ); ?>
				</p>
			</header><!-- .entry-header -->

			<div class="entry-content row">
				<section class="col-8">
					<h2 class="h1" id="mission"><?php esc_html_e( 'Our Mission', 'wporg' ); ?></h2>
					<p><?php esc_html_e( 'WordPress is software designed for everyone, emphasizing accessibility, performance, security, and ease of use. We believe great software should work with minimum set up, so you can focus on sharing your story, product, or services freely. The basic WordPress software is simple and predictable so you can easily get started. It also offers powerful features for growth and success.', 'wporg' ); ?></p>
					<p>
						<?php
						/* translators: Link to */
						printf( wp_kses_post( __( 'We believe in democratizing publishing and the <a href="%s">freedoms that come with open source</a>. Supporting this idea is a large community of people collaborating on and contributing to this project. The WordPress community is welcoming and inclusive. Our contributors&#8217; passion drives the success of WordPress which, in turn, helps you reach your goals.', 'wporg' ) ), esc_url( 'https://opensource.org/osd-annotated' ) );
						?>
					</p>

					<div class="shapes">
						<a class="parallelogram technology dashicons-before dashicons-welcome-widgets-menus" href="<?php echo esc_url( home_url( '/about/features/' ) ); ?>">
							<p>
								<strong><?php esc_html_e( 'The Technology', 'wporg' ); ?></strong><br />
								<?php esc_html_e( 'Learn about the software', 'wporg' ); ?>
							</p>
						</a>
						<a class="parallelogram community dashicons-before dashicons-admin-site" href="https://make.wordpress.org/">
							<p>
								<strong><?php esc_html_e( 'The Community', 'wporg' ); ?></strong><br />
								<?php esc_html_e( 'Learn about the people', 'wporg' ); ?>
							</p>
						</a>
					</div>
					<p><?php esc_html_e( 'WordPress contributors work around the globe, and have dedicated countless hours to build a tool that democratizes publishing. WordPress is open source software that is both free and priceless.', 'wporg' ); ?></p>
				</section>

				<section class="areas row gutters between">
					<div class="col-4">
						<h3><?php esc_html_e( 'The Technology', 'wporg' ); ?></h3>
						<p><?php esc_html_e( 'Learn about WordPress, where it&#8217;s been, and where it&#8217;s going.', 'wporg' ); ?></p>
						<ul>
							<li><a href="<?php echo esc_url( home_url( '/about/requirements/' ) ); ?>"><?php echo esc_html_x( 'Requirements', 'Page title', 'wporg' ); ?></a></li>
							<li><a href="<?php echo esc_url( home_url( '/about/features/' ) ); ?>"><?php echo esc_html_x( 'Features', 'Page title', 'wporg' ); ?></a></li>
							<li><a href="<?php echo esc_url( home_url( '/about/security/' ) ); ?>"><?php echo esc_html_x( 'Security', 'Page title', 'wporg' ); ?></a></li>
							<li><a href="<?php echo esc_url( home_url( '/about/roadmap/' ) ); ?>"><?php echo esc_html_x( 'Roadmap', 'Page title', 'wporg' ); ?></a></li>
							<li><a href="<?php echo esc_url( home_url( '/about/history/' ) ); ?>"><?php echo esc_html_x( 'History', 'Page title', 'wporg' ); ?></a></li>
						</ul>
					</div>
					<div class="col-4">
						<h3><?php esc_html_e( 'The Details', 'wporg' ); ?></h3>
						<p><?php esc_html_e( 'There&#8217;s so much in the details. Stay abreast with the particulars.', 'wporg' ); ?></p>
						<ul>
							<li><a href="<?php echo esc_url( home_url( '/about/domains/' ) ); ?>"><?php echo esc_html_x( 'Domains', 'Page title', 'wporg' ); ?></a></li>
							<li><a href="<?php echo esc_url( home_url( '/about/license/' ) ); ?>"><?php echo esc_html_x( 'GNU Public License', 'Page title', 'wporg' ); ?></a></li>
							<li><a href="<?php echo esc_url( home_url( '/about/accessibility/' ) ); ?>"><?php echo esc_html_x( 'Accessibility', 'Page title', 'wporg' ); ?></a></li>
							<li><a href="<?php echo esc_url( home_url( '/about/privacy/' ) ); ?>"><?php echo esc_html_x( 'Privacy Policy', 'Page title', 'wporg' ); ?></a></li>
							<li><a href="<?php echo esc_url( home_url( '/about/stats/' ) ); ?>"><?php echo esc_html_x( 'Statistics', 'Page title', 'wporg' ); ?></a></li>
						</ul>
					</div>
					<div class="col-4">
						<h3><?php esc_html_e( 'The People', 'wporg' ); ?></h3>
						<p><?php esc_html_e( 'Learn about the community and how we get along.', 'wporg' ); ?></p>
						<ul>
							<li><a href="<?php echo esc_url( home_url( '/about/philosophy/' ) ); ?>"><?php echo esc_html_x( 'Philosophy', 'Page title', 'wporg' ); ?></a></li>
							<li><a href="<?php echo esc_url( home_url( '/about/etiquette/' ) ); ?>"><?php echo esc_html_x( 'Etiquette', 'Page title', 'wporg' ); ?></a></li>
							<li><a href="<?php echo esc_url( home_url( '/about/swag/' ) ); ?>"><?php echo esc_html_x( 'Swag', 'Page title', 'wporg' ); ?></a></li>
							<li><a href="<?php echo esc_url( home_url( '/about/logos/' ) ); ?>"><?php echo esc_html_x( 'Logos and Graphics', 'Page title', 'wporg' ); ?></a></li>
							<li><a href="<?php echo esc_url( home_url( '/about/testimonials/' ) ); ?>"><?php echo esc_html_x( 'Testimonials', 'Page title', 'wporg' ); ?></a></li>
						</ul>
					</div>
				</section>

				<section class="col-8">
					<h2 class="h1" id="story"><?php esc_html_e( 'Our Story', 'wporg' ); ?></h2>
					<p>
						<?php
						/* translators: 1: Link to b2/cafelog; 2: WordPress market share: 30 - Note: The following percent sign is '%%' for escaping purposes; */
						printf( wp_kses_post( __( 'WordPress started in 2003 when Mike Little and Matt Mullenweg created a fork of <a href="%1$s">b2/cafelog</a>. The need for an elegant, well-architected personal publishing system was clear even then. Today, WordPress is built on PHP and MySQL, and licensed under the GPLv2. It is also the platform of choice for over %2$s%% of all sites across the web.', 'wporg' ) ), esc_url( 'https://cafelog.com/' ), esc_html( number_format_i18n( WP_MARKET_SHARE ) ) );
						?>
					</p>
					<p><?php esc_html_e( 'The WordPress open source project has evolved in progressive ways over time &#8212; supported by skilled, enthusiastic developers, designers, scientists, bloggers, and more. WordPress provides the opportunity for anyone to create and share, from handcrafted personal anecdotes to world-changing movements. People with a limited tech experience can use it &#8220;out of the box&#8221;, and more tech-savvy folks can customize it in remarkable ways.', 'wporg' ); ?></p>
				</section>

				<section class="col-8">
					<h2 class="h1" id="bill-of-rights"><?php esc_html_e( 'Bill of Rights', 'wporg' ); ?></h2>
					<p>
						<?php
						/* translators: Link to license page */
						printf( wp_kses_post( __( 'WordPress is licensed under the <a href="%s">General Public License (GPLv2 or later)</a> which provides four core freedoms. Consider this the WordPress Bill of Rights:', 'wporg' ) ), esc_url( home_url( '/about/license/' ) ) );
						?>
					</p>
				</section>

				<section class="freedoms row gutters between">
					<div class="col-3">
						<div class="graphic"></div>
						<h3><?php esc_html_e( 'The 1st Freedom', 'wporg' ); ?></h3>
						<p><?php esc_html_e( 'To run the program for any purpose.', 'wporg' ); ?></p>
					</div>
					<div class="col-3">
						<div class="graphic"></div>
						<h3><?php esc_html_e( 'The 2nd Freedom', 'wporg' ); ?></h3>
						<p><?php esc_html_e( 'To study how the program works and change it to make it do what you wish.', 'wporg' ); ?></p>
					</div>
					<div class="col-3">
						<div class="graphic"></div>
						<h3><?php esc_html_e( 'The 3rd Freedom', 'wporg' ); ?></h3>
						<p><?php esc_html_e( 'To redistribute.', 'wporg' ); ?></p>
					</div>
					<div class="col-3">
						<div class="graphic"></div>
						<h3><?php esc_html_e( 'The 4th Freedom', 'wporg' ); ?></h3>
						<p><?php esc_html_e( 'To distribute copies of your modified versions to others.', 'wporg' ); ?></p>
					</div>
				</section>

			</div><!-- .entry-content -->

		</article><!-- #post-## -->

	</main><!-- #main -->

<?php
get_footer();
