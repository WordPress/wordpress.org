<?php
/**
 * Page template for the Get WordPress page.
 *
 * @package wporg-main
 */

require WPORGPATH . 'header.php';
?>

	<div id="page" class="site page-template-get">
		<a class="skip-link screen-reader-text" href="#main"><?php esc_html_e( 'Skip to content', 'wporg-main' ); ?></a>

		<div id="content" class="site-content">
		<header id="masthead" class="site-header" role="banner">
			<div class="site-branding">
				<h1 class="site-title">
					<a href="<?php echo esc_url( home_url( '/get/' ) ); ?>" rel="home">
						<?php _e( 'Get WordPress', 'wporg-main' ); ?>
					</a>
				</h1>

				<p class="site-description">
					<?php _e( 'Use the software that powers over 25% of the Internet.', 'wporg-main' ); ?>
				</p>
			</div><!-- .site-branding -->
		</header><!-- #masthead -->

		<main id="main" class="site-main" role="main">

			<?php while ( have_posts() ) : the_post(); ?>

			<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
				<div class="entry-content">

					<section class="download">
						<h2><?php _e( 'Priceless, and also free' ); ?></h2>
						<p class="subheading"><?php _e( 'Download WordPress and use it on your site.' ); ?></p>
						<div class="call-to-action">
							<button class="button button-primary button-xl"><?php _e( 'Download WordPress 4.4.2' ); ?></button>
						</div>

						<div class="container">
							<aside>
								<h4><?php _e( 'Requirements' ); ?></h4>
								<p class="aside"><?php printf( __( 'We recommend servers running version 5.6 or greater of <a href="%1$s">PHP</a>/<a href="%2$s">MySQL</a> and version 10.0 or greater of <a href="%3$s">MariaDB</a>.' ), '', '', '' ); ?></p>

								<p class="aside"><?php printf( __( 'We also recommend either <a href="%1$s">Apache</a> or <a href="%1$s">Nginx</a> as the most robust options for running WordPress, but neither is required.' ), '', '' ); ?></p>
							</aside><article>
								<h4><?php _e( 'Installation' ); ?></h4>
								<p><?php printf( __( 'With our famous 5-minute installation, setting up WordPress for the first time is simple. We’ve created a <a href="%1$s">handy guide</a> to see you through the installation process.' ), '' ); ?></p>
								<h4><?php _e( 'Release notifications' ); ?></h4>
								<p><?php printf( __( 'Want to get notified about WordPress releases? Join the <a href="%1$s">WordPress Announcements mailing list</a> and we will send a friendly message whenever there is a new stable release.' ), '' ); ?></p>
							</article>
						</div>
						<a href="#" class="call-to-action"><?php _e( 'Discover other ways to get WordPress' ); ?></a>
					</section>

					<section class="hosting">
						<span class="dashicons dashicons-cloud"></span>
						<h2><?php _e( 'WordPress Hosting' ); ?></h2>
						<p class="subheading"><?php _e( 'Choosing a hosting provider can be difficult, so we have selected a few of the best to get you started.' ); ?></p>

						<div class="three-up">
							<div>
								<img src="<?php echo esc_url( get_theme_file_uri( 'images/logo-bluehost.png' ) ); ?>" class="logo__bluehost" />
								<p><?php _e( 'Our optimized hosting is fast, secure, and simple. We are turning our passion for WordPress into the most amazing managed platform for your WordPress websites ever.' ); ?></p>
								<a href=""><?php _e( 'Visit Bluehost' ); ?></a>
							</div>
							<div>
								<img src="<?php echo esc_url( get_theme_file_uri( 'images/logo-getlisted.png' ) ); ?>" class="logo__getlisted" />
								<p><?php _e( 'Would you like to get your hosting company listed on this page? Click through and read up on how to do it. There’s a few hoops to jump through, but nothing impossible.' ); ?></p>
								<a href=""><?php _e( 'Get listed' ); ?></a>
							</div>
							<div>
								<img src="<?php echo esc_url( get_theme_file_uri( 'images/logo-wpcom.png' ) ); ?>" class="logo__wpcom" />
								<p><?php _e( 'WordPress.com is the easiest way to create a free website or blog. It’s a powerful hosting platform that grows with you. We offer expert support for your WordPress site.' ); ?></p>
								<a href=""><?php _e( 'Visit WordPress.com' ); ?></a>
							</div>
						</div>
						<a href="" class="call-to-action"><?php _e( 'See all of our recommended hosts' ); ?></a>
					</section>

					<section class="apps-mobile">
						<span class="dashicons dashicons-smartphone"></span>
						<h2><?php _e( 'Inspiration strikes anywhere, anytime' ); ?></h2>
						<p class="subheading"><?php _e( 'Create or update content on the go; try our mobile apps.' ); ?></p>

						<div class="web-stores">
							<a href="" class="button-ios"><img src="<?php echo esc_url( get_theme_file_uri( 'images/badge-apple.png' ) ); ?>" /></a>
							<a href="" class="button-android"><img src="<?php echo esc_url( get_theme_file_uri( 'images/badge-google-play.png' ) ); ?>" /></a>
						</div>
						<a href="" class="call-to-action"><?php _e( 'Learn more about our mobile apps' ); ?></a>
					</section>

				</div><!-- .entry-content -->

				<footer class="entry-footer">
					<?php
					edit_post_link(
						sprintf(
						/* translators: %s: Name of current post */
							esc_html__( 'Edit %s', 'wporg-main' ),
							the_title( '<span class="screen-reader-text">"', '"</span>', false )
						),
						'<span class="edit-link">',
						'</span>'
					);
					?>
				</footer><!-- .entry-footer -->
			</article><!-- #post-## -->

			<?php endwhile; ?>

		</main><!-- #main -->

<?php
get_footer();