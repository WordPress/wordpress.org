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
					<div class="shape technology">
						<a class="dashicons-before dashicons-welcome-widgets-menus" href="">
							<h3><?php _esc_html_e( 'The Technology', 'wporg' ); ?></h3>
							<p><?php _esc_html_e( 'Learn about the software', 'wporg' ); ?></p>
						</a>
					</div>
					<div class="shape community">
						<a class="dashicons-before dashicons-admin-site" href="">
							<h3><?php _esc_html_e( 'The Community', 'wporg' ); ?></h3>
							<p><?php _esc_html_e( 'Learn about the people', 'wporg' ); ?></p>
						</a>
					</div>
					<p><?php _esc_html_e( 'We work around the globe, and have contributed countless hours to build a future wherein we can all be proud. WordPress is an open source project that is both free and priceless.', 'wporg' ); ?></p>
				</section>

				<section class="row gutters between">
					<div class="col-4">
						<h4><?php _esc_html_e( 'The Technology', 'wporg' ); ?></h4>
						<p><?php _esc_html_e( 'Learn about WordPress, where we&#8217;ve been, and where we&#8217;re going.', 'wporg' ); ?></p>
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
				</section>

				<section class="col-8">
					<h2 id="bill-of-rights"><?php _esc_html_e( 'Bill of Rights', 'wporg' ); ?></h2>
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
