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

// phpcs:disable WordPress.XSS.EscapeOutput.UnsafePrintingFunction, WordPress.XSS.EscapeOutput.OutputNotEscaped

namespace WordPressdotorg\MainTheme;

global $rosetta;

$showcase = false;
if ( is_object( $rosetta ) && $rosetta->showcase instanceof \Rosetta_Showcase ) {
	$showcase = $rosetta->showcase->front();
}

$swag_class = $showcase ? 'col-4' : 'col-2';
$user_class = $showcase ? 'col-12' : 'col-2';

// The blocks code sets up the layout, but there is also inline CSS to refine things that aren't supported in classic themes.
$banner_blocks = '<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|30","bottom":"var:preset|spacing|30","left":"var:preset|spacing|30","right":"var:preset|spacing|30"}}},"backgroundColor":"black","className":"wporg-homepage-banner","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull wporg-homepage-banner has-black-background-color has-background" style="padding-top:var(--wp--preset--spacing--30);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--30);padding-left:var(--wp--preset--spacing--30)"><!-- wp:group {"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"center"}} -->
<div class="wp-block-group"><!-- wp:image {"sizeSlug":"full","className":"is-resized"} -->
<figure class="wp-block-image size-full is-resized"><img src="https://wordpress.org/files/2024/03/wcasia-white-rectangle.png" alt="' . esc_attr__( 'WordCamp Asia 2024', 'wporg' ) . '"/></figure>
<!-- /wp:image -->

<!-- wp:group {"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"style":{"typography":{"lineHeight":1.6},"elements":{"link":{"color":{"text":"var:preset|color|white"},":hover":{"color":{"text":"var:preset|color|white"}}}},"spacing":{"margin":{"top":"0"}}},"textColor":"white","fontSize":"small"} -->
<p class="has-white-color has-text-color has-link-color has-small-font-size" style="margin-top:0;line-height:1.6">' . __( 'Watch the Q&amp;A session with the WordPress project&#039;s co-founder, Matt Mullenweg, recorded live from WordCamp Asia 2024. <a href="https://wordpress.org/news/2024/03/highlights-from-wordcamp-asia-2024/">Read the event highlights</a>.', 'wporg' ) . '</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"typography":{"lineHeight":1.6},"elements":{"link":{"color":{"text":"var:preset|color|white"},":hover":{"color":{"text":"var:preset|color|white"}}}},"spacing":{"margin":{"bottom":"0"}}},"textColor":"white","fontSize":"small"} -->
<p class="has-white-color has-text-color has-link-color has-small-font-size" style="margin-bottom:0;line-height:1.6">' . __( '<a href="https://www.youtube.com/watch?v=EOF70YJLC5U"><strong>Watch now ↗</strong></a>', 'wporg' ) . '</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->';

\WordPressdotorg\skip_to( '#masthead' );

get_header( 'wporg' );
?>
	<aside id="download-mobile">
		<span class="download-ready"><?php _e( 'Ready to get started?', 'wporg' ); ?></span><a class="button download-button" href="/download/"><?php _e( 'Get WordPress', 'wporg' ); ?></a>
	</aside>

	<style>
		/* Set a few custom properties as they appear in the parent theme. */
		.wporg-homepage-banner {
			--wp--preset--spacing--20: 20px;
			--wp--preset--spacing--30: 30px;
			--wp--preset--spacing--60: clamp(20px, calc(10vw - 40px), 80px);
			--wp--preset--font-size--small: 14px;
		}
		.wporg-homepage-banner a:hover {
			text-decoration: none;
		}
		.wporg-homepage-banner > * {
			margin-left: auto !important;
			margin-right: auto !important;
			max-width: 1160px;
		}
		.wporg-homepage-banner .is-layout-flex {
			gap: var(--wp--preset--spacing--20) var(--wp--preset--spacing--60);
		}
		.wporg-homepage-banner .is-layout-flex > * {
			flex: 1;
		}
		@media (max-width: 650px) {
			.wporg-homepage-banner .is-layout-flex {
				flex-direction: column;
			}
		}
	</style>
	<?php echo do_blocks( $banner_blocks ); ?>

	<header id="masthead" class="site-header" role="banner">
		<div class="site-branding">
			<p class="site-title"><?php _e( 'Meet WordPress', 'wporg' ); ?></p>

			<p class="site-description"><?php _e( 'WordPress is open source software you can use to create a beautiful website, blog, or app.', 'wporg' ); ?></p>
		</div><!-- .site-branding -->
	</header><!-- #masthead -->

	<main id="main" class="site-main " role="main">
		<div class="home-welcome">
			<div id="lang-guess-wrap"></div>

			<section class="intro">
				<p class="subheading"><?php _e( 'Beautiful designs, powerful features, and the freedom to build anything you want. WordPress is both free and priceless at the same time.', 'wporg' ); ?></p>
				<div class="screenshots">
					<img src="https://s.w.org/images/home/screen-themes.png?4" class="dashboard" />
					<img src="https://s.w.org/images/home/mobile-themes.png?4" class="dashboard-mobile" />
				</div>
			</section>

			<section class="showcase">
				<h2><?php _e( 'Trusted by the Best', 'wporg' ); ?></h2>
				<p class="subheading">
					<?php
					printf(
						/* translators: WordPress market share: 30 - Note: The following percent sign is '%%' for escaping purposes; */
						__( '%s%% of the web uses WordPress, from hobby blogs to the biggest news sites online.', 'wporg' ),
						number_format_i18n( WP_MARKET_SHARE )
					);
					?>
				</p>
				<div class="collage">

				</div>
				<p class="cta-link"><a href="https://wordpress.org/showcase/"><?php _e( 'Discover more sites built with WordPress', 'wporg' ); ?></a></p>
			</section>

			<section class="features">
				<h2><?php _e( 'Powerful Features', 'wporg' ); ?></h2>
				<p class="subheading"><?php _e( 'Limitless possibilities. What will you create?', 'wporg' ); ?></p>
				<ul>
					<li>
						<span class="dashicons dashicons-admin-customizer"></span>
						<?php _e( 'Customizable<br />Designs', 'wporg' ); ?>
					</li>
					<li>
						<span class="dashicons dashicons-welcome-widgets-menus"></span>
						<?php _e( 'SEO<br />Friendly', 'wporg' ); ?>
					</li>
					<li>
						<span class="dashicons dashicons-smartphone"></span>
						<?php _e( 'Responsive<br />Mobile Sites', 'wporg' ); ?>
					</li>
					<li>
						<span class="dashicons dashicons-chart-line"></span>
						<?php _e( 'High<br />Performance', 'wporg' ); ?>
					</li>
					<li>
						<a href="https://wordpress.org/mobile/"><img src="https://s.w.org/images/home/icon-run-blue.svg" />
							<?php _e( 'Manage<br />on the Go', 'wporg' ); ?></a>
					</li>
					<li>
						<span class="dashicons dashicons-lock"></span>
						<?php _e( 'High<br />Security', 'wporg' ); ?>
					</li>
					<li>
						<span class="dashicons dashicons-images-alt2"></span>
						<?php _e( 'Powerful<br />Media Management', 'wporg' ); ?>
					</li>
					<li>
						<span class="dashicons dashicons-universal-access"></span>
						<?php _e( 'Easy and<br />Accessible', 'wporg' ); ?>
					</li>
				</ul>
				<p>
					<?php
					$plugin_count = defined( 'WP_PLUGIN_COUNT' ) ? WP_PLUGIN_COUNT : 54000;
					printf(
						/* translators: 1: Rounded number of plugins. 2: Link to Plugin Directory. */
						_n(
							'Extend WordPress with over %1$s plugin to help your website meet your needs. Add an online store, galleries, mailing lists, forums, analytics, and <a href="%2$s">much more</a>.',
							'Extend WordPress with over %1$s plugins to help your website meet your needs. Add an online store, galleries, mailing lists, forums, analytics, and <a href="%2$s">much more</a>.',
							$plugin_count,
							'wporg'
						),
						esc_html( number_format_i18n( $plugin_count ) ),
						esc_url( home_url( '/plugins/' ) )
					);
					?>
				</p>
			</section>

			<section class="community-2">
				<div class="screen"></div>
				<div class="container">
					<h2><?php _e( 'Community', 'wporg' ); ?></h2>
					<p class="subheading">
						<?php
						$meetups = 817;

						printf(
							/* translators: Number of meetups. */
							_n(
								'Hundreds of thousands of developers, content creators, and site owners gather at monthly meetups in %s city worldwide.',
								'Hundreds of thousands of developers, content creators, and site owners gather at monthly meetups in %s cities worldwide.',
								$meetups,
								'wporg'
							),
							number_format_i18n( $meetups )
						);
						?>
					</p>
					<a class="button button-secondary button-large" href="https://make.wordpress.org/community/meetups-landing-page"><?php _e( 'Find a local WordPress community', 'wporg' ); ?></a>
				</div>
			</section>

			<section class="get">
				<h2><?php _e( 'Get Started with WordPress', 'wporg' ); ?></h2>
				<p class="subheading"><?php _e( 'Over 60 million people have chosen WordPress to power the place on the web they call &ldquo;home&rdquo; &mdash; join the family.', 'wporg' ); ?></p>
				<div class="cta-wrapper">
					<a href="<?php echo esc_url( get_downloads_url() ); ?>" class="button button-primary button-xl"><?php _e( 'Get WordPress', 'wporg' ); ?></a>
				</div>
			</section>
		</div>

		<div id="home-below" class="home-below row gutters">
			<div class="col-4">
				<?php
				$switched      = false;
				$news_blog_url = get_permalink( get_option( 'page_for_posts' ) );
				$featured_args = [
					'posts_per_page'      => 1,
					'post_status'         => 'publish',
					'ignore_sticky_posts' => true,
					'no_found_rows'       => true,
				];
				$featured = new \WP_Query( $featured_args );

				// Fetch posts from the English News blog if this site doesn't have any.
				if ( ! $featured->have_posts() && defined( 'WPORG_NEWS_BLOGID' ) ) {
					$switched      = switch_to_blog( WPORG_NEWS_BLOGID );
					$featured      = new \WP_Query( $featured_args );
					$news_blog_url = home_url( '/' );
				}

				printf(
					'<h4><a href="%s">%s</a></h4>',
					esc_url( $news_blog_url ),
					__( 'News From Our Blog', 'wporg' )
				);

				// Forcibly hide all Jetpack sharing buttons.
				add_filter( 'sharing_show', '__return_false' );

				while ( $featured->have_posts() ) {
					$featured->the_post();

					the_title( sprintf( '<h5><a href="%s" rel="bookmark">', esc_url( get_permalink() ) ), '</a></h5>' );
					echo '<div class="entry-summary">' . apply_filters( 'the_excerpt', get_the_excerpt() ) . '</div>';
				}

				remove_filter( 'sharing_show', '__return_false' );

				wp_reset_postdata();

				if ( $switched ) {
					restore_current_blog();
				}
				?>
			</div>

			<div class="col-4">
				<h4><?php _e( 'It&rsquo;s Easy&nbsp;As&hellip;', 'wporg' ); ?></h4>

				<ol class="steps">
					<li class="one">
						<span></span>
						<?php
						printf(
							/* translators: URL to Hosting page. */
							__( '<a href="%s">Find a trusted web host</a> and maybe support WordPress at the same&nbsp;time.', 'wporg' ),
							esc_url( 'https://wordpress.org/hosting/' )
						);
						?>
					</li>
					<li class="two">
						<span></span>
						<?php
						printf(
							/* translators: URL to Downloads page. */
							__( '<a href="%s">Download &amp; install WordPress</a> with our famous 5-minute&nbsp;installation. Publishing has never been&nbsp;easier.', 'wporg' ),
							esc_url( get_downloads_url() )
						);
						?>
					</li>
					<li class="three">
						<span></span>
						<?php
						printf(
							/* translators: URL to HelpHub. */
							__( '<a href="%s">Spend some time reading our documentation</a>, get to know WordPress better every day and start helping others,&nbsp;too.', 'wporg' ),
							esc_url( __( 'https://wordpress.org/support/', 'wporg' ) )
						);
						?>
					</li>
				</ol>
			</div>

			<div class="<?php echo esc_attr( $swag_class ); ?> first">
				<h4><a href="https://mercantile.wordpress.org/"><?php _e( 'WordPress&nbsp;Swag', 'wporg' ); ?></a></h4>
				<a href="https://mercantile.wordpress.org/">
					<?php if ( $showcase ) : ?>
						<img width="288" height="288" src="https://s.w.org/images/home/swag_col-2.png" srcset="https://s.w.org/images/home/swag_col-2_x2.png 2x" alt="<?php esc_attr_e( 'WordPress Swag', 'wporg' ); ?>" />
					<?php else : ?>
						<img width="132" height="177" src="https://s.w.org/images/home/swag_col-1.jpg?1" alt="<?php esc_attr_e( 'WordPress Swag', 'wporg' ); ?>" />
					<?php endif; ?>
				</a>
			</div>

			<div class="<?php echo esc_attr( $user_class ); ?>">
				<h4><a href="https://wordpress.org/showcase/"><?php _e( 'WordPress&nbsp;Users', 'wporg' ); ?></a></h4>

				<?php if ( $showcase ) : ?>
					<div id="notable-users" class="notable-users col-12 row gutters">
						<?php
						foreach ( $showcase as $showcase_post ) :
							$post_url  = get_permalink( $showcase_post->ID );
							$thumbnail = has_post_thumbnail( $showcase_post->ID )
								? get_the_post_thumbnail( $showcase_post->ID, 'showcase-thumbnail' )
								: sprintf( '<img src="%1$s" width="220" loading="lazy" alt="%2$s" />', esc_url( $rosetta->screenshot_url( $post_url, 220 ) ), esc_attr( $showcase_post->post_title ) );

							printf(
								'<div class="col-3"><a href="%1$s">%2$s</a></div>',
								esc_url( $post_url ),
								$thumbnail
							);
						endforeach;
						?>
					</div>
				<?php else : ?>
					<ul id="notable-users" class="notable-users">
						<?php
						$user_links = [
							'whitehouse'    => 'https://wordpress.org/showcase/the-white-house/',
							'rolling-stone' => 'https://wordpress.org/showcase/rolling-stone/',
							'bbc-america'   => 'https://wordpress.org/showcase/bbc-america/',
							'unicef-uk'     => 'https://wordpress.org/showcase/unicef-uk/',
							'pioneer-woman' => 'https://wordpress.org/showcase/the-pioneer-woman',
							'playstation'   => 'https://wordpress.org/showcase/playstationblog/',
							'blondie'       => 'https://wordpress.org/showcase/blondie/',
						];

						foreach ( array_rand( $user_links, 3 ) as $slug ) :
							printf(
								'<li><a href="%1$s"><img src="https://s.w.org/images/notableusers/%2$s-2x.png?version=2" alt="%2$s" width="130" height="57" /></a></li>',
								$user_links[ $slug ],
								$slug
							);
						endforeach;
						?>
					</ul>
				<?php endif; ?>

				<a class="showcase-link" href="https://wordpress.org/showcase/"><?php _e( '&hellip; and hundreds more', 'wporg' ); ?></a>
			</div>
		</div>

	</main><!-- #main -->

<?php
get_footer( 'wporg' );
