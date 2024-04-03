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
$hosting_cache_buster = '6';

?>

	<main id="main" class="site-main col-12" role="main">
		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<div class="entry-content row">

				<section class="col-8">
					<p>There are hundreds of thousands of web hosts out there, the vast majority of which meet the <a href="https://wordpress.org/about/requirements/">WordPress minimum requirements</a>, and choosing one from the crowd can be a chore. Just like flowers need the right environment to grow, WordPress works best when it&#8217;s in a rich hosting environment.</p>

					<p>We&#8217;ve dealt with more hosts than you can imagine; in our opinion, the hosts below represent some of the best and brightest of the hosting world. If you do decide to go with one of the hosts below and click through from this page, some will donate a portion of your fee back&#8212;so you can have a great host and support WordPress.org at the same time. If you&#8217;d like to try WordPress for free, you can get started with a free website or blog at <a href="https://wordpress.com/wordpress-free/">WordPress.com</a>.</p>
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

						<p>Bluehost is WordPress.org's longest running recommended host and offers the ultimate WordPress platform that powers millions of websites. Their shared hosting is benchmarked as delivering best-in-class performance, and for those that demand the fastest speed, 100% uptime and expert support, Bluehost Cloud Hosting offers unmatched power. No matter the solution you choose, you'll get WordPress pre-installed, an AI site builder, free domain name, email, SSL, built-in CDN and more. From blogs, business sites, and online stores, build any kind of website on an easily scalable WordPress-optimized platform backed by legendary 24/7 support by in-house WordPress experts.</p>

						<div class="forum">
							<a href="https://wordpress.org/search/Bluehost/?in=support_forums">Forum threads about Bluehost &raquo;</a>
						</div>
					</div>
                    <div class="partner">
                        <h2>
                            <a href="https://www.hostinger.com/special/wordpress" rel="nofollow">
                                <img
                                    alt=""
                                    src="https://s.w.org/hosting/hostinger.png?<?php echo $hosting_cache_buster; ?>"
                                    height="100"
                                    width="100"
                                />
                                Hostinger
                            </a>
                        </h2>

                        <p>Hostinger, trusted by more than 2.5 million clients worldwide, offers fast and secure managed WordPress hosting. Enjoy seamless WordPress experience with a 1-click installer, free domain and SSL, LiteSpeed and object cache for a faster website, and built-in CDN. Focusing on user security, experience and performance, it's an ideal solution for beginners and experts alike. Get smooth, value-driven hosting, backed by 24/7 support of WordPress experts, with Hostinger.</p>

                        <div class="forum">
                            <a href="https://wordpress.org/search/Hostinger/?in=support_forums">Forum threads about Hostinger &raquo;</a>
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
							<a href="https://wordpress.org/search/DreamHost/?in=support_forums">Forum threads about DreamHost &raquo;</a>
						</div>
					</div>
					
					<div class="partner">
						<h2>
							<a href="https://wordpress.com/wordpress-hosting/" rel="nofollow">
								<img
									alt=""
									src="https://s.w.org/style/images/about/WordPress-logotype-wmark.png?<?php echo $hosting_cache_buster; ?>"
									height="100"
									width="100"
								/>
								WordPress.com
							</a>
						</h2>

						<p>At WordPress.com, we've built an automatically scalable and secure platform optimized for the latest WordPress has to offer.  Start a free website or blog today with everything you need to grow. Get lightning-fast and reliable performance with our global CDN, high-frequency CPUs, multi-datacenter failover, secure login, integrated visitor stats, and more. With managed hosting and the 24/7 support of our dedicated WordPress experts, you'll be well taken care of.</p>

						<div class="forum">
							<a href="https://wordpress.org/search/wordpress.com/?in=support_forums">Forum threads about WordPress.com &raquo;</a>
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
