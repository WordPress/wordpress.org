<?php
/**
 * Template Name: Enterprise-Content Marketing
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
					<img width="300" src="<?php echo get_theme_file_uri('images/enterprise/contentmarketing.png'); ?>">
					<h1 class="entry-title">WordPress for Brands & Content Marketers</h1>
					<p class="entry-description small">The mission statement of WordPress is to "democratize publishing". The result is the most popular content marketing platform on the Internet.  It is easy to use for content authors, editors, and publishers: simple, but powerful, with a core set of APIs for integration into any content ingestion, syndication, editing, and distribution workflow. </p>
					<p class="entry-description small">This has made WordPress a smart choice for brands using content to tell their story, shape their brand image, and attract customers. If you want your story (your offering, your services, your mission) to be part of the fabric of the conversation on the web, WordPress is the ideal platform. </p>
					<div style="clear:both"></div>
				</hgroup>
			</header><!-- .entry-header -->

			<div class="entry-content row usecases">
				<h2>Examples</h2>
				<hr class="bluesmall">
				<h3 class="col-12">Global CPG Brands</h3>
				<div class="entry-content row enterprise-logos">
					<section class="col-2"><img src="<?php echo get_theme_file_uri('images/enterprise/cocacola.png'); ?>"></section>
					<section class="col-2"><img src="<?php echo get_theme_file_uri('images/enterprise/campbells.png'); ?>"></section>
					<section class="col-2"><img src="<?php echo get_theme_file_uri('images/enterprise/dole.png'); ?>"></section>
					<section class="col-2"><img src="<?php echo get_theme_file_uri('images/enterprise/mcdonalds.png'); ?>"></section>
					<section class="col-2"><img src="<?php echo get_theme_file_uri('images/enterprise/mercedesbenz.png'); ?>"></section>
					<section class="col-2"><img src="<?php echo get_theme_file_uri('images/enterprise/microsoft.png'); ?>"></section>
					<section class="col-2"><img src="<?php echo get_theme_file_uri('images/enterprise/playstation.png'); ?>"></section>
					<section class="col-2"><img src="<?php echo get_theme_file_uri('images/enterprise/sonymusic.png'); ?>"></section>
					<section class="col-2"><img src="<?php echo get_theme_file_uri('images/enterprise/target.png'); ?>"></section>
					<section class="col-2"><img src="<?php echo get_theme_file_uri('images/enterprise/uber.png'); ?>"></section>
					<section class="col-2"><img src="<?php echo get_theme_file_uri('images/enterprise/ups.png'); ?>"></section>
					<section class="col-2"><img src="<?php echo get_theme_file_uri('images/enterprise/vanheusen.png'); ?>"></section>
					<section class="col-2"></section>
					<section class="col-2"><img src="<?php echo get_theme_file_uri('images/enterprise/verizon.png'); ?>"></section>
					<section class="col-2"><img src="<?php echo get_theme_file_uri('images/enterprise/bacardi.png'); ?>"></section>
					<section class="col-2"><img src="<?php echo get_theme_file_uri('images/enterprise/waltdisney.png'); ?>"></section>
					<section class="col-2"></section>
				</div>
				<hr class="bluesmall">
					<h3 class="col-12">Non-Profits</h3>
					<div class="entry-content row enterprise-logos">
						<section class="col-2"><img src="<?php echo get_theme_file_uri('images/enterprise/aarp.png'); ?>"></section>
						<section class="col-2"><img src="<?php echo get_theme_file_uri('images/enterprise/charitywater.png'); ?>"></section>
						<section class="col-2"><img src="<?php echo get_theme_file_uri('images/enterprise/globalvoices.png'); ?>"></section>
						<section class="col-2"><img src="<?php echo get_theme_file_uri('images/enterprise/janegoodall.png'); ?>"></section>
						<section class="col-2"><img src="<?php echo get_theme_file_uri('images/enterprise/jdrf.png'); ?>"></section>
						<section class="col-2"><img src="<?php echo get_theme_file_uri('images/enterprise/red.png'); ?>"></section>
						<section class="col-2"><img src="<?php echo get_theme_file_uri('images/enterprise/unicef.png'); ?>"></section>
						<section class="col-2"><img src="<?php echo get_theme_file_uri('images/enterprise/goldfish.png'); ?>"></section>
						<section class="col-2"><img src="<?php echo get_theme_file_uri('images/enterprise/pepperidgefarm.png'); ?>"></section>
				</div>
			</div>

			<div class="entry-content row whyuse">
				<h2>Why Use WordPress?</h2>
				<p>Content marketers frequently leverage WordPress for microsites associated with specific campaigns, products, brands or events, spinning up sites quickly based on off-the-shelf plugins and themes (or theme frameworks). Many also use WordPress for their core brand platform, to provide a consistent hub and return destination for content syndicated throughout the web, on social platforms. Whatever their need, WordPress allows content marketers to get their story in front of all their potential customers quickly and easily.</p>
				<hr class="bluesmall">
				<h3 class="col-12">Responsive Design</h3>
				<p>Consumers aren't just using mobile devices as a supplementary screen, but as the primary or dominant method of accessing the web. WordPress not only supports responsive design through thousands of available themes (as well as custom themes developed using a robust templating API) but also throughout the administrative/content authoring experience.</p>
				<p>WordPress even supports built-in content preview on multiple device types through the customizer, letting content authors and editors preview their content across multiple devices.</p>
				<hr class="bluesmall">
				<h3 class="col-12">Multilingual</h3>
				<p>WordPress isn't just a software platform, but a global community of users, developers, and designers. The core platform has long supported multilingual content, including double-byte languages and right-to-left languages. Localization and internationalization are not "bolted on" as an afterthought but deeply integrated into the core APIs. Even the authoring and administrative interface is available in over 50 languages, so content authors can work in the language for which they are writing.</p>
				<p>The flexibility of WordPress means you can have multilingual sites, in which all content is available in multiple languages (including integrations with all the major automated and human powered translation partners), or individual sites in different languages, depending on your need.</p>
				<hr class="bluesmall">
				<h3 class="col-12">Search Engine Optimization</h3>
				<p>Search engines love WordPress, and (properly configured) WordPress sites do very well in terms of search engine optimization. Through popular free and commercially supported plugins, WordPress can produce XML sitemaps, robust meta tags, schema.org markup, and other technical SEO features, but also enables your authors to focus on producing great content and distributing that content, which is the real ultimate key to SEO.</p>
				<hr class="bluesmall">
				<h3 class="col-12">Content Distribution and Syndication</h3>
				<p>WordPress isn't just a great platform for authoring content, it's also a fantastic platform for distributing that content to others. In the original blogging era, that primarily meant RSS, which of course WordPress still supports. More recently, though, that's meant support for oEmbed (as both a provider and a consumer), Facebook Instant Articles, Google Accelerated Mobile Pages (AMP), Apple News, and other external formats.</p>
				<p>The significant market share of WordPress means that any time anyone develops a new platform one which people consume content, one of their first integrations will be to WordPress.</p>
				<hr class="bluesmall">
				<h3 class="col-12">REST API</h3>
				<p>For those rare cases where a platform to which you're hoping to syndicate content doesn't provide a WordPress integration, and for which RSS will not provide a deep enough integration, WordPress provides a full REST API in core, which can be configured to interact with external applications as both a consumer and a creator of structured content.</p>
				<p>The REST API can also be used to push content into WordPress. Marketing departments who already use another system for the content pipeline often "publish" that content into WordPress sites via this API, so that they can take advantage of WordPress's lower cost of implementation and deployment while retaining their advanced content workflows.</p>
				<hr class="bluesmall">
				<h3 class="col-12">Marketing Automation</h3>
				<p>Getting users exposed to your content (on site or in off-site social), of course, is only half the battle for most content marketers. Getting them to take action - to subscribe, to register, to share, to like, to favorite, to buy - is the real goal.  WordPress not only integrates easily with all major social platforms, but also with marketing automation systems like Eloqua, Hubspot, Marketo, Pardot, and Salesforce.</p>
				<hr class="bluesmall">
				<h3 class="col-12">Personalization</h3>
				<p>Ten years ago, websites showed the same content to every visitor, every time. Nowadays, customers expect a more customized experience. Whether tuning messaging based on demographic information like location, time-of-day (local to where the visitor is), or company, or more sophisticated tuning based on previous activity by the visitor or showing different experiences for customers versus prospects, modern marketers are increasingly asked to create unique experiences for each visitor.</p>
				<p>WordPress contains internal tools that make it easier to change content, layout, or entire areas of websites based on dynamic factors. WordPress also integrates with the panoply of personalization tool vendors that allow marketers to target based on segments or behaviors that make sense for their products and customers.</p>
				<p>Another fact of Personalization is that every site is different. There's no such thing as installing software and suddenly a website is "personalized." While the tools inside and outside of WordPress provide raw material, custom development is always required. Therefore, the fact that WordPress is especially agile and cost-effective for custom development also makes it ideal for creating personalized online experiences.</p>
				<hr class="bluesmall">
				<h3 class="col-12">Headless / Content Hub</h3>
				<p>Publishing content externally is no longer the only location where marketing content is useful. In modern, increasingly digital companies, marketing content is useful in other contexts, such as inside web or mobile applications, inside back-office systems, and inside newsletters and other forms of communication.</p>
				<p>WordPress is an ideal Content Hub: A central system for creating, editing, approving, publishing, and distributing these forms of internal content. The REST API and RSS feeds makes it possible to integrate finished content in any other system, from complex applications written in any language, to 3rd-party systems like dashboards and wikis.</p>
			</div>

			<div class="entry-content row integrations">
				<section class="col-12 top">
					<h3 class="header">For More Information</h3>
				</section>
				<div class="entry-content row integrations-list">
					<section class="col-4 integration">
						<div class="logos">
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/smashingmag.png'); ?>"></div>
						</div>
					</section>
					<section class="col-4 integration">
						<div class="logos">
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/contentmarketinginstitute.png'); ?>"></div>
						</div>
					</section>
					<section class="col-4 integration">
						<div class="logos">
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/codeinwp.png'); ?>"></div>
						</div>
					</section>
				</div>
			</div>

		</article><!-- #post-## -->

	</main><!-- #main -->

<?php
get_footer();

