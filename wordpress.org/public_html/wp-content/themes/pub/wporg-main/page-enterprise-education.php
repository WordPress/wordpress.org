<?php
/**
 * Template Name: Enterprise-Education
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
					<img width="300" src="<?php echo get_theme_file_uri('images/enterprise/education.png'); ?>">
					<h1 class="entry-title">WordPress in Education</h1>
					<p class="entry-description">As a result of its flexibility, ease of use (for authors and administrators), open source license, focus on backward compatibility, and large community of developers, designers, and consultants, WordPress is the content management system of choice for K12 and Higher Education across the globe.</p>
					<div style="clear:both"></div>
				</hgroup>

			</header><!-- .entry-header -->

			<div class="entry-content row usecases">
				<section class="col-12">
					<h2 class="header">Use Cases</h2>
					<p>In the world of education, WordPress is used to power everything from simple news sites or blogs (specific to courses, departments, teachers, or campuses) to large networks representing entire University systems or school districts with hundreds or thousands of participating sites.</p>
					<p>Here's just a sampling of educational institutions relying on WordPress as a significant part of their digital content management / web publishing initiatives:</p>
				</section>
				<div class="col-12">
					<hr class="bluesmall">
					<h3>Public Universities</h3>
				</div>
				<div class="entry-content row enterprise-logos">
					<section class="col-2"><img src="<?php echo get_theme_file_uri('images/enterprise/boisestate.png'); ?>"></section>
					<section class="col-2"><img src="<?php echo get_theme_file_uri('images/enterprise/sprott.png'); ?>"></section>
					<section class="col-2"><img src="<?php echo get_theme_file_uri('images/enterprise/cuny.png'); ?>"></section>
					<section class="col-2"><img src="<?php echo get_theme_file_uri('images/enterprise/coloradostate.png'); ?>"></section>
					<section class="col-2"><img src="<?php echo get_theme_file_uri('images/enterprise/curtinuniversity.png'); ?>"></section>
					<section class="col-2"><img src="<?php echo get_theme_file_uri('images/enterprise/fiu.png'); ?>"></section>
					<section class="col-2"><img src="<?php echo get_theme_file_uri('images/enterprise/georgiastate.png'); ?>"></section>
					<section class="col-2"><img src="<?php echo get_theme_file_uri('images/enterprise/iaia.png'); ?>"></section>
					<section class="col-2"><img src="<?php echo get_theme_file_uri('images/enterprise/newcollegeofflorida.png'); ?>"></section>
					<section class="col-2"><img src="<?php echo get_theme_file_uri('images/enterprise/nichollsstate.png'); ?>"></section>
					<section class="col-2"><img src="<?php echo get_theme_file_uri('images/enterprise/uofalabama.png'); ?>"></section>
					<section class="col-2"><img src="<?php echo get_theme_file_uri('images/enterprise/ualr.png'); ?>"></section>
					<section class="col-2"><img src="<?php echo get_theme_file_uri('images/enterprise/ubc.png'); ?>"></section>
					<section class="col-2"><img src="<?php echo get_theme_file_uri('images/enterprise/berkleylaw.png'); ?>"></section>
					<section class="col-2"><img src="<?php echo get_theme_file_uri('images/enterprise/cambridge.png'); ?>"></section>
					<section class="col-2"><img src="<?php echo get_theme_file_uri('images/enterprise/ucf.png'); ?>"></section>
					<section class="col-2"><img src="<?php echo get_theme_file_uri('images/enterprise/uofflorida.png'); ?>"></section>
					<section class="col-2"><img src="<?php echo get_theme_file_uri('images/enterprise/uofhawaii.png'); ?>"></section>
					<section class="col-2"><img src="<?php echo get_theme_file_uri('images/enterprise/uofmaine.png'); ?>"></section>
					<section class="col-2"><img src="<?php echo get_theme_file_uri('images/enterprise/uofmarywashington.png'); ?>"></section>
					<section class="col-2"><img src="<?php echo get_theme_file_uri('images/enterprise/uofmelbourne.png'); ?>"></section>
					<section class="col-2"><img src="<?php echo get_theme_file_uri('images/enterprise/uofmichigan.png'); ?>"></section>
					<section class="col-2"><img src="<?php echo get_theme_file_uri('images/enterprise/centerforeuropeanstudies.png'); ?>"></section>
					<section class="col-2"><img src="<?php echo get_theme_file_uri('images/enterprise/usc.png'); ?>"></section>
					<section class="col-2"><img src="<?php echo get_theme_file_uri('images/enterprise/uofwashington.png'); ?>"></section>
					<section class="col-2"><img src="<?php echo get_theme_file_uri('images/enterprise/uofwisconsinmilwaukee.png'); ?>"></section>
					<section class="col-2"><img src="<?php echo get_theme_file_uri('images/enterprise/vcuarts.png'); ?>"></section>
					<section class="col-2"><img src="<?php echo get_theme_file_uri('images/enterprise/wallawalla.png'); ?>"></section>
					<section class="col-2"><img src="<?php echo get_theme_file_uri('images/enterprise/washingtonstate.png'); ?>"></section>
				</div>
			</div>

			<div class="entry-content row usecases">
				<section class="col-12">
					<h2 class="header">Use Cases</h2>
					<p>In towns and cities, regions and countries, all over the world, WordPress powers the sites people visit multiple times every day for news, information and entertainment.</p>
				</section>
			</div>

			<div class="entry-content row examples">
				<section class="col-6">
					<h4>Private Liberal Arts Colleges</h4>
					<img src="<?php echo get_theme_file_uri('images/enterprise/bates.png'); ?>">
					<img src="<?php echo get_theme_file_uri('images/enterprise/dawson.png'); ?>">
					<img src="<?php echo get_theme_file_uri('images/enterprise/dominicancollege.png'); ?>">
					<img src="<?php echo get_theme_file_uri('images/enterprise/dominicanuniversity.png'); ?>">
					<img src="<?php echo get_theme_file_uri('images/enterprise/lafayette.png'); ?>">
					<img src="<?php echo get_theme_file_uri('images/enterprise/wheaton.png'); ?>">
				</section>
				<section class="col-6">
					<h4>Private Research Universities</h4>
					<img src="<?php echo get_theme_file_uri('images/enterprise/bostonu.png'); ?>">
					<img src="<?php echo get_theme_file_uri('images/enterprise/bayloru.png'); ?>">
					<img src="<?php echo get_theme_file_uri('images/enterprise/casewestern.png'); ?>">
					<img src="<?php echo get_theme_file_uri('images/enterprise/mitsloan.png'); ?>">
					<img src="<?php echo get_theme_file_uri('images/enterprise/northeastern.png'); ?>">
					<img src="<?php echo get_theme_file_uri('images/enterprise/stanford.png'); ?>">
				</section>
			</div>

			<div class="entry-content row examples">
				<section class="col-4">
					<h4>Ivy League</h4>
					<img src="<?php echo get_theme_file_uri('images/enterprise/harvard.png'); ?>">
					<img src="<?php echo get_theme_file_uri('images/enterprise/princeton.png'); ?>">
				</section>
				<section class="col-4">
					<h4>Professional &<br> Medical Schools</h4>
					<img src="<?php echo get_theme_file_uri('images/enterprise/desmoines.png'); ?>">
					<img src="<?php echo get_theme_file_uri('images/enterprise/westernstates.png'); ?>">
				</section>
				<section class="col-4">
					<h4>K-12 Education</h4>
					<img src="<?php echo get_theme_file_uri('images/enterprise/newark.png'); ?>">
					<img src="<?php echo get_theme_file_uri('images/enterprise/lcti.png'); ?>">
				</section>
			</div>

			<div class="entry-content row usecases">
				<section class="col-12">
					<h2 class="header">Accessibility</h2>
					<p>Although it should be a concern for all providers of web-based experiences, the world of education recognizes that accessibility is a core requirement for every site. Public universities in the United States, for example, are generally understood to be required to be compliant with Section 508 of the Rehabilitation Act of 1973. The Web Content Accessibility Guidelines (WCAG) define a set of standards broadly adopted by the international web community (including most US-based institutions).</p>
					<p>While compliance with either Section 508 or WCAG 2.0 depends on a number of factors, including decisions made by site developers, theme developers, and content authors themselves, WordPress as a software project takes accessibility very seriously and works to ensure the platform itself supports accessibility, through a core Accessibility Team.</p>
					<p>The education community within WordPress is also frequently involved, documenting their own approaches to creating and maintaining accessible experiences through WordCamp talks (many available on WordPress.tv), and providing plugins and tools which encourage and monitor accessibility compliance (see Integrations below).</p>
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
						<h4>Accessibility</h4>
						<hr>
						<div class="logos">
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/washingtonstate.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/wpaccessibilityhandbook.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/wpaccessibility.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/siteimprove.png'); ?>"></div>
						</div>
					</section>
					<section class="col-4 integration">
						<img class="icon" src="<?php echo get_theme_file_uri('images/enterprise/iconvideo.png'); ?>">
						<h4>Single-Sign On/<br>Federated Identity</h4>
						<hr>
						<div class="logos">
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/shibbolethlogo.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/saml.png'); ?>"></div>
						</div>
					</section>
					<section class="col-4 integration">
						<img class="icon" src="<?php echo get_theme_file_uri('images/enterprise/iconasset.png'); ?>">
						<h4>Search</h4>
						<hr>
						<div class="logos">
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/elasticsearchlogo.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/google.png'); ?>"></div>
						</div>
					</section>
				</div>
				<div class="entry-content row integrations-list">
					<section class="col-4 integration">
						<img class="icon" src="<?php echo get_theme_file_uri('images/enterprise/iconanalytics.png'); ?>">
						<h4>Calendar/Events</h4>
						<hr>
						<div class="logos">
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/localist.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/theeventscalendar.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/calendarwiz.png'); ?>"></div>
						</div>
					</section>
					<section class="col-4 integration">
						<img class="icon" src="<?php echo get_theme_file_uri('images/enterprise/iconautomation.png'); ?>">
						<h4>News/Syndication</h4>
						<hr>
						<div class="logos">
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/distributorlogo.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/washingtonstate.png'); ?>"></div>
						</div>
					</section>
					<section class="col-4 integration">
						<img class="icon" src="<?php echo get_theme_file_uri('images/enterprise/iconseo.png'); ?>">
						<h4>WordPress Powered Learning Management Systems</h4>
						<hr>
						<div class="logos">
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/learndash.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/lifterlms.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/learnpress.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/wpcourseware.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/senseilms.png'); ?>"></div>
						</div>
					</section>
				</div>
				<div class="entry-content row integrations-list">
					<section class="col-4 integration">
						<img class="icon" src="<?php echo get_theme_file_uri('images/enterprise/iconchannels.png'); ?>">
						<h4>Learning Management Systems</h4>
						<hr>
						<div class="logos">
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/blackboard.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/edmodo.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/moodle.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/openedx.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/sakai.png'); ?>"></div>
						</div>
					</section>
					<section class="col-4 integration">
						<img class="icon" src="<?php echo get_theme_file_uri('images/enterprise/iconsocial.png'); ?>">
						<h4>Student Information Systems</h4>
						<hr>
						<div class="logos">
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/ellucian.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/blackbaud.png'); ?>"></div>
							<div class="logo"><img src="<?php echo get_theme_file_uri('images/enterprise/empower.png'); ?>"></div>
						</div>
					</section>
					<section class="col-4 integration cta blue">
						<h4>Know more for<br>integrations &rarr;</h4>
					</section>
				</div>
			</div>



			<div class="entry-content row education-moreinfo">
				<section class="col-12">
					<div class="groups">
						<h2 class="header">For More Information</h2>
						<h3>Groups</h3>
						<hr class="bluesmall">
						<img src="<?php echo get_theme_file_uri('images/enterprise/wpcampus.png'); ?>">
						<p>Active community of WordPress practitioners working in higher education:  "Our goal is to provide a wealth of knowledge for anyone who's interested in using WordPress and allow people to share and learn about WordPress in the world of higher education." Hosts an annual conference.</p>

						<img src="<?php echo get_theme_file_uri('images/enterprise/wpedu.png'); ?>">
						<p>"Low-Traffic list discussing WordPress in education"</p>
					</div>
					<div class="videos">
						<h3 class="header">Videos</h3>
						<hr class="bluesmall">
						<p>WordPress.tv - WordCamp US presentation from 2017<br>
						Managing Accessible Content on Thousands of Sites &rarr;</p>

						<p>WordPress.tv - WordCamp Boston presentation from 2016<br>
						A Centralized Approach to Managing WordPress At Boston University &rarr;</p>

						<p>WordPress.tv - WordCamp Atlanta presentation from 2016<br>
						Using WordPress in the World of Higher Education &rarr;</p>

						<p>Pantheon Webinar recording with Shane Pearlman from Modern Tribe<br>
						Making the Case for WordPress in Education &rarr;</p>

						<p>WordPress.tv - WordCamp Denver 2015<br>
						WordPress in Higher Education Case Study - Tufts &rarr;</p>

						<p>WordPress.tv - WordCamp US 2016<br>
						WordPress for Schools &rarr;</p>
					</div>
				</div>
			</div>

		</article><!-- #post-## -->

	</main><!-- #main -->

<?php
get_footer();

