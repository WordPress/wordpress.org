<?php
/**
 * Template Name: About -> Privacy -> Cookies
 *
 * Page template for displaying the Cookie Policy page.
 *
 * @package WordPressdotorg\MainTheme
 */

namespace WordPressdotorg\MainTheme;

$GLOBALS['menu_items'] = [
	'about/privacy'                      => esc_html_x( 'Privacy Policy', 'Page title', 'wporg' ),
	'about/privacy/cookies'              => esc_html_x( 'Cookie Policy', 'Page title', 'wporg' ),
	'about/privacy/data-export-request'  => esc_html_x( 'Data Export Request', 'Page title', 'wporg' ),
	'about/privacy/data-erasure-request' => esc_html_x( 'Data Erasure Request', 'Page title', 'wporg' ),
];

// The Data Export and Data Erasure forms/pages don't currently exist on rosetta sites, and should not be linked here.
if ( isset( $GLOBALS['rosetta'] ) ) {
	unset( $GLOBALS['menu_items']['about/privacy/data-export-request'], $GLOBALS['menu_items']['about/privacy/data-erasure-request'] );
}


// Prevent Jetpack from looking for a non-existent featured image.
add_filter( 'jetpack_images_pre_get_images', function() {
	return new \WP_Error();
} );

// Pretend we're a direct child of the About page for styling purposes.
add_filter( 'body_class', function( $classes ) {
	$classes[] = 'page-parent-about';

	return $classes;
} );


/* See inc/page-meta-descriptions.php for the meta description for this page. */

get_header( 'child-page' );
the_post();
?>
	<main id="main" class="site-main col-12" role="main">

		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<header class="entry-header row">
				<h1 class="entry-title col-8"><?php the_title(); ?></h1>
			</header><!-- .entry-header -->

			<div class="entry-content row">
				<section class="col-8">
					<h2><?php esc_html_e( 'Cookies', 'wporg' ); ?></h2>
					<p><?php
						echo wp_kses_post( sprintf(
							/* translators: %s: Link to the Privacy Policy. */
							__( 'Our <a href="%s">Privacy Policy</a> explains our principles when it comes to the collection, processing, and storage of your information. The Cookie Policy specifically explains how we, our partners, and users of our services deploy cookies, as well as the options you have to control them.', 'wporg' ),
							home_url( '/about/privacy/' )
						) );
					?></p>

					<h2><?php esc_html_e( 'What are cookies?', 'wporg' ); ?></h2>
					<p><?php esc_html_e( 'Cookies are small pieces of data, stored in text files, that are stored on your computer or other device when websites are loaded in a browser. They are widely used to &#8216;remember&#8217; you and your preferences, either for a single visit (through a &#8216;session cookie&#8217;) or for multiple repeat visits (using a &#8216;persistent cookie&#8217;). They ensure a consistent and efficient experience for visitors, and perform essential functions such as allowing users to register and remain logged in. Cookies may be set by the site that you are visiting (known as &#8216;first party cookies&#8217;), or by third parties, such as those who serve content or provide advertising or analytics services on the website (&#8216;third party cookies&#8217;).', 'wporg' ); ?></p>

					<h2><?php esc_html_e( 'Cookies set by WordPress.org', 'wporg' ); ?></h2>
					<p><?php esc_html_e( 'We use cookies for a number of different purposes. Some cookies are necessary for technical reasons; some enable a personalized experience for both visitors and registered users; and some allow the display of advertising from selected third party networks. Some of these cookies may be set when a page is loaded, or when a visitor takes a particular action (clicking the &#8216;like&#8217; or &#8216;follow&#8217; button on a post, for example).', 'wporg' ); ?></p>
					<p><?php esc_html_e( 'Below the different categories of cookies set by WordPress.org are outlined, with specific examples detailed in the tables that follow. This includes their name and purpose. Certain cookies are only set for logged-in visitors, whereas others are set for any visitors, and these are marked below accordingly. Where a cookie only applies to specific subdomains, they are included under the relevant header.', 'wporg' ); ?></p>
					<p><strong><?php esc_html_e( 'Strictly Necessary', 'wporg' ); ?></strong>: <?php esc_html_e( 'These are the cookies that are essential for WordPress.org to perform basic functions. These include those required to allow registered users to authenticate and perform account related functions.', 'wporg' ); ?></p>
					<p><strong><?php esc_html_e( 'Functionality', 'wporg' ); ?></strong>: <?php esc_html_e( 'These cookies are used to store preferences set by users such as account name, language, and location.', 'wporg' ); ?></p>
					<p><strong><?php esc_html_e( 'Performance', 'wporg' ); ?></strong>: <?php esc_html_e( 'Performance cookies collect information on how users interact with websites hosted on WordPress.org, including what pages are visited most, as well as other analytical data. These details are only used to improve how the website functions.', 'wporg' ); ?></p>
					<p><strong><?php esc_html_e( 'Tracking', 'wporg' ); ?></strong>: <?php esc_html_e( 'These are set by trusted third party networks (e.g. Google Analytics) to track details such as the number of unique visitors, and pageviews to help improve the user experience.', 'wporg' ); ?></p>
					<p><strong><?php esc_html_e( 'Third Party/Embedded Content', 'wporg' ); ?></strong>: <?php esc_html_e( 'WordPress.org makes use of different third party applications and services to enhance the experience of website visitors. These include social media platforms such as Facebook and Twitter (through the use of sharing buttons), or embedded content from YouTube and Vimeo. As a result, cookies may be set by these third parties, and used by them to track your online activity. We have no direct control over the information that is collected by these cookies.', 'wporg' ); ?></p>

					<h2><?php esc_html_e( 'wordpress.org', 'wporg' ); ?></h2>
					<table>
						<thead>
							<tr>
								<th><?php esc_html_e( 'Cookie', 'wporg' ); ?></th>
								<th><?php esc_html_e( 'Duration', 'wporg' ); ?></th>
								<th><?php esc_html_e( 'Purpose', 'wporg' ); ?></th>
								<th><?php esc_html_e( 'Logged-in Users Only?', 'wporg' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<th>_ga</th>
								<td><?php esc_html_e( '2 years', 'wporg' ); ?></td>
								<td><a href="https://developers.google.com/analytics/devguides/collection/analyticsjs/cookie-usage#gtagjs_google_analytics_4_-_cookie_usage"><?php esc_html_e( 'Google Analytics', 'wporg' ); ?></a> - <?php esc_html_e( 'Used to distinguish users.', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'No', 'wporg' ); ?></td>
							</tr>
							<tr>
								<th>_ga_&lt;property-id&gt;</th>
								<td><?php esc_html_e( '2 years', 'wporg' ); ?></td>
								<td><a href="https://developers.google.com/analytics/devguides/collection/analyticsjs/cookie-usage#gtagjs_google_analytics_4_-_cookie_usage"><?php esc_html_e( 'Google Analytics', 'wporg' ); ?></a> - <?php esc_html_e( 'Used to persist session state.', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'No', 'wporg' ); ?></td>
							</tr>
							<tr>
								<th>devicePixelRatio</th>
								<td><?php esc_html_e( 'Browser default (1 year)', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'Used to make the site responsive to the visitor&#8217;s screen size.', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'No', 'wporg' ); ?></td>
							</tr>
							<tr>
								<th>wordpress_test_cookie</th>
								<td><?php esc_html_e( 'Session', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'Tests that the browser accepts cookies.', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'No', 'wporg' ); ?></td>
							</tr>
							<tr>
								<th>tk_ai</th>
								<td><?php esc_html_e( '24 hours', 'wporg' ); ?></td>
								<td><a href="https://jetpack.com/support/cookies/"><?php esc_html_e( 'Jetpack', 'wporg' ); ?></a> - <?php esc_html_e( 'Stores the unique identifier for the publisher to enable Jetpack to collect data.', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'No', 'wporg' ); ?></td>
							</tr>
							<tr>
								<th>tk_lr</th>
								<td><?php esc_html_e( '1 year', 'wporg' ); ?></td>
								<td><a href="https://jetpack.com/support/cookies/"><?php esc_html_e( 'Jetpack', 'wporg' ); ?></a> - <?php esc_html_e( 'Stores the unique identifier for the publisher to enable Jetpack to collect data.', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'No', 'wporg' ); ?></td>
							</tr>
							<tr>
								<th>tk_or</th>
								<td><?php esc_html_e( '5 years', 'wporg' ); ?></td>
								<td><a href="https://jetpack.com/support/cookies/"><?php esc_html_e( 'Jetpack', 'wporg' ); ?></a> - <?php esc_html_e( 'Stores the unique identifier for the publisher to enable Jetpack to collect data.', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'No', 'wporg' ); ?></td>
							</tr>
							<tr>
								<th>wp-settings-{user_id}</th>
								<td><?php esc_html_e( '1 year', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'Used to persist a user&#8217;s wp-admin configuration.', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'Yes', 'wporg' ); ?></td>
							</tr>
							<tr>
								<th>wporg_logged_in<br/>wporg_sec</th>
								<td><?php esc_html_e( '14 days if you select &#8220;Remember Me&#8221; when logging in. Otherwise, Session.', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'Used to check whether the current visitor is a logged-in WordPress.org user.', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'Yes', 'wporg' ); ?></td>
							</tr>
							<tr>
								<th>wporg_locale</th>
								<td><?php esc_html_e( '1 year', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'Used to persist a user&#8217;s locale configuration.', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'Yes', 'wporg' ); ?></td>
							</tr>
						</tbody>
					</table>

					<h2><?php esc_html_e( 'make.wordpress.org', 'wporg' ); ?></h2>
					<table>
						<thead>
							<tr>
								<th><?php esc_html_e( 'Cookie', 'wporg' ); ?></th>
								<th><?php esc_html_e( 'Duration', 'wporg' ); ?></th>
								<th><?php esc_html_e( 'Purpose', 'wporg' ); ?></th>
								<th><?php esc_html_e( 'Logged-in Users Only?', 'wporg' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<th>_ga</th>
								<td><?php esc_html_e( '2 years', 'wporg' ); ?></td>
								<td><a href="https://developers.google.com/analytics/devguides/collection/analyticsjs/cookie-usage#gtagjs_google_analytics_4_-_cookie_usage"><?php esc_html_e( 'Google Analytics', 'wporg' ); ?></a> - <?php esc_html_e( 'Used to distinguish users.', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'No', 'wporg' ); ?></td>
							</tr>
							<tr>
								<th>_ga_&lt;property-id&gt;</th>
								<td><?php esc_html_e( '2 years', 'wporg' ); ?></td>
								<td><a href="https://developers.google.com/analytics/devguides/collection/analyticsjs/cookie-usage#gtagjs_google_analytics_4_-_cookie_usage"><?php esc_html_e( 'Google Analytics', 'wporg' ); ?></a> - <?php esc_html_e( 'Used to persist session state.', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'No', 'wporg' ); ?></td>
							</tr>
							<tr>
								<th>welcome-{blog_id}</th>
								<td><?php esc_html_e( 'Permanent', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'Used to record if you&#8217;ve chosen to hide the &#8220;Welcome&#8221; message at the top of the corresponding blog.', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'No', 'wporg' ); ?></td>
							</tr>
							<tr>
								<th>showComments</th>
								<td><?php esc_html_e( '10 years', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'Used to determine if you prefer comments to be shown or hidden when reading the site.', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'No', 'wporg' ); ?></td>
							</tr>
						</tbody>
					</table>

					<h2><?php esc_html_e( '*.trac.wordpress.org', 'wporg' ); ?></h2>
					<table>
						<thead>
							<tr>
								<th><?php esc_html_e( 'Cookie', 'wporg' ); ?></th>
								<th><?php esc_html_e( 'Duration', 'wporg' ); ?></th>
								<th><?php esc_html_e( 'Purpose', 'wporg' ); ?></th>
								<th><?php esc_html_e( 'Logged-in Users Only?', 'wporg' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<th>_ga</th>
								<td><?php esc_html_e( '2 years', 'wporg' ); ?></td>
								<td><a href="https://developers.google.com/analytics/devguides/collection/analyticsjs/cookie-usage#gtagjs_google_analytics_4_-_cookie_usage"><?php esc_html_e( 'Google Analytics', 'wporg' ); ?></a> - <?php esc_html_e( 'Used to distinguish users.', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'No', 'wporg' ); ?></td>
							</tr>
							<tr>
								<th>_ga_&lt;property-id&gt;</th>
								<td><?php esc_html_e( '2 years', 'wporg' ); ?></td>
								<td><a href="https://developers.google.com/analytics/devguides/collection/analyticsjs/cookie-usage#gtagjs_google_analytics_4_-_cookie_usage"><?php esc_html_e( 'Google Analytics', 'wporg' ); ?></a> - <?php esc_html_e( 'Used to persist session state.', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'No', 'wporg' ); ?></td>
							</tr>
							<tr>
								<th>trac_form_token</th>
								<td><?php esc_html_e( 'Session', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'Used as a security token for cross-site request forgery protection.', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'No', 'wporg' ); ?></td>
							</tr>
							<tr>
								<th>trac_session</th>
								<td><?php esc_html_e( '90 days', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'Used to keep anonymous session information.', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'No', 'wporg' ); ?></td>
							</tr>
						</tbody>
					</table>

					<h2><?php esc_html_e( 'codex.wordpress.org', 'wporg' ); ?></h2>
					<table>
						<thead>
							<tr>
								<th><?php esc_html_e( 'Cookie', 'wporg' ); ?></th>
								<th><?php esc_html_e( 'Duration', 'wporg' ); ?></th>
								<th><?php esc_html_e( 'Purpose', 'wporg' ); ?></th>
								<th><?php esc_html_e( 'Logged-in Users Only?', 'wporg' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<th>_ga</th>
								<td><?php esc_html_e( '2 years', 'wporg' ); ?></td>
								<td><a href="https://developers.google.com/analytics/devguides/collection/analyticsjs/cookie-usage#gtagjs_google_analytics_4_-_cookie_usage"><?php esc_html_e( 'Google Analytics', 'wporg' ); ?></a> - <?php esc_html_e( 'Used to distinguish users.', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'No', 'wporg' ); ?></td>
							</tr>
							<tr>
								<th>_ga_&lt;property-id&gt;</th>
								<td><?php esc_html_e( '2 years', 'wporg' ); ?></td>
								<td><a href="https://developers.google.com/analytics/devguides/collection/analyticsjs/cookie-usage#gtagjs_google_analytics_4_-_cookie_usage"><?php esc_html_e( 'Google Analytics', 'wporg' ); ?></a> - <?php esc_html_e( 'Used to persist session state.', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'No', 'wporg' ); ?></td>
							</tr>
							<tr>
								<th>codexToken</th>
								<td><?php esc_html_e( '6 months', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'Used to check whether the current visitor is a logged-in WordPress.org user. Only set if you select &#8220;Keep me logged in&#8221; when logging in.', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'Yes', 'wporg' ); ?></td>
							</tr>
							<tr>
								<th>codexUserId<br/>codexUserName</th>
								<td><?php esc_html_e( '6 months', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'Used to check whether the current visitor is a logged-in WordPress.org user.', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'Yes', 'wporg' ); ?></td>
							</tr>
							<tr>
								<th>codex_session</th>
								<td><?php esc_html_e( 'Session', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'Used to check whether the current visitor is a logged-in WordPress.org user.', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'Yes', 'wporg' ); ?></td>
							</tr>
						</tbody>
					</table>

					<h2><?php esc_html_e( '*.wordcamp.org', 'wporg' ); ?></h2>
					<table>
						<thead>
							<tr>
								<th><?php esc_html_e( 'Cookie', 'wporg' ); ?></th>
								<th><?php esc_html_e( 'Duration', 'wporg' ); ?></th>
								<th><?php esc_html_e( 'Purpose', 'wporg' ); ?></th>
								<th><?php esc_html_e( 'Logged-in Users Only?', 'wporg' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<th>_ga</th>
								<td><?php esc_html_e( '2 years', 'wporg' ); ?></td>
								<td><a href="https://developers.google.com/analytics/devguides/collection/analyticsjs/cookie-usage#gtagjs_google_analytics_4_-_cookie_usage"><?php esc_html_e( 'Google Analytics', 'wporg' ); ?></a> - <?php esc_html_e( 'Used to distinguish users.', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'No', 'wporg' ); ?></td>
							</tr>
							<tr>
								<th>_ga_&lt;property-id&gt;</th>
								<td><?php esc_html_e( '2 years', 'wporg' ); ?></td>
								<td><a href="https://developers.google.com/analytics/devguides/collection/analyticsjs/cookie-usage#gtagjs_google_analytics_4_-_cookie_usage"><?php esc_html_e( 'Google Analytics', 'wporg' ); ?></a> - <?php esc_html_e( 'Used to persist session state.', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'No', 'wporg' ); ?></td>
							</tr>
							<tr>
								<th>camptix_client_stats</th>
								<td><?php esc_html_e( '1 year', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'Used to track unique visitors to tickets page on a WordCamp site', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'No', 'wporg' ); ?></td>
							</tr>
							<tr>
								<th>wp-saving-post</th>
								<td><?php esc_html_e( '1 day', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'Used to track if there is saved post exists for a post currently being edited. If exists then let user restore the data', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'Yes', 'wporg' ); ?></td>
							</tr>
							<tr>
								<th>comment_author_{hash}</th>
								<td><?php esc_html_e( '347 days', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'Used to tracked comment author name, if &#8220;Save my name, email, and website in this browser for the next time I comment.&#8221; is checked', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'No', 'wporg' ); ?></td>
							</tr>
							<tr>
								<th>comment_author_email_{hash}</th>
								<td><?php esc_html_e( '347 days', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'Used to tracked comment author email, if &#8220;Save my name, email, and website in this browser for the next time I comment.&#8221; is checked', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'No', 'wporg' ); ?></td>
							</tr>
							<tr>
								<th>comment_author_url_{hash}</th>
								<td><?php esc_html_e( '347 days', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'Used to track comment author url, if &#8220;Save my name, email, and website in this browser for the next time I comment.&#8221; checkbox is checked', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'No', 'wporg' ); ?></td>
							</tr>
							<tr>
								<th>wp-postpass_{hash}</th>
								<td><?php esc_html_e( '10 days', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'Used to maintain session if a post is password protected', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'No', 'wporg' ); ?></td>
							</tr>
							<tr>
								<th>wp-settings-{user}</th>
								<td><?php esc_html_e( '1 year', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'Used to preserve user&#8217;s wp-admin settings', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'Yes', 'wporg' ); ?></td>
							</tr>
							<tr>
								<th>wp-settings-time-{user}</th>
								<td><?php esc_html_e( '1 year', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'Time at which wp-settings-{user} was set', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'Yes', 'wporg' ); ?></td>
							</tr>
							<tr>
								<th>tix_view_token</th>
								<td><?php esc_html_e( '2 days', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'Used for session managing private CampTix content', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'No', 'wporg' ); ?></td>
							</tr>
							<tr>
								<th>tk_ai</th>
								<td><?php esc_html_e( 'Browser default', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'Used for tracking', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'No', 'wporg' ); ?></td>
							</tr>
							<tr>
								<th>jetpackState</th>
								<td><?php esc_html_e( 'Session', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'Used for maintaining Jetpack State', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'Yes', 'wporg' ); ?></td>
							</tr>
							<tr>
								<th>jpp_math_pass</th>
								<td><?php esc_html_e( 'Session', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'Verifies that a user answered the math problem correctly while logging in.', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'No', 'wporg' ); ?></td>
							</tr>
							<tr>
								<th>stnojs</th>
								<td><?php esc_html_e( '2 days', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'Remember if user do not want JavaScript executed', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'No', 'wporg' ); ?></td>
							</tr>
							<tr>
								<th>wordpress_logged_in_{hash}</th>
								<td><?php esc_html_e( 'Session', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'Remember User session', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'Yes', 'wporg' ); ?></td>
							</tr>
							<tr>
								<th>wordpress_test_cookie</th>
								<td><?php esc_html_e( 'Session', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'Test if cookie can be set', 'wporg' ); ?></td>
								<td><?php esc_html_e( 'No', 'wporg' ); ?></td>
							</tr>
						</tbody>
					</table>

					<h2><?php esc_html_e( 'Controlling Cookies', 'wporg' ); ?></h2>
					<p><?php esc_html_e( 'Visitors may wish to restrict the use of cookies, or completely prevent them from being set. Most browsers provide for ways to control cookie behavior such as the length of time they are stored &#8212; either through built-in functionality or by utilizing third party plugins.', 'wporg' ); ?></p>
					<p>
						<?php
						printf(
							wp_kses_post( __( 'To find out more on how to manage and delete cookies, visit <a href="%1$s">aboutcookies.org</a>. For more details on advertising cookies, and how to manage them, visit <a href="%2$s">youronlinechoices.eu</a> (EU based), or <a href="%3$s">aboutads.info</a> (US based).', 'wporg' ) ),
							'https://www.aboutcookies.org/',
							'https://youronlinechoices.eu/',
							'http://www.aboutads.info/choices/'
						);
						?>
					</p>
					<p><?php esc_html_e( 'Some specific opt-out programs are available here:', 'wporg' ); ?></p>
					<p>Google Analytics - <a href="https://tools.google.com/dlpage/gaoptout">https://tools.google.com/dlpage/gaoptout</a></p>
					<p><?php esc_html_e( 'It&#8217;s important to note that restricting or disabling the use of cookies can limit the functionality of sites, or prevent them from working correctly at all.', 'wporg' ); ?></p>

					<h2><?php esc_html_e( 'WordPress.org', 'wporg' ); ?></h2>
					<p><a rel="license" href="https://creativecommons.org/licenses/by-sa/4.0/"><img alt="Creative Commons License" src="https://s.w.org/images/home/ccbysa40.png"></a></p>
				</section>
			</div><!-- .entry-content -->

		</article><!-- #post-## -->

	</main><!-- #main -->

<?php
get_footer();
