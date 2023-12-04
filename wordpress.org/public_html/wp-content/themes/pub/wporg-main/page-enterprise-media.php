<?php
/**
 * Template Name: Enterprise-Media
 *
 * Page template for displaying the Enterprise Media page.
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
					<img width="300" src="<?php echo get_theme_file_uri('images/enterprise/mediapublishing.png'); ?>">
					<h1 class="entry-title">Media and Publishing</h1>

					<p class="entry-description">Global Media Delivers on WordPress</p>
					<p class="entry-description small">From the beginning, WordPress has strived to democratize publishing as a free, intuitive publishing platform for millions of individual voices to reach their audiences. It has subsequently evolved into a content management system that allows the biggest sites to reach billions of readers. It also allows publishers to free themselves from the confines of expensive, proprietary systems that frustrate their editorial teams and stifle innovation.</p>
					<p class="entry-description small">Today, WordPress is the content management system of choice for small, medium, and large publishers globally, and is used to produce content across screens and endpoints within a unified, digital-first workflow. Just as important, the software is still free. Democratizing publishing has never been more powerful.</p>
					<div style="clear:both"></div>
				</hgroup>

			</header><!-- .entry-header -->

			<div class="entry-content row usecases">
				<section class="col-12">
					<h2 class="header">Use Cases</h2>
					<p>In towns and cities, regions and countries, all over the world, WordPress powers the sites people visit multiple times every day for news, information and entertainment.</p>
					<hr class="bluesmall">
					<h3 class="header">Newspapers & Magazines</h3>
					<p>WordPress is helping to transform the editorial workflow for traditional media outlets. A well-supported library of integrations and its robust REST API lets publishers use WordPress produce content once and publish it to their website, mobile platforms, social media, and even print in a unified, digital-first workflow.</p>
				</section>
			</div>

			<div class="entry-content row media-logos">
				<section class="col-3">
					<img src="<?php echo get_theme_file_uri('images/enterprise/nytlogo.png'); ?>">
				</section>
				<section class="col-3">
					<img src="<?php echo get_theme_file_uri('images/enterprise/peoplelogo.png'); ?>">
				</section>
				<section class="col-3">
					<img src="<?php echo get_theme_file_uri('images/enterprise/uslogo.png'); ?>">
				</section>
				<section class="col-3">
					<img src="<?php echo get_theme_file_uri('images/enterprise/wsjlogo.png'); ?>">
				</section>
				<section class="col-3">
					<img src="<?php echo get_theme_file_uri('images/enterprise/mnglogo.png'); ?>">
				</section>
				<section class="col-3">
					<img src="<?php echo get_theme_file_uri('images/enterprise/timelogo.png'); ?>">
				</section>
				<section class="col-3">
					<img src="<?php echo get_theme_file_uri('images/enterprise/entertainmentlogo.png'); ?>">
				</section>
				<section class="col-3">
					<img src="<?php echo get_theme_file_uri('images/enterprise/denverpostlogo.png'); ?>">
				</section>
				<section class="col-3">
					<img src="<?php echo get_theme_file_uri('images/enterprise/sjmnewslogo.png'); ?>">
				</section>
				<section class="col-3">
					<img src="<?php echo get_theme_file_uri('images/enterprise/fortunelogo.png'); ?>">
				</section>
				<section class="col-3">
					<img src="<?php echo get_theme_file_uri('images/enterprise/varietylogo.png'); ?>">
				</section>
				<section class="col-3">
					<img src="<?php echo get_theme_file_uri('images/enterprise/portlandpresslogo.png'); ?>">
				</section>
				<section class="col-3">
					<img src="<?php echo get_theme_file_uri('images/enterprise/seattletimeslogo.png'); ?>">
				</section>
				<section class="col-3">
					<img src="<?php echo get_theme_file_uri('images/enterprise/wwdlogo.png'); ?>">
				</section>
				<section class="col-3">
					<img src="<?php echo get_theme_file_uri('images/enterprise/rollingstonelogo.png'); ?>">
				</section>
				<section class="col-3">
					<img src="<?php echo get_theme_file_uri('images/enterprise/thesunlogo.png'); ?>">
				</section>
				<section class="col-3">
					<img src="<?php echo get_theme_file_uri('images/enterprise/usatodaylogo.png'); ?>">
				</section>
				<section class="col-3">
					<img src="<?php echo get_theme_file_uri('images/enterprise/motortrendlogo.png'); ?>">
				</section>
				<section class="col-3">
					<img src="<?php echo get_theme_file_uri('images/enterprise/readersdigestlogo.png'); ?>">
				</section>
				<section class="col-3">
				</section>
				<hr class="bluesmall">
			</div>

			<div class="entry-content row publishers">
				<section class="col-12">
					<h3>Digital Only Publishers</h3>
					<p>WordPress is an easy choice for many digital-only publishers. The extensibility of the platform allows it to keep pace with your site whether its a simple blog or a multifaceted digital publication.</p>
				</section>
			</div>

			<div class="entry-content row media-logos">
				<section class="col-2">
					<img src="<?php echo get_theme_file_uri('images/enterprise/digidaylogo.png'); ?>">
				</section>
				<section class="col-2">
					<img src="<?php echo get_theme_file_uri('images/enterprise/fivethirtyeightlogo.png'); ?>">
				</section>
				<section class="col-2">
					<img src="<?php echo get_theme_file_uri('images/enterprise/onedigitallogo.png'); ?>">
				</section>
				<section class="col-2">
					<img src="<?php echo get_theme_file_uri('images/enterprise/venturebeatlogo.png'); ?>">
				</section>
				<section class="col-2">
					<img src="<?php echo get_theme_file_uri('images/enterprise/politicologo.png'); ?>">
				</section
				<section class="col-2">
					<img src="<?php echo get_theme_file_uri('images/enterprise/uproxxlogo.png'); ?>">
				</section>
				<section class="col-2">
					<img src="<?php echo get_theme_file_uri('images/enterprise/thewraplogo.png'); ?>">
				</section>
				<section class="col-2">
					<img src="<?php echo get_theme_file_uri('images/enterprise/wirecutterlogo.png'); ?>">
				</section>
				<section class="col-2">
					<img src="<?php echo get_theme_file_uri('images/enterprise/foxsportslogo.png'); ?>">
				</section>
				<section class="col-2">
					<img src="<?php echo get_theme_file_uri('images/enterprise/digitaltrendslogo.png'); ?>">
				</section>
			</div>

			<div class="entry-content row examples">
				<section class="col-4">
					<h4>Broadcasters</h4>
					<p>WordPress integrates with streaming video-on-demand applications (SVOD) via providers like Anvato, over-the-top (OTT) media services, and integrated audio and video players that support virtually any platform or device.</p>
					<img src="<?php echo get_theme_file_uri('images/enterprise/wamulogo.png'); ?>">
					<img src="<?php echo get_theme_file_uri('images/enterprise/nexstarlogo.png'); ?>">
					<img src="<?php echo get_theme_file_uri('images/enterprise/beasleylogo.png'); ?>">
				</section>
				<section class="col-4">
					<h4>Book Publishers</h4>
					<p>A flexible information architecture, supported by fully customizable post types and taxonomies, helps book publishers manage complex data models for their titles and integrate with services like Onix and Bowker.</p>
					<img src="<?php echo get_theme_file_uri('images/enterprise/macmillanlogo.png'); ?>">
					<img src="<?php echo get_theme_file_uri('images/enterprise/hachettelogo.png'); ?>">
					<img src="<?php echo get_theme_file_uri('images/enterprise/harpercollinslogo.png'); ?>">
				</section>
				<section class="col-4">
					<h4>Nonprofit</h4>
					<p>Nonprofit organizations have complex publishing needs that rival any publisher in news media. WordPress helps them efficiently manage their sites with a small staff and powerful integrations with search platforms let them expose their wealth of evergreen content to the community.</p>
					<img src="<?php echo get_theme_file_uri('images/enterprise/brookingslogo.png'); ?>">
					<img src="<?php echo get_theme_file_uri('images/enterprise/clevelandcliniclogo.png'); ?>">
					<img src="<?php echo get_theme_file_uri('images/enterprise/aarplogo.png'); ?>">
				</section>
			</div>

			<div class="entry-content row integrations">
				<section class="col-12 top">
					<h3>Integrations</h3>
					<p>WordPress gives you the freedom to choose from the best available tools and providers to compliment your digital platform. The robust network of sites using WordPress means that most third-party platforms already have well-supported integrations that let you readily integrate with them. The enterprise community regularly contributes to growing and updating these integrations and ensuring they meet the highest standards for performance, security, and scale.</p>
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
						<h4>Marketing Automation & Personalization</h4>
						<hr>
						<div class="logos">
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/sailthrulogo.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/parselylogo.png'); ?>"></div>
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
						<img class="icon" src="<?php echo get_theme_file_uri('images/enterprise/iconworkflow.png'); ?>">
						<h4>Editorial Workflow</h4>
						<hr>
						<div class="logos">
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/editflowlogo.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/coschedulelogo.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/kapostlogo.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/distributorlogo.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/googledocslogo.png'); ?>"></div>
						</div>
					</section>
				</div>
				<div class="entry-content row integrations-list">
					<section class="col-4 integration">
						<img class="icon" src="<?php echo get_theme_file_uri('images/enterprise/iconadvertising.png'); ?>">
						<h4>Advertising</h4>
						<hr>
						<div class="logos">
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/adstxtlogo.png'); ?>"></div>
						</div>
					</section>
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
					<section class="col-4 integration blue">
						<h4>Know more for<br>integrations &rarr;</h4>
						</section>
				</div>
			</div>

			<div class="entry-content row moreinfo">
				<section class="col-12">
					<h2 class="header">For More Information</h2>
				</section>
				<div class="entry-content row">
					<section class="col-3">
						<h3>Paywall and Monetization Whitepaper</h3>
						<div><img src="<?php echo get_theme_file_uri('images/enterprise/more1.png'); ?>"></div>
					</section>
					<section class="col-3">
						<h3>Editorial Newsroom Workflow Guide</h3>
						<div><img src="<?php echo get_theme_file_uri('images/enterprise/more2.png'); ?>"></div>
					</section>
					<section class="col-3">
						<h3>Decoupled WordPress Pros and Cons</h3>
						<div><img src="<?php echo get_theme_file_uri('images/enterprise/more3.png'); ?>"></div>
					</section>
					<section class="col-3">
						<h3>Security / Open Source</h3>
						<div><img src="<?php echo get_theme_file_uri('images/enterprise/more4.png'); ?>"></div>
					</section>
				</div>
			</div>

		</article><!-- #post-## -->

	</main><!-- #main -->

<?php
get_footer();

