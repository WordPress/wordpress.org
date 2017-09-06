<?php
/**
 * The main template file.
 *
 * This is the most generic template file in a WordPress theme
 * and one of the two required files for a theme (the other being style.css).
 * It is used to display a page when nothing more specific matches a query.
 * E.g., it puts together the home page when no home.php file exists.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package WordPressdotorg\Theme
 */

namespace WordPressdotorg\MainTheme;

require WPORGPATH . 'header.php';
?>
	<header id="masthead" class="site-header" role="banner">
		<div class="site-branding">
			<p class="site-title"><?php _e( 'Meet WordPress', 'wporg-main' ); ?></p>

			<p class="site-description"><?php _e( 'WordPress is open source software you can use to create a beautiful website, blog, or app' ); ?></p>
		</div><!-- .site-branding -->
	</header><!-- #masthead -->

	<main id="main" class="site-main " role="main">
		<div class="home-welcome">
			<div id="lang-guess-wrap"></div>

			<section class="intro">
				<p class="subheading"><?php _e( 'Beautiful designs, powerful features, and the freedom to build anything you want. WordPress is both free and priceless at the same time.' ); ?></p>
				<div class="screenshots">
					<img src="https://s.w.org/images/home/screen-themes.png?1" class="dashboard" />
					<img src="https://s.w.org/images/home/iphone-themes.png?1" class="dashboard-mobile" />
				</div>
			</section>

			<section class="showcase">
				<h2><?php _e( 'Trusted by the Best' ); ?></h2>
				<p class="subheading"><?php _e( '28% of the web uses WordPress, from hobby blogs to the biggest news sites online.' ); ?></p>
				<div class="collage">

				</div>
				<p class="cta-link"><a href="https://wordpress.org/showcase/"><?php _e( 'Discover more sites built with WordPress' ); ?></a>.</p>
			</section>

			<section class="features">
				<h2><?php _e( 'Powerful Features' ); ?></h2>
				<p class="subheading"><?php _e( 'Limitless possibilities. What will you create?' ); ?></p>
				<ul>
					<li>
						<span class="dashicons dashicons-admin-customizer"></span>
						<?php _e( 'Customizable<br />Designs' ); ?>
					</li>
					<li>
						<span class="dashicons dashicons-welcome-widgets-menus"></span>
						<?php _e( 'SEO<br />Friendly' ); ?>
					</li>
					<li>
						<span class="dashicons dashicons-smartphone"></span>
						<?php _e( 'Responsive<br />Mobile Sites' ); ?>
					</li>
					<li>
						<span class="dashicons dashicons-chart-line"></span>
						<?php _e( 'High<br />Performance' ); ?>
					</li>
					<li>
						<a href="https://wordpress.org/mobile/"><img src="https://s.w.org/images/home/icon-run-blue.svg" />
							<?php _e( 'Manage<br />on the Go' ); ?></a>
					</li>
					<li>
						<span class="dashicons dashicons-lock"></span>
						<?php _e( 'High<br />Security' ); ?>
					</li>
					<li>
						<span class="dashicons dashicons-images-alt2"></span>
						<?php _e( 'Powerful<br />Media Management' ); ?>
					</li>
					<li>
						<span class="dashicons dashicons-universal-access"></span>
						<?php _e( 'Easy and<br />Accessible' ); ?>
					</li>
				</ul>
				<p><?php
					/* translators: %s: Link to Plugin Directory. */
					printf( __( 'Extend WordPress with over 45,000 plugins to help your website meet your needs. Add an online store, galleries, mailing lists, forums, analytics, and <a href="%s">much more</a>.' ), esc_url( 'https://wordpress.org/plugins/' ) ); ?></p>
			</section>

			<section class="community-2">
				<div class="screen"></div>
				<div class="container">
					<h2><?php _e( 'Community' ); ?></h2>
					<p class="subheading"><?php _e( 'Hundreds of thousands of developers, content creators, and site owners gather at monthly meetups in 436 cities worldwide' ); ?>.</p>
				</div>
			</section>

			<section class="get">
				<h2><?php _e( 'Get Started with WordPress' ); ?></h2>
				<p class="subheading"><?php _e( 'Over 60 million people have chosen WordPress to power the place on the web they call &ldquo;home&rdquo; &mdash; join the family.' ); ?></p>
				<div class="cta-wrapper">
					<a href="https://wordpress.org/download/" class="button button-primary button-xl"><?php _e( 'Get WordPress' ); ?></a>
				</div>
			</section>
		</div>

		<div id="home-below" class="home-below">
			<div class="col-2">
				<h4><a href="https://wordpress.org/about/swag/"><?php _e( 'WordPress&nbsp;Swag' ); ?></a></h4>
				<a href="https://wordpress.org/about/swag/"><img width="132" height="177" src="https://wpdotorg.files.wordpress.com/2015/10/gray-tshirt-swag.jpg" alt="<?php esc_attr_e( 'WordPress Swag' ); ?>" /></a>
			</div>

			<div class="col-4">
				<h4><a href="https://wordpress.org/news/"><?php _e( 'News From Our Blog' ); ?></a></h4>

				<?php
				$featured = new \WP_Query( [ 'posts_per_page' => 1 ] );
				while ( $featured->have_posts() ) :
					$featured->the_post();
					the_title( sprintf( '<h5><a href="%s" rel="bookmark">', esc_url( get_permalink() ) ), '</a></h5>' );
					echo '<p>' . apply_filters( 'the_excerpt', get_the_excerpt() ) . '</p>';
				endwhile;
				wp_reset_query();
				?>
			</div>

			<div class="col-4">
				<h4><?php _e( 'It&rsquo;s Easy&nbsp;As&hellip;' ); ?></h4>

				<ol class="steps">
					<li class="one"><span></span><?php printf( __( '<a href="%s">Find a Web Host</a> and get great hosting while supporting WordPress at the same&nbsp;time.' ), esc_url( 'https://wordpress.org/hosting/' ) ); ?></li>
					<li class="two"><span></span><?php printf( __( '<a href="%s">Download &amp; Install WordPress</a> with our famous 5-minute&nbsp;installation. Feel like a rock star.' ), esc_url( 'https://wordpress.org/download/' ) ); ?></li>
					<li class="three"><span></span><?php printf( __( '<a href="%s">Read the Documentation</a> and become a WordPress expert yourself, impress your friends.' ), esc_url( 'https://developer.wordpress.org' ) ); ?></li>
				</ol>
			</div>

			<div class="col-2">
				<h4><a href="https://wordpress.org/showcase/"><?php _e( 'WordPress&nbsp;Users' ); ?></a></h4>

				<ul id="notable-users">
					<?php
						$links = array(
							'nytimes'       => 'https://wordpress.org/showcase/tag/new-york-times/',
							'cnn'           => 'https://wordpress.org/showcase/tag/cnn/',
							'rollingstones' => 'https://wordpress.org/showcase/the-rolling-stones/',
							'people'        => 'https://wordpress.org/showcase/stylewatch-off-the-rack/',
							'playstation'   => 'https://wordpress.org/showcase/playstationblog/',
							'motleycrue'    => 'https://wordpress.org/showcase/motley-crue/',
							'blondie'       => 'https://wordpress.org/showcase/blondie/',
							'marthastewart' => 'https://wordpress.org/showcase/themarthablog/',
						);

						foreach ( array_rand( $links, 3 ) as $slug ) :
							printf(
								'<li><a href="%1$s"><img src="https://s.w.org/images/notableusers/%2$s-2x.png" alt="%2$s" width="130" height="57" /></a></li>',
								$links[ $slug ],
								$slug
							);
						endforeach;
					?>
				</ul>
				<p id="showcase-link"><a href="https://wordpress.org/showcase/"><?php _e( '&hellip; and hundreds more' ); ?></a></p>
			</div>
		</div>

	</main><!-- #main -->

	<?php
get_footer();
