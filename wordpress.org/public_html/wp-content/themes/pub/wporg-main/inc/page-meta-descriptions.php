<?php
/**
 * Custom template tags
 *
 * @package WordPressdotorg\MainTheme
 */

namespace WordPressdotorg\MainTheme;

/**
 * Add custom open-grapgh tags for page templates where the content is hard-coded.
 *
 * This is also defined here to allow it to be used on pages where the page template is not included for that page, sych as the embed template.
 */
function custom_open_graph_tags( $tags = array() ) {
	$post = get_post();

	switch ( $post->page_template ) {
		default:
			return $tags;
			break;

		case 'page-about-domains.php':
			$title = _esc_html__( 'WordPress Domains', 'wporg' );
			$desc  = _esc_html__( 'WordPress domains and site names can be very flexible; however, top-level domains can&#8217;t use the word WordPress. Find out what is allowed and what constitutes a trademark violation, as well as policies on subdomain use. Review the list of official WordPress sites to know how to recognize and advise violators.', 'wporg' );
			break;

		case 'page-about-etiquette.php':
			$title = _esc_html__( 'Etiquette at WordPress', 'wporg' );
			$desc  = _esc_html__( 'We welcome the contributions of everyone who&#8217;s interested in joining the WordPress open source project, and every thriving, diverse community needs etiquette guidelines. Review our simple guidelines that focus on diversity, safety, and inclusion and foster a welcoming community for our contributors around the world.', 'wporg' );
			break;

		case 'page-about-features.php':
			$title = _esc_html__( 'WordPress Features', 'wporg' );
			/* translators: WordPress market share: 30%; */
			$desc  = sprintf( _esc_html__( 'Discover why WordPress powers more than %s of the web. WordPress is a simple, flexible, user-friendly platform, with key features that include media management, SEO, and endless options for customization. More than 50,000 plugins extend the core functionality of WordPress even more. Build your site today.', 'wporg' ), WP_MARKET_SHARE . '%' );
			break;

		case 'page-about-history.php':
			$title = _esc_html__( 'The History of WordPress', 'wporg' );
			/* translators: WordPress market share: 30%; */
			$desc  = sprintf( _esc_html__( 'WordPress currently powers more than %s of the web. How did it grow to become the world&#8217;s leading web publishing platform? Learn about the history of WordPress: an open source software project built by an active community of contributors who are passionate about collaboration, empowerment, and the open web.', 'wporg' ), WP_MARKET_SHARE . '%' );
			break;

		case 'page-about-license.php':
			$title = _esc_html__( 'The GNU Public License', 'wporg' );
			$desc  = _esc_html__( 'WordPress is an open source software project, and a fierce believer in the values of the open web. WordPress uses the GNU Public License, which provides a platform for technical expansion and encourages adaptation and innovation. Learn more about this license, and discover what can and cannot be done under it.', 'wporg' );
			break;

		case 'page-about-logos.php':
			$title = _esc_html__( 'Graphics &amp; Logos', 'wporg' );
			$desc  = sprintf( ___( 'When you need the official WordPress logo for a web site or publication, please use one of the following. Please only use logos in accordance with the <a href="%s">WordPress trademark&nbsp;policy</a>.', 'wporg' ), esc_url( 'http://wordpressfoundation.org/trademark-policy/' ) );
			break;

		case 'page-about-philosophy.php':
			$title = _esc_html__( 'WordPress&rsquo; Philosophy', 'wporg' );
			$desc  = _esc_html__( 'At the core of the WordPress philosophy is our commitment to the open web and to building software that works for everyone, from a new user to an advanced developer. Our philosophy pushes WordPress to remain flexible, adaptable, and easy-to-use. Learn about WordPress&#8217;s philosophy and how it shapes our community.', 'wporg' );
			break;

		case 'page-about-privacy.php':
			$title = _esc_html__( 'WordPress Privacy Policy', 'wporg' );
			$desc  = _esc_html__( 'Like other major software platforms, WordPress gathers and collects statistics and analytical data. Privacy is key in this endeavor and WordPress never discloses any personally identifiable data. Review the WordPress Privacy Policy to learn how, as a participant in this community, you&#8217;re privacy is protected.', 'wporg' );
			break;

		case 'page-about-requirements.php':
			$title = _esc_html__( 'Hosting Requirements for WordPress', 'wporg' );
			$desc  = _esc_html__( 'Running WordPress doesn&#8217;t require a lot, but your host will still need to meet a few minimum requirements. Learn about the website hosting requirements to run WordPress, including our recommendation to support PHP 7.2+ and HTTPS. Not sure how to ask your host for these details? Use the sample email we include.', 'wporg' );
			break;

		case 'page-about-roadmap.php':
			$title = _esc_html__( 'WordPress Development Roadmap', 'wporg' );
			$desc  = _esc_html__( 'The WordPress Roadmap lists major releases by date, includes details about the features of each release, and acknowledges the contributing community members. Learn about the status of upcoming releases, development cycles, issues, and milestones. Follow the progress of WordPress development week after week!', 'wporg' );
			break;

		case 'page-about-security.php':
			$title = _esc_html__( 'WordPress is Secure', 'wporg' );
			/* translators: WordPress market share: 30%; */
			$desc  = sprintf( _esc_html__( 'Why is WordPress recommended as a secure website-building solution? With a passionate open source community and an extensible, easy-to-use platform, WordPress provides flexible and secure options for all levels of users, from beginners to pros. Learn how WordPress guarantees the security of %s of the web.', 'wporg' ), WP_MARKET_SHARE . '%' );
			break;

		case 'page-about-stats.php':
			$title = _esc_html__( 'Key WordPress Statistics', 'wporg' );
			$desc  = _esc_html__( 'WordPress is committed to transparency, and you can get a better sense of its constant worldwide growth through the statistics we share. Review key WordPress stats including usage breakdown by WordPress versions, PHP and MySQL versions being run, and locales of use, and see how WordPress expands its global reach.', 'wporg' );
			break;

		case 'page-about-swag.php':
			$title = _esc_html__( 'WordPress Swag', 'wporg' );
			$desc  = _esc_html__( 'Show your WordPress pride and run with the coolest swag! You&#8217;ll be surprised how widely recognized our logo is around the world, bringing people together through recognition and community. Choose your WordPress swag today (Wapuu t-shirt, anyone?) and your purchase will also support free swag at WordCamps and meetups.', 'wporg' );
			break;

		case 'page-about.php':
			$title = _esc_html__( 'Democratize Publishing', 'wporg' );
			$desc  = _esc_html__( 'WordPress is software designed for everyone with emphasis on accessibility, performance, security, and usability.', 'wporg' );
			break;
	}

	$tags['og:title']            = $title;
	$tags['twitter:text:title']  = $title;
	$tags['og:description']      = $desc;
	$tags['twitter:description'] = $desc;

	return $tags;
}
add_filter( 'jetpack_open_graph_tags', __NAMESPACE__ . '\custom_open_graph_tags' );
