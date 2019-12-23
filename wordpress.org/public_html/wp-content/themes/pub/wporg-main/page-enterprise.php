<?php
/**
 * Template Name: Enterprise
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

/* See inc/page-meta-descriptions.php for the meta description for this page. */

get_header();
the_post();
?>

	<main id="main" class="site-main col-12" role="main">

		<article id="post-1">
			<header class="entry-header">
				<hgroup class="header-group">
					<img width="300" src="<?php echo get_theme_file_uri('images/enterprise/hero.png'); ?>">
					<h1 class="entry-title">WordPress for Enterprise</h1>

					<p class="entry-description">From its humble beginnings as a blogging platform, the WordPress content management system has grown to become a dominant market leader. Discover how today's biggest brands use WordPress, the features that make it so appealing and how you can make WordPress work for your enterprise.</p>
					<div style="clear:both"></div>
				</hgroup>

			</header><!-- .entry-header -->

			<div class="entry-content row enterprises">
				<section class="col-12">
					<h2 class="header">Household brands using WordPress at scale</h2>
				</section>
			<div class="entry-content row enterprise-logos">
				<section class="col-2">
					<img src="<?php echo get_theme_file_uri('images/enterprise/newyorkpostlogo.png'); ?>">
				</section>
				<section class="col-2">
					<img src="<?php echo get_theme_file_uri('images/enterprise/thesunlogo.png'); ?>">
				</section>
				<section class="col-2">
					<img src="<?php echo get_theme_file_uri('images/enterprise/peoplelogo.png'); ?>">
				</section>
				<section class="col-2">
					<img src="<?php echo get_theme_file_uri('images/enterprise/readersdigestlogo.png'); ?>">
				</section>
				<section class="col-2">
					<img src="<?php echo get_theme_file_uri('images/enterprise/wwdlogo.png'); ?>">
				</section>
				<section class="col-2">
					<img src="<?php echo get_theme_file_uri('images/enterprise/varietylogo.png'); ?>">
				</section>
				<section class="col-2">
					<img src="<?php echo get_theme_file_uri('images/enterprise/spotifylogo.png'); ?>">
				</section>
				<section class="col-2">
					<img src="<?php echo get_theme_file_uri('images/enterprise/tedlogo.png'); ?>">
				</section>
				<section class="col-2">
					<img src="<?php echo get_theme_file_uri('images/enterprise/facebooklogo.png'); ?>">
				</section>
				<section class="col-2">
					<img src="<?php echo get_theme_file_uri('images/enterprise/jetlogo.png'); ?>">
				</section>
				<section class="col-2">
					<img src="<?php echo get_theme_file_uri('images/enterprise/microsoftlogo.png'); ?>">
				</section>
				<section class="col-2">
					<img src="<?php echo get_theme_file_uri('images/enterprise/cnnlogo.png'); ?>">
				</section>
			</div>
			</div>

			<div class="entry-content row applications">
				<section class="col-12">
					<h3>Enterprise use cases</h3>
					<p>
					WordPress' core competency is content management. Whether used as a primary or secondary CMS, you'll find WordPress being used by enterprise at scale wherever there's a requirement for flexible, cost-effective and secure creation and distribution of content. Here's a deeper dive into some of WordPress' most popular use cases for enterprises.
					</p>
				</section>

				<div class="entry-content row applications-list">
					<section class="col-3">
						<h4>Media & Publishing</h4>
						<div class="box mediapublishing"><img src="<?php echo get_theme_file_uri('images/enterprise/mediapublishing.png'); ?>"></div>
						<p>WordPress powers the sites people visit multiple times every day for news, information and entertainment.</p>
					</section>
					<section class="col-3">
						<h4>eCommerce</h4>
						<div class="box ecommerce"><img src="<?php echo get_theme_file_uri('images/enterprise/ecommerce.png'); ?>"></div>
						<p>Organizations around the world choose WordPress as the ecommerce solution of choice for their needs.</p>
					</section>
					<section class="col-3">
						<h4>Content Marketing</h4>
						<div class="box contentmarketing"><img src="<?php echo get_theme_file_uri('images/enterprise/contentmarketing.png'); ?>"></div>
						<p>WordPress will help you get your brand&apos;s story in front of your potential customers quickly and easily.</p>
					</section>
					<section class="col-3">
						<h4>Higher education</h4>
						<div class="box education"><img src="<?php echo get_theme_file_uri('images/enterprise/education.png'); ?>"></div>
						<p>Educational organisations use WordPress to power everything from a departmental blog through to an entire university system.</p>
					</section>
				</div>
			</div>

			<div class="entry-content row tagline">
				<section class="col-5">
					<h3>WordPress is used by enterprise across media, finance, goverment, etc...</h3>
				</section>
			</div>

			<div class="entry-content row features">
				<section class="col-12">
					<h3>Key features</h3>
				</section>
				<div class="entry-content row features-list">
					<section class="col-4">
						<div class="box"><img src="<?php echo get_theme_file_uri('images/enterprise/extensibility.png'); ?>"></div>
						<h4>Extensibility</h4>
						<p>Enterprise-level extensions for WordPress span a broad range of functionality, from enhanced search, to advanced on-page SEO, to AI-driven content augmentation, to developer power tools.</p>
					</section>
					<section class="col-4">
						<div class="box"><img src="<?php echo get_theme_file_uri('images/enterprise/security.png'); ?>"></div>
						<h4>Security</h4>
						<p>WordPress&apos; strict security standards make it a popular CMS for enterprise companies around the world, and whilst its popularity can make it a target, it has proven itself as a secure platform.</p>
					</section>
					<section class="col-4">
						<div class="box"><img src="<?php echo get_theme_file_uri('images/enterprise/opensource.png'); ?>"></div>
						<h4>Open Source</h4>
						<p>WordPress is open source software. This means the source code is made freely available and may be distributed and modified subject to the GPL license.</p>
					</section>
				</div>
			</div>

			<div class="entry-content row resources">
				<section class="col-12">
					<h3>Resource & Learning</h3>
				</section>
				<div class="entry-content row resource-list">
					<section class="col-4">
						<h4>Lorem ipsum</h4>
						<p>dolor sit amet, consectetur adipiscing elit. Nam eu varius nibh. Aliquam erat volutpat.</p>
						<hr>
						<a>bvbvknd</a>
						<hr>
						<a>bvbvknd</a>
						<hr>
						<a>bvbvknd</a>
						<hr>
						<a>bvbvknd</a>
					</section>
					<section class="col-4">
						<h4>Lorem ipsum</h4>
						<p>dolor sit amet, consectetur adipiscing elit. Nam eu varius nibh. Aliquam erat volutpat.</p>
						<ul>
							<li>vuvdnkfdvnkfdj</li>
							<li>vuvdnkfdvnkfdj</li>
							<li>vuvdnkfdvnkfdj</li>
							<li>vuvdnkfdvnkfdj</li>
						</ul>
					</section>
					<section class="col-4">
						<h4>Lorem ipsum</h4>
						<p>dolor sit amet, consectetur adipiscing elit. Nam eu varius nibh. Aliquam erat volutpat.</p>
					</section>
				</div>
			</div>
			<div class="entry-content row getstarted">
				<section class="col-12">
					<h3>Get started now</h3>
				</section>
				<section class="col-12">
					<p>WordPress&apos; famous five-minute install means that whoever you are, you can get going with WordPress today.</p>
				</section>
				<section class="col-12">
					<a href="/download" class="button download-button">Try WordPress</a>
				</section>
			</div>

		</article><!-- #post-## -->

	</main><!-- #main -->

<?php
get_footer();

