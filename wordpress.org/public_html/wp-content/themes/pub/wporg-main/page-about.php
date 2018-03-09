<?php
/**
 * Template Name: About
 *
 * Page template for displaying the About page.
 *
 * @package WordPressdotorg\MainTheme
 */

namespace WordPressdotorg\MainTheme;

if ( false === stristr( home_url(), 'test' ) ) {
	return get_template_part( 'page' );
}

// Prevent Jetpack from looking for a non-existent featured image.
add_filter( 'jetpack_images_pre_get_images', function() {
	return new \WP_Error();
} );

add_filter( 'jetpack_open_graph_tags', function( $tags ) {
	$tags['og:title']            = _esc_html__( 'Democratize Publishing', 'wporg' );
	$tags['og:description']      = _esc_html__( 'WordPress is software designed for everyone with emphasis on accessibility, performance, security, and usability.', 'wporg' );
	$tags['twitter:text:title']  = $tags['og:title'];
	$tags['twitter:description'] = $tags['og:description'];

	return $tags;
} );

get_header();

the_post();
?>

	<main id="main" class="site-main col-12" role="main">

		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<header class="entry-header">
				<h1 class="entry-title"><?php _esc_html_e( 'Democratize Publishing', 'wporg' ); ?></h1>

				<p class="entry-description">
					<?php _esc_html_e( 'The freedom to build. The freedom to change. The freedom to share.', 'wporg' ); ?>
				</p>
			</header><!-- .entry-header -->

			<div class="entry-content row">
				<section class="col-8">
					<h2 id="mission"><?php _esc_html_e( 'Our Mission', 'wporg' ); ?></h2>
					<p><?php _esc_html_e( 'WordPress is software designed for everyone, emphasizing accessibility, performance, security, and ease of use. We believe great software should work with minimum set up, so you can focus on sharing your story, product, or services freely. The basic WordPress software is simple and predictable so you can easily get started. It also offers powerful features for growth and success.', 'wporg' ); ?></p>
					<p>
						<?php
						/* translators: Link to */
						printf( wp_kses_post( ___( 'We believe in democratizing publishing and the <a href="%s">freedoms that come with open source</a>. Supporting this idea is a large community of people collaborating on and contributing to this project. The WordPress community is welcoming and inclusive. Our contributors&#8217; passion drives the success of WordPress which, in turn, helps you reach your goals.', 'wporg' ) ), esc_url( 'https://opensource.org/osd-annotated' ) );
						?>
					</p>

					<div class="shapes">
						<a class="shape technology dashicons-before dashicons-welcome-widgets-menus" href="<?php echo esc_url( home_url( '/about/features/' ) ); ?>">
							<p>
								<strong><?php _esc_html_e( 'The Technology', 'wporg' ); ?></strong><br />
								<?php _esc_html_e( 'Learn about the software', 'wporg' ); ?>
							</p>
						</a>
						<a class="shape community dashicons-before dashicons-admin-site" href="https://make.wordpress.org/">
							<p>
								<strong><?php _esc_html_e( 'The Community', 'wporg' ); ?></strong><br />
								<?php _esc_html_e( 'Learn about the people', 'wporg' ); ?>
							</p>
						</a>
					</div>
					<p><?php _esc_html_e( 'WordPress contributors work around the globe, and have dedicated countless hours to build a tool that democratizes publishing. WordPress is open source software that is both free and priceless.', 'wporg' ); ?></p>
				</section>

				<section class="row gutters between">
					<div class="col-4">
						<h4><?php _esc_html_e( 'The Technology', 'wporg' ); ?></h4>
						<p><?php _esc_html_e( 'Learn about WordPress, where it&#8217;s been, and where it&#8217;s going.', 'wporg' ); ?></p>
						<ul>
							<li><a href="<?php echo esc_url( home_url( '/about/requirements/' ) ); ?>"><?php _esc_html_e( 'Requirements', 'wporg' ); ?></a></li>
							<li><a href="<?php echo esc_url( home_url( '/about/features/' ) ); ?>"><?php _esc_html_e( 'Features', 'wporg' ); ?></a></li>
							<li><a href="<?php echo esc_url( home_url( '/about/security/' ) ); ?>"><?php _esc_html_e( 'Security', 'wporg' ); ?></a></li>
							<li><a href="<?php echo esc_url( home_url( '/about/roadmap/' ) ); ?>"><?php _esc_html_e( 'Roadmap', 'wporg' ); ?></a></li>
							<li><a href="<?php echo esc_url( home_url( '/about/history/' ) ); ?>"><?php _esc_html_e( 'History', 'wporg' ); ?></a></li>
						</ul>
					</div>
					<div class="col-4">
						<h4><?php _esc_html_e( 'The Details', 'wporg' ); ?></h4>
						<p><?php _esc_html_e( 'There&#8217;s so much in the details. Stay abreast with the particulars.', 'wporg' ); ?></p>
						<ul>
							<li><a href="<?php echo esc_url( home_url( '/about/domains/' ) ); ?>"><?php _esc_html_e( 'Domains', 'wporg' ); ?></a></li>
							<li><a href="<?php echo esc_url( home_url( '/about/license/' ) ); ?>"><?php _esc_html_e( 'GNU Public License', 'wporg' ); ?></a></li>
							<li><a href="<?php echo esc_url( home_url( '/about/privacy/' ) ); ?>"><?php _esc_html_e( 'Privacy Policy', 'wporg' ); ?></a></li>
							<li><a href="<?php echo esc_url( home_url( '/about/stats/' ) ); ?>"><?php _esc_html_e( 'Statistics', 'wporg' ); ?></a></li>
						</ul>
					</div>
					<div class="col-4">
						<h4><?php _esc_html_e( 'The People', 'wporg' ); ?></h4>
						<p><?php _esc_html_e( 'Learn about the community and how we get along.', 'wporg' ); ?></p>
						<ul>
							<li><a href="<?php echo esc_url( home_url( '/about/philosophy/' ) ); ?>"><?php _esc_html_e( 'Philosophy', 'wporg' ); ?></a></li>
							<li><a href="<?php echo esc_url( home_url( '/about/etiquette/' ) ); ?>"><?php _esc_html_e( 'Etiquette', 'wporg' ); ?></a></li>
							<li><a href="<?php echo esc_url( home_url( '/about/swag/' ) ); ?>"><?php _esc_html_e( 'Swag', 'wporg' ); ?></a></li>
							<li><a href="<?php echo esc_url( home_url( '/about/logos/' ) ); ?>"><?php _esc_html_e( 'Logos and Graphics', 'wporg' ); ?></a></li>
						</ul>
					</div>
				</section>

				<section class="col-8">
					<h2 id="story"><?php _esc_html_e( 'Our Story', 'wporg' ); ?></h2>
					<p>
						<?php
						/* translators: 1: Link to b2/cafelog; 2: WordPress market share: 29%; */
						printf( wp_kses_post( ___( 'WordPress started in 2003 when Mike Little and Matt Mullenweg created a <a href="%1$s">fork of b2/cafelog</a>. The need for an elegant, well-architected personal publishing system was clear even then. Today, WordPress is built on PHP and MySQL, and licensed under the GPLv2. It is also the platform of choice for over %2$s of all sites across the web.', 'wporg' ) ), esc_url( 'https://www.whoishostingthis.com/resources/b2-cafelog/' ), esc_html( WP_MARKET_SHARE . '%' ) );
						?>
					</p>
					<p><?php _esc_html_e( 'The WordPress open source project has evolved in progressive ways over time &#8212; supported by skilled, enthusiastic developers, designers, scientists, bloggers, and more. WordPress provides the opportunity for anyone to create and share, from handcrafted personal anecdotes to world-changing movements. People with a limited tech experience can use it "out of the box", and more tech-savvy folks can customize it in remarkable ways.', 'wporg' ); ?></p>
				</section>

				<section class="col-8">
					<h2 id="bill-of-rights"><?php _esc_html_e( 'Bill of Rights', 'wporg' ); ?></h2>
					<p>
						<?php
						/* translators: Link to license page */
						printf( wp_kses_post( ___( 'WordPress is licensed under the <a href="%s">General Public License (GPLv2 or later)</a> which provides four core freedoms. Consider this the WordPress Bill of Rights:', 'wporg' ) ), esc_url( home_url( '/about/license/' ) ) );
						?>
					</p>
				</section>

				<section class="freedoms row gutters between">
					<div class="col-3">
						<div class="graphic"></div>
						<h4><?php _esc_html_e( 'The 1st Freedom', 'wporg' ); ?></h4>
						<p><?php _esc_html_e( 'To run the program for any purpose.', 'wporg' ); ?></p>
					</div>
					<div class="col-3">
						<div class="graphic"></div>
						<h4><?php _esc_html_e( 'The 2nd Freedom', 'wporg' ); ?></h4>
						<p><?php _esc_html_e( 'To study how the program works and change it to make it do what you wish.', 'wporg' ); ?></p>
					</div>
					<div class="col-3">
						<div class="graphic"></div>
						<h4><?php _esc_html_e( 'The 3rd Freedom', 'wporg' ); ?></h4>
						<p><?php _esc_html_e( 'To redistribute.', 'wporg' ); ?></p>
					</div>
					<div class="col-3">
						<div class="graphic"></div>
						<h4><?php _esc_html_e( 'The 4th Freedom', 'wporg' ); ?></h4>
						<p><?php _esc_html_e( 'To distribute copies of your modified versions to others.', 'wporg' ); ?></p>
					</div>
				</section>

			</div><!-- .entry-content -->

			<?php
			edit_post_link(
				sprintf(
					/* translators: %s: Name of current post */
					esc_html__( 'Edit %s', 'wporg' ),
					the_title( '<span class="screen-reader-text">"', '"</span>', false )
				),
				'<footer class="entry-footer"><span class="edit-link">',
				'</span></footer><!-- .entry-footer -->'
			);
			?>
		</article><!-- #post-## -->

	</main><!-- #main -->

<?php
get_footer();
