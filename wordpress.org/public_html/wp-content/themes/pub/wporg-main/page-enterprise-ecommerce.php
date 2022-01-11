<?php
/**
 * Template Name: Enterprise-eCommerce
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
					<img width="300" src="<?php echo get_theme_file_uri('images/enterprise/ecommerce.png'); ?>">
					<h1 class="entry-title">eCommerce</h1>
					<p class="entry-description">Enterprise Ecommerce,<br>Now on WordPress</p>
					<p class="entry-description small">WordPress is ready to support your organization with ecommerce in the enterprise. More online stores use WordPress for ecommerce than any other platform. For the enterprise, WordPress has the integrations, technical approaches, and service ecosystem you need. It&apos;s also an open platform with no lock-in. The data in WordPress is yours with easy porting in and out. WordPress itself has no restrictive contracts or licensing and offers a lower total cost of ownership compared to other enterprise platforms.</p>
					<div style="clear:both"></div>
				</hgroup>

			</header><!-- .entry-header -->



			<div class="entry-content row usecases">
				<section class="col-12">
					<h2 class="header">Why WordPress for eCommerce?</h2>
				</section>
				<div class="col-6">
					<hr class="bluesmall">
					<h3>Proven Scale</h3>
					<p>More than 20% of online stores run on WooCommerce alone, a popular ecommerce plugin and just one of multiple options for ecommerce in WordPress. From single product stores to complex catalogs spanning tens of thousands of digital and physical products, ecommerce on WordPress has been proven to scale across a wide range of applications and markets.</p>
				</div>
				<div class="col-6">
					<hr class="bluesmall">
					<h3>Deep Integrations</h3>
					<p>WordPress is built from the ground up for integrations, has extensive documentation and a strong developer community. With more than 50,000 plugins in the official plugin repository alone, the integration you need is likely available or can be created.</p>
				</div>
				<div class="col-6">
					<hr class="bluesmall">
					<h3>Multiple Approaches</h3>
					<p>You can run your store on WordPress (including headless for custom applications) or combine it with a SaaS provider for added capability. Whether a simple site for transactions or complex multi-lingual, multi-currency catalogs, there is a technical approach for your needs.</p>
				</div>
				<div class="col-6">
					<hr class="bluesmall">
					<h3>Familiar Experience</h3>
					<p>WordPress has been designed, from its beginning, with ease-of-use in mind. With more than a third of the web running on WordPress, most users will either be familiar with the interface, or will pick it up quickly, reducing training costs and improving efficiency.</p>
				</div>
				<div class="col-6">
					<hr class="bluesmall">
					<h3>Diverse Ecosystem</h3>
					<p>From ecommerce-specific plugins, to SaaS providers, to freelance specialists, to ecommerce agencies, WordPress offers access to the largest ecommerce ecosystems. You can learn from what others have done and get the support you need for your specific use case.</p>
				</div>
				<div class="col-6">
					<hr class="bluesmall">
					<h3>Data Portability</h3>
					<p>The data in WordPress is yours. Unlike some other platforms, WordPress is designed with data portability in mind, making it easy to get your data in and out whenever you choose with a built-in API and support for other query technologies like GraphQL.</p>
				</div>
				<div class="col-6">
					<hr class="bluesmall">
					<h3>Open Source</h3>
					<p>Built on four core freedoms and licensed under a General Public License, WordPress is open source at it&apos;s best. That means no restrictive licenses, no sneaky terms of services, and development happens in the open. Unlike some other enterprise platforms, the code is yours.</p>
				</div>
				<div class="col-6">
					<hr class="bluesmall">
					<h3>Lower Costs</h3>
					<p>With no licensing costs for WordPress itself and access to a large ecosystem of integrations and service providers, your total cost of ownership will typically be lower on WordPress than most other enterprise ecommerce platforms.</p>
				</div>

			</div>

			<div class="entry-content row usecases">
				<section class="col-12">
					<h2 class="header">Use Cases</h2>
					<p>In towns and cities, regions and countries, all over the world, WordPress powers the sites people visit multiple times every day for news, information and entertainment.</p>
				</section>
			</div>

			<div class="entry-content row examples">
				<section class="col-4">
					<h4>WooCommerce</h4>
					<p>Hundreds of extensions help to support your existing vendors and foundational systems. It&apos;s straightforward to build powerful, custom experiences without the need to create components from scratch.</p>
					<img src="<?php echo get_theme_file_uri('images/enterprise/swelllogo.png'); ?>">
					<img src="<?php echo get_theme_file_uri('images/enterprise/allblackslogo.png'); ?>">
					<img src="<?php echo get_theme_file_uri('images/enterprise/wwflogo.png'); ?>">
				</section>
				<section class="col-4">
					<h4>BigCommerce</h4>
					<p>A SaaS backend integrated through the BigCommerce plugin brings enterprise-specific functionality and scale along with streamlined integrations. </p>
					<img src="<?php echo get_theme_file_uri('images/enterprise/firewirelogo.png'); ?>">
					<img src="<?php echo get_theme_file_uri('images/enterprise/carluccioslogo.png'); ?>">
					<img src="<?php echo get_theme_file_uri('images/enterprise/kodaklogo.png'); ?>">
				</section>
				<section class="col-4">
					<h4>Other eCommerce Platforms</h4>
					<p>Build seamless integrations with other popular ecommerce solutions like Shopify and Magento for a unified user experience..</p>
					<img src="<?php echo get_theme_file_uri('images/enterprise/methodlogo.png'); ?>">
					<img src="<?php echo get_theme_file_uri('images/enterprise/tyrlogo.png'); ?>">
					<img src="<?php echo get_theme_file_uri('images/enterprise/riflepaperlogo.png'); ?>">
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
						<h4>ERP</h4>
						<hr>
						<div class="logos">
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/netsuitelogo.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/zohologo.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/salesforcelogo.png'); ?>"></div>
						</div>
					</section>
					<section class="col-4 integration">
						<img class="icon" src="<?php echo get_theme_file_uri('images/enterprise/iconvideo.png'); ?>">
						<h4>PIM Systems</h4>
						<hr>
						<div class="logos">
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/solidpepperlogo.png'); ?>"></div>
						</div>
					</section>
					<section class="col-4 integration">
						<img class="icon" src="<?php echo get_theme_file_uri('images/enterprise/iconasset.png'); ?>">
						<h4>Email Marketing</h4>
						<hr>
						<div class="logos">
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/mailchimplogo.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/jiltlogo.png'); ?>"></div>
						</div>
					</section>
				</div>
				<div class="entry-content row integrations-list">
					<section class="col-4 integration">
						<img class="icon" src="<?php echo get_theme_file_uri('images/enterprise/iconanalytics.png'); ?>">
						<h4>Personalization and multichanneling</h4>
						<hr>
						<div class="logos">
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/sailthrulogo.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/stripelogo.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/squarelogo.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/amazonpaylogo.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/authnetlogo.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/paypalpfprologo.png'); ?>"></div>
						</div>
					</section>
					<section class="col-4 integration">
						<img class="icon" src="<?php echo get_theme_file_uri('images/enterprise/iconautomation.png'); ?>">
						<h4>Shipping apps</h4>
						<hr>
						<div class="logos">
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/shipstationlogo.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/upslogo.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/fedexlogo.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/uspslogo.png'); ?>"></div>
						</div>
					</section>
					<section class="col-4 integration">
						<img class="icon" src="<?php echo get_theme_file_uri('images/enterprise/iconseo.png'); ?>">
						<h4>Analytics</h4>
						<hr>
						<div class="logos">
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/googleecomlogo.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/metoriklogo.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/kissmetricslogo.png'); ?>"></div>
						</div>
					</section>
				</div>
				<div class="entry-content row integrations-list">
					<section class="col-4 integration">
						<img class="icon" src="<?php echo get_theme_file_uri('images/enterprise/iconchannels.png'); ?>">
						<h4>Accounting Systems (Bookkeeping)</h4>
						<hr>
						<div class="logos">
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/quickbookslogo.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/xerologo.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/freshbookslogo.png'); ?>"></div>
						</div>
					</section>
					<section class="col-4 integration">
						<img class="icon" src="<?php echo get_theme_file_uri('images/enterprise/iconsocial.png'); ?>">
						<h4>POS Integrations</h4>
						<hr>
						<div class="logos">
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/squarelogo.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/lightspeedlogo.png'); ?>"></div>
						</div>
					</section>
					<section class="col-4 integration">
						<img class="icon" src="<?php echo get_theme_file_uri('images/enterprise/iconworkflow.png'); ?>">
						<h4>Multilingual Store</h4>
						<hr>
						<div class="logos">
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/wpmllogo.png'); ?>"></div>
						</div>
					</section>
				</div>
			</div>

			<div class="entry-content row moreinfo">
				<section class="col-12">
					<h2 class="header">For More Information</h2>
				</section>
				<div class="entry-content row">
					<section class="col-3">
						<h3>Security</h3>
						<div><img src="<?php echo get_theme_file_uri('images/enterprise/more4.png'); ?>"></div>
					</section>
					<section class="col-3">
						<h3>GDPR</h3>
						<div><img src="<?php echo get_theme_file_uri('images/enterprise/more5.png'); ?>"></div>
					</section>
					<section class="col-3">
						<h3>Multichannel Marketing</h3>
						<div><img src="<?php echo get_theme_file_uri('images/enterprise/more6.png'); ?>"></div>
					</section>
					<section class="col-3">
						<h3>Working with the REST API</h3>
						<div><img src="<?php echo get_theme_file_uri('images/enterprise/more7.png'); ?>"></div>
					</section>
				</div>
			</div>

		</article><!-- #post-## -->

	</main><!-- #main -->

<?php
get_footer();

