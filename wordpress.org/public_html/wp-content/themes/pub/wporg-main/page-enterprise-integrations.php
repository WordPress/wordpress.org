<?php
/**
 * Template Name: Enterprise-Integrations
 *
 * Page template for displaying the Enterprise page.
 *
 * @package WordPressdotorg\MainTheme
 */

namespace WordPressdotorg\MainTheme;

// Prevent Jetpack from looking for a non-existent featured image.
add_filter( 'jetpack_images_pre_get_images', function() {
	return new \WP_Error();
} );

// rm the page-child class from this page
add_filter( 'body_class', function ( $classes ) {
    return array_diff( $classes, array( 'page-child' ) );
} );

// Noindex until ready.
add_filter( 'wporg_noindex_request', '__return_true' );

/* See inc/page-meta-descriptions.php for the meta description for this page. */

get_header();
the_post();
?>

	<main id="main" class="site-main col-12" role="main">

		<article id="post-1">

			<header class="entry-header">
				<hgroup class="header-group">
					<img width="300" src="<?php echo get_theme_file_uri('images/enterprise/integrations.png'); ?>">
					<h1 class="entry-title">Integrations</h1>
					<p class="entry-description">The most popular CMS; the most integrations</p>
					<p class="entry-description small">WordPress is more than a &ldquo;website builder&rdquo; &ndash; it&apos;s a content platform ready to be configured for each enterprise&apos;s use case, integrations, and workflow. A streamlined and hardened core platform is designed with compatibility, modularity, and integrations in mind. Dominant market share and a developer-friendly architecture have spawned an unrivaled ecosystem of apps, integrations, and plugins that can be installed, managed, and updated with just a few secure, authorized clicks.more powerful.</p>
					<div style="clear:both"></div>
				</hgroup>

			</header><!-- .entry-header -->

			<div class="entry-content row supportedby">
				<section class="col-12">
					<h2 class="header">Supported by the biggest enterprises</h2>
					<p>First party integrations and official WordPress solutions from enterprises like:</p>
				</section>
				<div class="col-2 image"><img src="<?php echo get_theme_file_uri('images/enterprise/salesforce.png'); ?>"></div>
				<div class="col-2 image"><img src="<?php echo get_theme_file_uri('images/enterprise/google.png'); ?>"></div>
				<div class="col-2 image"><img src="<?php echo get_theme_file_uri('images/enterprise/facebooklogo3.png'); ?>"></div>
				<div class="col-2 image"><img src="<?php echo get_theme_file_uri('images/enterprise/twitter.png'); ?>"></div>
				<div class="col-2 image"><img src="<?php echo get_theme_file_uri('images/enterprise/microsoft.png'); ?>"></div>
			</div>

			<div class="entry-content row integrations">
				<section class="col-12 top">
					<h3>Featured integrations</h3>
				</section>
				<div class="entry-content row integrations-list">
					<section class="col-4 integration">
						<img class="icon" src="<?php echo get_theme_file_uri('images/enterprise/iconpaywall.png'); ?>">
						<h4>Paywall & Monetization</h4>
						<hr>
						<div class="logos">
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/pianologo.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/laterpaylogo.png'); ?>"></div>
						</div>
					</section>
					<section class="col-4 integration">
						<img class="icon" src="<?php echo get_theme_file_uri('images/enterprise/iconvideo.png'); ?>">
						<h4>Video</h4>
						<hr>
						<div class="logos">
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/brightcovelogo.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/ooyalalogo.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/anvatologo.png'); ?>"></div>
						</div>
					</section>
					<section class="col-4 integration">
						<img class="icon" src="<?php echo get_theme_file_uri('images/enterprise/iconasset.png'); ?>">
						<h4>Digital Asset Management</h4>
						<hr>
						<div class="logos">
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/gettyimageslogo.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/webdamlogo.png'); ?>"></div>
						</div>
					</section>
				</div>
				<div class="entry-content row integrations-list">
					<section class="col-4 integration">
						<img class="icon" src="<?php echo get_theme_file_uri('images/enterprise/iconanalytics.png'); ?>">
						<h4>Analytics</h4>
						<hr>
						<div class="logos">
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/parselylogo.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/chartbeatlogo.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/googleanalyticslogo.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/adobeanalyticslogo.png'); ?>"></div>
						</div>
					</section>
					<section class="col-4 integration">
						<img class="icon" src="<?php echo get_theme_file_uri('images/enterprise/iconautomation.png'); ?>">
						<h4>Marketing</h4>
						<hr>
						<div class="logos">
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/eloqualogo.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/marketologo.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/sailthrulogo.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/hubspotlogo.png'); ?>"></div>
						</div>
					</section>
					<section class="col-4 integration">
						<img class="icon" src="<?php echo get_theme_file_uri('images/enterprise/iconseo.png'); ?>">
						<h4>SEO & Audience</h4>
						<hr>
						<div class="logos">
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/yoastlogo.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/optimizelylogo.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/msmsitemaplogo.png'); ?>"></div>
						</div>
					</section>
				</div>
				<div class="entry-content row integrations-list">
					<section class="col-4 integration">
						<img class="icon" src="<?php echo get_theme_file_uri('images/enterprise/iconchannels.png'); ?>">
						<h4>Platform Channels</h4>
						<hr>
						<div class="logos">
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/amplogo.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/facebook2logo.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/ioslogo.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/flipboardlogo.png'); ?>"></div>
						</div>
					</section>
					<section class="col-4 integration">
						<img class="icon" src="<?php echo get_theme_file_uri('images/enterprise/iconsocial.png'); ?>">
						<h4>Social</h4>
						<hr>
						<div class="logos">
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/disquslogo.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/forumslogo.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/socialcommunitylogo.png'); ?>"></div>
						</div>
					</section>
					<section class="col-4 integration">
						<img class="icon" src="<?php echo get_theme_file_uri('images/enterprise/iconsocial.png'); ?>">
						<h4>eCommerce</h4>
						<hr>
						<div class="logos">
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/woocommercelogo.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/stripelogo.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/paypallogo.png'); ?>"></div>
						</div>
					</section>
				</div>
				<div class="entry-content row integrations-list">
					<section class="col-4 integration">
						<img class="icon" src="<?php echo get_theme_file_uri('images/enterprise/iconadvertising.png'); ?>">
						<h4>Content</h4>
						<hr>
						<div class="logos">
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/distributorcontentreuse.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/googledocslogo.png'); ?>"></div>
						</div>
					</section>
					<section class="col-4 integration">
						<img class="icon" src="<?php echo get_theme_file_uri('images/enterprise/iconadvertising.png'); ?>">
						<h4>Advertising</h4>
						<hr>
						<div class="logos">
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/adstxtlogo.png'); ?>"></div>
						</div>
					</section>
					<section class="col-4 integration">
						<img class="icon" src="<?php echo get_theme_file_uri('images/enterprise/iconadvertising.png'); ?>">
						<h4>Security & Sign on</h4>
						<hr>
						<div class="logos">
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/activedirectorylogo.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/oneloginlogo.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/shibbolethlogo.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/janrainlogo.png'); ?>"></div>
						</div>
					</section>
				</div>

				<div class="entry-content row integrations-list">
					<section class="col-4 integration">
						<img class="icon" src="<?php echo get_theme_file_uri('images/enterprise/iconsearch.png'); ?>">
						<h4>Search & Indexing</h4>
						<hr>
						<div class="logos">
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/elasticsearchlogo.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/solrlogo.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/marklogiclogo.png'); ?>"></div>
						</div>
					</section>
					<section class="col-4 integration cta blue">
						<h4>Know more for<br>integrations &rarr;</h4>
					</section>
					<section class="col-4 integration cta lightblue">
						<h4>Some another CTA for balance grid &rarr;</h4>
					</section>
				</div>
			</div>

			<div class="entry-content row manage-integrations col-9">
				<h3 class="entry-title col-9">Manage integrations your way</h3>
				<section class="entry-content col-9 left">
					<img width="200" src="<?php echo get_theme_file_uri('images/enterprise/dashboard.png'); ?>">
					<p class="title">From the dashboard</p>
					<p class="description">Authorized Administrators can review, install, update, and manage integrations across a single site or collection of websites from WordPress&apos;s intuitive administrative interface.</p>
					<div style="clear:both"></div>
				</section>
				<section class="entry-content col-9 right">
					<img width="200" src="<?php echo get_theme_file_uri('images/enterprise/commandline.png'); ?>">
					<p class="title">From the command line</p>
					<p class="description">Enterprise IT managers who prefer the security and interface of a terminal, can securely connect to their server and manage updates using the WordPress command line interface.</p>
					<div style="clear:both"></div>
				</section>
				<section class="entry-content col-9 left">
					<img width="200" src="<?php echo get_theme_file_uri('images/enterprise/console.png'); ?>">
					<p class="title">From a management console</p>
					<p class="description">Third party software management solutions from companies like Automattic and GoDaddy offer consoles to manage and view the status of integrations across multiple WordPress sites, including update deployment.</p>
					<div style="clear:both"></div>
				</section>
				<section class="entry-content col-9 right">
					<img width="200" src="<?php echo get_theme_file_uri('images/enterprise/versioncontrol.png'); ?>">
					<p class="title">From version control</p>
					<p class="description">WordPress can be set to lock out installation and update management from the dashboard, enabling third-party workflows and deployment management rules that prefer upgrades to be managed by engineering and version control systems.</p>
					<div style="clear:both"></div>
				</section>

			</div>

			<div class="entry-content row make-your-own">
				<h3 class="entry-title">Make your own</h3>
				<section class="entry-content col-12">
					<p class="entry-description">Enterprises with custom applications and solutions can make their own integrations leveraging WordPress&apos;s fully extensible open source architecture, including native PHP modules, a robust RESTful API, and command line interfaces.</p>

					<a class="button button-primary button-xl" href="">
					Learn more about making your own integrations</a>
				</section>
			</div>

		</article><!-- #post-## -->

	</main><!-- #main -->

<?php
get_footer();

