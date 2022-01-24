<?php

/**
 * Template Name: Hosting
 *
 * @package WordPressdotorg\MainTheme
 */

namespace WordPressdotorg\MainTheme;

// Prevent Jetpack from looking for a non-existent featured image.
add_filter( 'jetpack_images_pre_get_images', function() {
	return new \WP_Error();
} );

/* See inc/page-meta-descriptions.php for the meta description for this page. */

get_header( 'top-level-page' );
the_post();
$hosting_cache_buster = '3';

?>

	<main id="main" class="site-main col-12" role="main">
		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<div class="entry-content row">

				<section class="col-8">
					<p>There are hundreds of thousands of web hosts out there, the vast majority of which meet the
						<a href="https://wordpress.org/about/requirements/">WordPress minimum requirements</a>
						, and choosing one from the crowd can be a chore. Just like flowers need the right environment to grow, WordPress works best when it&#8217;s in a rich hosting environment.
					</p>

					<p>
						We&#8217;ve dealt with more hosts than you can imagine; in our opinion, the hosts below represent some of the best and brightest of the hosting world. If you do decide to go with one of the hosts below and click through from this page, some will donate a portion of your fee back&#8212;so you can have a great host and support WordPress.org at the same time. If you&#8217;d like to try WordPress for free, you can get started with a free website or blog at
						<a href="https://wordpress.com/">WordPress.com</a>
						.
					</p>
				</section>

				<section>
					<div class="partner">
						<h2>
							<a href="https://www.bluehost.com/wordpress-hosting" rel="nofollow">
								<img
									alt=""
									src="https://s.w.org/hosting/bluehost.png?<?php echo $hosting_cache_buster; ?>"
									height="100"
									width="100"
								/>
								Bluehost
							</a>
						</h2>

						<p>Powering over 2 million websites, Bluehost offers the ultimate WordPress platform. Tuned for WordPress, we offer WordPress-centric dashboards and tools along with 1-click installation, a FREE domain name, email, FTP, and more. Easily scalable and backed by legendary 24/7 support by in-house WordPress experts.</p>

						<div class="forum">
							<a href="https://wordpress.org/support/topic-tag/bluehost/">Forum threads about Bluehost &raquo;</a>
						</div>
					</div>

					<div class="partner">
						<h2>
							<a href="https://www.dreamhost.com/wordpress-hosting/" rel="nofollow">
								<img
									alt=""
									src="https://s.w.org/hosting/dreamhost.png?<?php echo $hosting_cache_buster; ?>"
									height="100"
									width="100"
								/>
								DreamHost
							</a>
						</h2>

						<p>DreamHost has been committed to WordPress and its community for over 10 years. Our hosting platforms are optimized for WordPress and our team actively contributes to the WordPress community. At DreamHost, you take total control of your server or let our team of experts handle everything for you. DreamHost offers choice, performance and value for new users and experts alike.</p>

						<div class="forum">
							<a href="https://wordpress.org/support/topic-tag/dreamhost/">Forum threads about DreamHost &raquo;</a>
						</div>
					</div>

					<div class="partner">
						<h2>
							<a href="https://www.siteground.com/wordpress-hosting.htm" rel="nofollow">
								<img
									alt=""
									src="https://s.w.org/hosting/siteground.png?<?php echo $hosting_cache_buster; ?>"
									height="100"
									width="100"
								/>
								SiteGround
							</a>
						</h2>

						<p>SiteGround has tools that make managing WordPress sites easy: one-click install, managed updates, WP-Cli, WordPress staging and git integration. We have a very fast support team with advanced WordPress expertise available 24/7. We provide latest speed technologies that make WordPress load faster: NGINX-based caching, SSD-drives, PHP 7, CDN, HTTP/2. We proactively protect the WordPress sites from hacks.</p>

						<div class="forum">
							<a href="https://wordpress.org/support/topic-tag/siteground/">Forum threads about SiteGround &raquo;</a>
						</div>
					</div>

				</section>

				<section class="col-8">
					<h2>Host Feedback</h2>

					<p>We&#8217;re committed to helping create a wholesome and hassle-free WordPress hosting environment. If you feel there are issues with one of
						the hosts listed here, please send a note to hosting dash feedback at this domain. If the situation warrants we&#8217;ll work with you and
						your host on a solution.
					</p>

					<p>Note before contacting us: Please don&#8217;t send us legal takedown orders or threats, we don&#8217;t actually host every WordPress blog in the world.
						If you don&#8217;t understand that, you probably shouldn&#8217;t be sending legal notices anyway.
					</p>

					<h2>Be Listed on This Page</h2>

					<p>We&#8217;ll be looking at this list several times a year, so keep an eye out for us re-opening the survey for hosts to submit themselves for inclusion.
						Listing is completely arbitrary, but includes criteria like: contributions to WordPress.org, size of customer base, ease of WP auto-install and auto-upgrades, avoiding GPL violations, design, tone, historical perception, using the correct logo, capitalizing WordPress correctly, not blaming us if you have a security issue, and up-to-date system software.
					</p>
				</section>

			</div><!-- .entry-content -->
		</article><!-- #post-## -->
	</main><!-- #main -->

<?php

get_footer();
