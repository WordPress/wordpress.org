<?php
/**
 * Custom meta descriptions.
 *
 * @package WordPressdotorg\MainTheme
 */

namespace WordPressdotorg\MainTheme;
use WordPressdotorg\API\Serve_Happy\RECOMMENDED_PHP;

/**
 * Add custom open-graph tags for page templates where the content is hard-coded.
 *
 * This is also defined here to allow it to be used on pages where the page template is not included for that page, such as the embed template.
 *
 * @param array $tags Optional. Open Graph tags.
 * @return array Filtered Open Graph tags.
 */
function custom_open_graph_tags( $tags = [] ) {
	$site_title = function_exists( '\WordPressdotorg\site_brand' ) ? \WordPressdotorg\site_brand() : 'WordPress.org';

	// Use `name=""` for description.
	// See Jetpacks Twitter Card for where it happens for the twitter:* fields.
	add_filter( 'jetpack_open_graph_output', function( $html ) {
		return str_replace( '<meta property="description"', '<meta name="description"', $html );
	} );

	// Override the Front-page tags.
	if ( is_front_page() ) {
		return array(
			'og:type'         => 'website',
			'og:title'        => __( 'Blog Tool, Publishing Platform, and CMS', 'wporg' ) . " - {$site_title}",
			'og:description'  => __( 'Open source software which you can use to easily create a beautiful website, blog, or app.', 'wporg' ),
			'description'     => __( 'Open source software which you can use to easily create a beautiful website, blog, or app.', 'wporg' ),
			'og:url'          => home_url( '/' ),
			'og:site_name'    => $site_title,
			'og:image'        => 'https://s.w.org/images/home/wordpress-homepage-ogimage.png',
			'og:locale'       => get_locale(),
			'twitter:card'    => 'summary_large_image',
			'twitter:creator' => '@WordPress',
		);
	}

	$post = get_post();
	if ( ! $post || 'page' !== $post->post_type ) {
		return $tags;
	}

	// These values are not correct for our page templates.
	unset( $tags['article:published_time'], $tags['article:modified_time'] );

	switch ( $post->page_template ) {
		default:
			return $tags;

		case 'page-about-domains.php':
			$title = esc_html__( 'WordPress Domains', 'wporg' );
			$desc  = esc_html__( 'WordPress domains and site names can be very flexible; however, top-level domains can&#8217;t use the word WordPress. Find out what is allowed and what constitutes a trademark violation, as well as policies on subdomain use. Review the list of official WordPress sites to know how to recognize and advise violators.', 'wporg' );
			break;

		case 'page-about-accessibility.php':
			$title = esc_html__( 'WordPress Accessibility', 'wporg' );
			$desc  = esc_html__( 'The WordPress community and the open source WordPress project is committed to being as inclusive and accessible as possible. We want users, regardless of device or ability, to be able to publish content and maintain a website or application built with WordPress.', 'wporg' );
			break;

		case 'page-about-etiquette.php':
			$title = esc_html__( 'Etiquette at WordPress', 'wporg' );
			$desc  = esc_html__( 'We welcome the contributions of everyone who&#8217;s interested in joining the WordPress open source project, and every thriving, diverse community needs etiquette guidelines. Review our simple guidelines that focus on diversity, safety, and inclusion and foster a welcoming community for our contributors around the world.', 'wporg' );
			break;

		case 'page-about-features.php':
			$title = esc_html__( 'WordPress Features', 'wporg' );
			/* translators: 1: WordPress market share: 30 - Note: The following percent sign is '%%' for escaping purposes; 2: Number of WordPress.org hosted plugins; */
			$desc = sprintf( esc_html__( 'Discover why WordPress powers more than %1$s%% of the web. WordPress is a simple, flexible, user-friendly platform, with key features that include media management, SEO, and endless options for customization. More than %2$s plugins extend the core functionality of WordPress even more. Build your site today.', 'wporg' ), number_format_i18n( WP_MARKET_SHARE ), number_format_i18n( 50000 ) );
			break;

		case 'page-about-history.php':
			$title = esc_html__( 'The History of WordPress', 'wporg' );
			/* translators: %s: WordPress market share: 30 - Note: The following percent sign is '%%' for escaping purposes; */
			$desc = sprintf( esc_html__( 'WordPress currently powers more than %s%% of the web. How did it grow to become the world&#8217;s leading web publishing platform? Learn about the history of WordPress: an open source software project built by an active community of contributors who are passionate about collaboration, empowerment, and the open web.', 'wporg' ), number_format_i18n( WP_MARKET_SHARE ) );
			break;

		case 'page-about-license.php':
			$title = esc_html__( 'The GNU Public License', 'wporg' );
			$desc  = esc_html__( 'WordPress is an open source software project, and a fierce believer in the values of the open web. WordPress uses the GNU Public License, which provides a platform for technical expansion and encourages adaptation and innovation. Learn more about this license, and discover what can and cannot be done under it.', 'wporg' );
			break;

		case 'page-about-logos.php':
			$title = esc_html__( 'Graphics &amp; Logos', 'wporg' );
			/* translators: %s: Link to foundation trademark policy; */
			$desc = sprintf( __( 'When you need the official WordPress logo for a web site or publication, please use one of the following. Please only use logos in accordance with the <a href="%s">WordPress trademark&nbsp;policy</a>.', 'wporg' ), esc_url( 'http://wordpressfoundation.org/trademark-policy/' ) );
			break;

		case 'page-about-philosophy.php':
			$title = esc_html__( 'WordPress&rsquo; Philosophy', 'wporg' );
			$desc  = esc_html__( 'At the core of the WordPress philosophy is our commitment to the open web and to building software that works for everyone, from a new user to an advanced developer. Our philosophy pushes WordPress to remain flexible, adaptable, and easy-to-use. Learn about WordPress&#8217;s philosophy and how it shapes our community.', 'wporg' );
			break;

		case 'page-about-privacy.php':
			$title = esc_html__( 'WordPress Privacy Policy', 'wporg' );
			$desc  = esc_html__( 'Like other major software platforms, WordPress gathers and collects statistics and analytical data. Privacy is key in this endeavor and WordPress never discloses any personally identifiable data. Review the WordPress Privacy Policy to learn how, as a participant in this community, you&#8217;re privacy is protected.', 'wporg' );
			break;

		case 'page-about-requirements.php':
			$title = esc_html__( 'Hosting Requirements for WordPress', 'wporg' );
			/* translators: %s: PHP version; */
			$desc  = sprintf( esc_html__( 'Running WordPress doesn&#8217;t require a lot, but your host will still need to meet a few minimum requirements. Learn about the website hosting requirements to run WordPress, including our recommendation to support PHP %s+ and HTTPS. Not sure how to ask your host for these details? Use the sample email we include.', 'wporg' ), RECOMMENDED_PHP );
			break;

		case 'page-about-roadmap.php':
			$title = esc_html__( 'WordPress Development Roadmap', 'wporg' );
			$desc  = esc_html__( 'The WordPress Roadmap lists major releases by date, includes details about the features of each release, and acknowledges the contributing community members. Learn about the status of upcoming releases, development cycles, issues, and milestones. Follow the progress of WordPress development week after week!', 'wporg' );
			break;

		case 'page-about-security.php':
			$title = esc_html__( 'WordPress is Secure', 'wporg' );
			/* translators: %s: WordPress market share: 30 - Note: The following percent sign is '%%' for escaping purposes; */
			$desc = sprintf( esc_html__( 'Why is WordPress recommended as a secure website-building solution? With a passionate open source community and an extensible, easy-to-use platform, WordPress provides flexible and secure options for all levels of users, from beginners to pros. Learn how WordPress guarantees the security of %s%% of the web.', 'wporg' ), number_format_i18n( WP_MARKET_SHARE ) );
			break;

		case 'page-about-stats.php':
			$title = esc_html__( 'Key WordPress Statistics', 'wporg' );
			$desc  = esc_html__( 'WordPress is committed to transparency, and you can get a better sense of its constant worldwide growth through the statistics we share. Review key WordPress stats including usage breakdown by WordPress versions, PHP and MySQL versions being run, and locales of use, and see how WordPress expands its global reach.', 'wporg' );
			break;

		case 'page-about-swag.php':
			$title = esc_html__( 'WordPress Swag', 'wporg' );
			$desc  = esc_html__( 'Show your WordPress pride and run with the coolest swag! You&#8217;ll be surprised how widely recognized our logo is around the world, bringing people together through recognition and community. Choose your WordPress swag today (Wapuu t-shirt, anyone?) and your purchase will also support free swag at WordCamps and meetups.', 'wporg' );
			break;

		case 'page-about-testimonials.php':
			$title = esc_html__( 'WordPress Testimonials', 'wporg' );
			$desc  = esc_html__( 'People like to talk about WordPress! Make a tweet or a post using the #ilovewp hashtag, and your comments might be featured on WordPress.org!', 'wporg' );
			break;

		case 'page-about.php':
			$title = esc_html__( 'Democratize Publishing', 'wporg' );
			$desc  = esc_html__( 'Learn about the team behind WordPress, and where the most popular online publishing platform is heading in the future.', 'wporg' );
			break;

		case 'page-about-privacy-data-erasure-request.php':
			$title = esc_html_x( 'Data Erasure Request', 'Page title', 'wporg' );
			$desc  = esc_html__( 'WordPress.org respects your privacy and intends to remain transparent about any personal data we store about individuals. Under the General Data Protection Regulation (GDPR), EU citizens and residents may request deletion of personal data stored on our servers.', 'wporg' );
			break;

		case 'page-about-privacy-data-export-request.php':
			$title = esc_html_x( 'Data Export Request', 'Page title', 'wporg' );
			$desc  = esc_html__( 'WordPress.org respects your privacy and intends to remain transparent about any personal data we store about individuals. Under the General Data Protection Regulation (GDPR), EU citizens and residents are entitled to receive a copy of any personal data we might hold about you.', 'wporg' );
			break;

		case 'page-about-privacy-cookies.php':
			$title = esc_html_x( 'Cookie Policy', 'Page title', 'wporg' );
			$desc  = esc_html__( 'This policy specifically explains how WordPress.org, our partners, and users of our services deploy cookies, as well as the options you have to control them.', 'wporg' );
			break;

		case 'page-download.php':
			$title = esc_html_x( 'Download', 'Page title', 'wporg' );
			$desc  = esc_html__( 'Download WordPress today, and get started on creating your website with one of the most powerful, popular, and customizable platforms in the world.', 'wporg' );
			break;

		case 'page-download-beta-nightly.php':
			$title = esc_html_x( 'Beta/Nightly', 'Page title', 'wporg' );
			$desc  = esc_html__( 'Get the latest, unstable or work-in-progress versions of WordPress for testing and development.', 'wporg' );
			break;

		case 'page-download-releases.php':
			$title = esc_html_x( 'Releases', 'Page title', 'wporg' );
			$desc  = esc_html__( 'Browse and download previous versions of WordPress for testing and development.', 'wporg' );
			break;

		case 'page-download-source.php':
			$title = esc_html_x( 'Source Code', 'Page title', 'wporg' );
			$desc  = esc_html__( 'See how WordPress works under the hood, and contribute your own code to the world&#8217;s most popular content management system.', 'wporg' );
			break;

		case 'page-40-percent-of-web.php':
			$title = esc_html_x( 'WordPress and the Journey to 40% of the Web', 'Page title', 'wporg' );
			$desc  = esc_html__( 'Getting to 40% of the web came with lots of hard work from our amazing WordPress community.', 'wporg' );
			break;

		case 'page-hosting.php':
			$title = esc_html_x( 'WordPress Hosting Recommendations', 'Page title', 'wporg' );
			$desc  = esc_html__( 'Get web hosting for your WordPress website from providers that have modern and approved server configurations.', 'wporg' );
			break;

		case 'page-mobile.php':
			$title = esc_html_x( 'WordPress Mobile Apps', 'Page title', 'wporg' );
			$desc  = esc_html__( 'Manage your site with our Android, iOS, and desktop apps', 'wporg' );
			break;

		case 'page-search.php':
			// Intentionally not internationalised as these are currently english only.
			$title = 'Search';
			$desc  = 'Search the WordPress.org website for plugins, themes, and support.';
			break;
	}

	$tags['og:title']            = $title;
	$tags['twitter:text:title']  = $title;
	$tags['og:description']      = $desc;
	$tags['twitter:description'] = $desc;
	$tags['description']         = $desc;

	return $tags;
}
add_filter( 'jetpack_open_graph_tags', __NAMESPACE__ . '\custom_open_graph_tags' );

/**
 * Renders site's attributes for the WordPress.org frontpages (including Rosetta).
 *
 * @see https://developers.google.com/search/docs/guides/enhance-site
 */
function sites_attributes_schema() {
	global $rosetta;

	if ( ! is_front_page() ) {
		return;
	}

	$og_tags         = custom_open_graph_tags();
	$locale_language = 'en';
	$name            = 'WordPress.org';

	if ( ! empty( $rosetta->rosetta->glotpress_locale ) ) {
		$locale_language = $rosetta->rosetta->glotpress_locale->slug;
		$name            = sprintf(
			__( 'WordPress - %s', 'wporg' ),
			$rosetta->rosetta->glotpress_locale->native_name
		);
	}

	?>
<script type="application/ld+json">
{
	"@context":"https://schema.org",
	"@graph":[
		{
			"@type":"Organization",
			"@id":"https://wordpress.org/#organization",
			"url":"https://wordpress.org/",
			"name":"WordPress",
			"logo":{
				"@type":"ImageObject",
				"@id":"https://wordpress.org/#logo",
				"url":"https://s.w.org/style/images/about/WordPress-logotype-wmark.png"
			},
			"sameAs":[
				"https://www.facebook.com/WordPress/",
				"https://twitter.com/WordPress",
				"https://en.wikipedia.org/wiki/WordPress"
			]
		},
		{
			"@type":"WebSite",
			"@id":"<?php echo esc_js( home_url( '/#website' ) ); ?>",
			"url":"<?php echo esc_js( home_url( '/' ) ); ?>",
			"name":"<?php echo esc_js( $name ); ?>",
			"publisher":{
				"@id":"https://wordpress.org/#organization"
			}
		},
		{
			"@type":"WebPage",
			"@id":"<?php echo esc_js( home_url( '/' ) ); ?>",
			"url":"<?php echo esc_js( home_url( '/' ) ); ?>",
			"inLanguage":"<?php echo esc_js( $locale_language ); ?>",
			"name":"<?php echo esc_js( $og_tags['og:title'] ); ?>",
			"description":"<?php echo esc_js( $og_tags['og:description'] ); ?>",
			"isPartOf":{
				"@id":"<?php echo esc_js( home_url( '/#website' ) ); ?>"
			}
		}
	]
}
</script>
<?php
}
add_action( 'wp_head', __NAMESPACE__ . '\sites_attributes_schema' );

/**
 * Maps page titles to translatable strings.
 *
 * @param string      $title The post title.
 * @param WP_Post|int $post  Optional. Post object or ID.
 * @return string Filtered post tile.
 */
function custom_page_title( $title, $post = null ) {
	if ( ! $post ) {
		return $title;
	}

	$post = get_post( $post );
	if ( ! $post || 'page' !== $post->post_type ) {
		return $title;
	}

	switch ( $post->page_template ) {
		case 'page-about-domains.php':
			$title = esc_html_x( 'Domains', 'Page title', 'wporg' );
			break;

		case 'page-about-accessibility.php':
			$title = esc_html_x( 'Accessibility', 'Page title', 'wporg' );
			break;

		case 'page-about-etiquette.php':
			$title = esc_html_x( 'Etiquette', 'Page title', 'wporg' );
			break;

		case 'page-about-features.php':
			$title = esc_html_x( 'Features', 'Page title', 'wporg' );
			break;

		case 'page-about-history.php':
			$title = esc_html_x( 'History', 'Page title', 'wporg' );
			break;

		case 'page-about-license.php':
			$title = esc_html_x( 'GNU Public License', 'Page title', 'wporg' );
			break;

		case 'page-about-logos.php':
			$title = esc_html_x( 'Graphics &amp; Logos', 'Page title', 'wporg' );
			break;

		case 'page-about-philosophy.php':
			$title = esc_html_x( 'Philosophy', 'Page title', 'wporg' );
			break;

		case 'page-about-privacy.php':
			$title = esc_html_x( 'Privacy Policy', 'Page title', 'wporg' );
			break;

		case 'page-about-requirements.php':
			$title = esc_html_x( 'Requirements', 'Page title', 'wporg' );
			break;

		case 'page-about-roadmap.php':
			$title = esc_html_x( 'Roadmap', 'Page title', 'wporg' );
			break;

		case 'page-about-security.php':
			$title = esc_html_x( 'Security', 'Page title', 'wporg' );
			break;

		case 'page-about-stats.php':
			$title = esc_html_x( 'Statistics', 'Page title', 'wporg' );
			break;

		case 'page-about-swag.php':
			$title = esc_html_x( 'Swag', 'Page title', 'wporg' );
			break;

		case 'page-about-testimonials.php':
			$title = esc_html_x( 'Testimonials', 'Page title', 'wporg' );
			break;

		case 'page-about.php':
			if ( 'single_post_title' === current_filter() ) {
				$title = esc_html_x( 'About Us: Our Mission', 'Page title', 'wporg' );
			} else {
				$title = esc_html_x( 'About', 'Page title', 'wporg' );
			}
			break;

		case 'page-about-privacy-data-erasure-request.php':
			$title = esc_html_x( 'Data Erasure Request', 'Page title', 'wporg' );
			break;

		case 'page-about-privacy-data-export-request.php':
			$title = esc_html_x( 'Data Export Request', 'Page title', 'wporg' );
			break;

		case 'page-about-privacy-cookies.php':
			$title = esc_html_x( 'Cookie Policy', 'Page title', 'wporg' );
			break;

		case 'page-download.php':
			$title = esc_html_x( 'Download', 'Page title', 'wporg' );
			break;

		case 'page-download-beta-nightly.php':
			$title = esc_html_x( 'Beta/Nightly', 'Page title', 'wporg' );
			break;

		case 'page-download-counter.php':
			$title = esc_html_x( 'Counter', 'Page title', 'wporg' );
			break;

		case 'page-download-releases.php':
			$title = esc_html_x( 'Releases', 'Page title', 'wporg' );
			break;

		case 'page-download-source.php':
			$title = esc_html_x( 'Source Code', 'Page title', 'wporg' );
			break;

		case 'page-40-percent-of-web.php':
			$title = esc_html_x( 'WordPress and the Journey to 40% of the Web', 'Page title', 'wporg' );
			break;

		case 'page-hosting.php':
			$title = esc_html_x( 'WordPress Hosting', 'Page title', 'wporg' );
			break;

		case 'page-mobile.php':
			$title = esc_html_x( 'WordPress Mobile Apps', 'Page title', 'wporg' );
			break;
	}

	return $title;
}
add_filter( 'the_title', __NAMESPACE__ . '\custom_page_title', 10, 2 );
add_filter( 'single_post_title', __NAMESPACE__ . '\custom_page_title', 10, 2 );

/**
 * Set the document title on the front page.
 */
function document_title_parts( $title ) {
	if ( isset( $title['site'] ) || is_front_page() ) {
		$title['site'] = 'WordPress.org'; // Rosetta will replace as needed.
	}

	if ( is_front_page() ) {
		$title['title'] = __( 'Blog Tool, Publishing Platform, and CMS', 'wporg' );
		unset( $title['tagline'] ); // Remove the tagline from the front-page.
	}

	return $title;
}
add_filter( 'document_title_parts', __NAMESPACE__ . '\document_title_parts' );
