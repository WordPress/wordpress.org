<?php
/**
 * Template Name: About -> Privacy
 *
 * Page template for displaying the Privacy page.
 *
 * @package WordPressdotorg\MainTheme
 */

namespace WordPressdotorg\MainTheme;

$GLOBALS['menu_items'] = [
	'about/domains'       => _x( 'Domains', 'Page title', 'wporg' ),
	'about/license'       => _x( 'GNU Public License', 'Page title', 'wporg' ),
	'about/accessibility' => _x( 'Accessibility', 'Page title', 'wporg' ),
	'about/privacy'       => _x( 'Privacy Policy', 'Page title', 'wporg' ),
	'about/stats'         => _x( 'Statistics', 'Page title', 'wporg' ),
];

// Prevent Jetpack from looking for a non-existent featured image.
add_filter( 'jetpack_images_pre_get_images', function() {
	return new \WP_Error();
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
					<p><?php esc_html_e( 'WordPress.org websites (collectively &#8220;WordPress.org&#8221; in this document) refer to sites hosted on the WordPress.org, WordPress.net, WordCamp.org, BuddyPress.org, bbPress.org, and other related domains and subdomains thereof. This privacy policy describes how WordPress.org uses and protects any information that you give us. We are committed to ensuring that your privacy is protected. If you provide us with personal information through WordPress.org, you can be assured that it will only be used in accordance with this privacy statement.', 'wporg' ); ?></p>

					<h2><?php esc_html_e( 'Website Visitors', 'wporg' ); ?></h2>
					<p><?php esc_html_e( 'Like most website operators, WordPress.org collects non-personally-identifying information of the sort that web browsers and servers typically make available, such as the browser type, language preference, referring site, and the date and time of each visitor request. WordPress.org&#8217;s purpose in collecting non-personally identifying information is to better understand how WordPress.org&#8217;s visitors use its website. From time to time, WordPress.org may release non-personally-identifying information in the aggregate, e.g., by publishing a report on trends in the usage of its website.', 'wporg' ); ?></p>

					<p><?php esc_html_e( 'WordPress.org also collects potentially personally-identifying information like Internet Protocol (IP) addresses. WordPress.org does not use IP addresses to identify its visitors, however, and does not disclose such information, other than under the same circumstances that it uses and discloses personally-identifying information, as described below.', 'wporg' ); ?></p>

					<h2><?php esc_html_e( 'Gathering of Personally-Identifying Information', 'wporg' ); ?></h2>
					<p><?php esc_html_e( 'Certain visitors to WordPress.org choose to interact with WordPress.org in ways that require WordPress.org to gather personally-identifying information. The amount and type of information that WordPress.org gathers depends on the nature of the interaction. For example, we ask visitors who use our forums to provide a username and email address.', 'wporg' ); ?></p>
					<p><?php esc_html_e( 'In each case, WordPress.org collects such information only insofar as is necessary or appropriate to fulfill the purpose of the visitor&#8217;s interaction with WordPress.org. WordPress.org does not disclose personally-identifying information other than as described below. And visitors can always refuse to supply personally-identifying information, with the caveat that it may prevent them from engaging in certain website-related activities, like purchasing a WordCamp ticket.', 'wporg' ); ?></p>
					<p><?php esc_html_e( 'All of the information that is collected on WordPress.org will be handled in accordance with GDPR legislation.', 'wporg' ); ?></p>

					<h2><?php esc_html_e( 'Protection of Certain Personally-Identifying Information', 'wporg' ); ?></h2>
					<p><?php esc_html_e( 'WordPress.org discloses potentially personally-identifying and personally-identifying information only to those of project administrators, employees, contractors, and affiliated organizations that (i) need to know that information in order to process it on WordPress.org&#8217;s behalf or to provide services available through WordPress.org, and (ii) that have agreed not to disclose it to others. Some of those employees, contractors and affiliated organizations may be located outside of your home country; by using WordPress.org, you consent to the transfer of such information to them.', 'wporg' ); ?></p>
					<p><?php esc_html_e( 'WordPress.org will not rent or sell potentially personally-identifying and personally-identifying information to anyone. Other than to project administrators, employees, contractors, and affiliated organizations, as described above, WordPress.org discloses potentially personally-identifying and personally-identifying information only when required to do so by law, if you give permission to have your information shared, or when WordPress.org believes in good faith that disclosure is reasonably necessary to protect the property or rights of WordPress.org, third parties, or the public at large.', 'wporg' ); ?></p>
					<p><?php esc_html_e( 'If you are a registered user of a WordPress.org website and have supplied your email address, WordPress.org may occasionally send you an email to tell you about new features, solicit your feedback, or just keep you up to date with what&#8217;s going on with WordPress.org and our products. We primarily use our blog to communicate this type of information, so we expect to keep this type of email to a minimum.', 'wporg' ); ?></p>
					<p><?php esc_html_e( 'If you send us a request (for example via a support email or via one of our feedback mechanisms), we reserve the right to publish it in order to help us clarify or respond to your request or to help us support other users. WordPress.org takes all measures reasonably necessary to protect against the unauthorized access, use, alteration, or destruction of potentially personally-identifying and personally-identifying information.', 'wporg' ); ?></p>

					<h2><?php esc_html_e( 'Use of personal information', 'wporg' ); ?></h2>
					<p><?php esc_html_e( 'We will not use the information you provide when you register for an account, attend our events, receive newsletters, use certain other services, or participate in the WordPress open source project in any other way.', 'wporg' ); ?></p>
					<p><?php esc_html_e( 'We will not sell or lease your personal information to third parties unless we have your permission or are required by law to do so.', 'wporg' ); ?></p>
					<p><?php esc_html_e( 'We would like to send you email marketing communication which may be of interest to you from time to time. If you have consented to marketing, you may opt out later.', 'wporg' ); ?></p>
					<p><?php esc_html_e( 'You have a right at any time to stop us from contacting you for marketing purposes.  If you no longer wish to be contacted for marketing purposes, please click on the unsubscribe link at the bottom of the email.', 'wporg' ); ?></p>

					<h2><?php esc_html_e( 'Legal grounds for processing personal information', 'wporg' ); ?></h2>
					<p><?php esc_html_e( 'We rely on one or more of the following processing conditions:', 'wporg' ); ?></p>
					<ul>
						<li><?php esc_html_e( 'our legitimate interests in the effective delivery of information and services to you;', 'wporg' ); ?></li>
						<li><?php esc_html_e( 'explicit consent that you have given;', 'wporg' ); ?></li>
						<li><?php esc_html_e( 'legal obligations.', 'wporg' ); ?></li>
					</ul>

					<h2><?php esc_html_e( 'Access to data', 'wporg' ); ?></h2>
					<p><?php esc_html_e( 'You have the right to request a copy of the information we hold about you. If you would like a copy of some or all your personal information, please follow the instructions at the end of this section.', 'wporg' ); ?></p>
					<p><?php esc_html_e( 'All WordCamp attendee-provided data can be viewed and changed by the attendee via the Access Token URL that is emailed to confirm a successful ticket purchase.', 'wporg' ); ?></p>
					<p><?php esc_html_e( 'WordPress.org user accounts can be edited by following these steps:', 'wporg' ); ?></p>
					<ol>
						<li>
							<?php
							printf(
								/* translators: 1: Login URL */
								wp_kses_post( __( 'Visit <a href="%1$s">%1$s</a>, and enter your username and password.', 'wporg' ) ),
								'https://login.wordpress.org/'
							);
							?>
						</li>
						<li><?php esc_html_e( 'You will be redirected to https://profiles.wordpress.org/your_username.', 'wporg' ); ?></li>
						<li><?php esc_html_e( 'Click the &#8220;Edit&#8221; link next to your username.', 'wporg' ); ?></li>
					</ol>
					<p><?php esc_html_e( 'If you would like to request access to your account data, please follow these steps:', 'wporg' ); ?></p>
					<ol>
						<li>
							<?php
							printf(
								/* translators: 1: Data export request URL */
								wp_kses_post( __( 'Visit <a href="%1$s">%1$s</a>.', 'wporg' ) ),
								'https://wordpress.org/about/privacy/data-export-request/'
							);
							?>
						</li>
						<li><?php esc_html_e( 'Enter your email address.', 'wporg' ); ?></li>
						<li><?php esc_html_e( 'Click &#8220;Accept Declaration and Request Export&#8221;.', 'wporg' ); ?></li>
					</ol>
					<p><?php esc_html_e( 'Note: If you have a WP.org account, it&#8217;s recommended you log in before submitting to associate your account with the request.', 'wporg' ); ?></p>

					<h2><?php esc_html_e( 'Retention of personal information', 'wporg' ); ?></h2>
					<p><?php esc_html_e( 'We will retain your personal information on our systems only for as long as we need to, for the success of the WordPress open source project and the programs that support WordPress.org. We keep contact information (such as mailing list information) until a user unsubscribes or requests that we delete that information from our live systems. If you choose to unsubscribe from a mailing list, we may keep certain limited information about you so that we may honor your request.', 'wporg' ); ?></p>
					<p><?php esc_html_e( 'WordPress.org will not delete personal data from logs or records necessary to the operation, development, or archives of the WordPress open source project.', 'wporg' ); ?></p>
					<p><?php esc_html_e( 'WordPress.org shall maintain WordCamp attendee data for 3 years to better track and foster community growth, and then automatically delete non-essential data collected via registration. Attendee names and email addresses will be retained indefinitely, to preserve our ability to respond to code of conduct reports.', 'wporg' ); ?></p>
					<p><?php esc_html_e( 'On WordCamp.org sites, banking/financial data collected as part of a reimbursement request is deleted from WordCamp.org 7 days after the request is marked paid. The reason for the 7-day retention period is to prevent organizers having to re-enter their banking details if a wire fails or if a payment was marked &#8220;Paid&#8221; in error. Invoices and receipts related to WordCamp expenses are retained for 7 years after the close of the calendar year&#8217;s audit, by instruction of our financial consultants (auditors & bookkeepers).', 'wporg' ); ?></p>
					<p><?php esc_html_e( 'When deletion is requested or otherwise required, we will anonymise the data of data subjects and/or remove their information from publicly accessible sites if the deletion of data would break essential systems or damage the logs or records necessary to the operation, development, or archival records of the WordPress open source project.', 'wporg' ); ?></p>
					<p><?php esc_html_e( 'If you would like to request deletion of your account and associated data, please follow these steps:', 'wporg' ); ?></p>
					<ol>
						<li>
							<?php
							printf(
								/* translators: 1: Data erasure request URL */
								wp_kses_post( __( 'Visit <a href="%1$s">%1$s</a>.', 'wporg' ) ),
								'https://wordpress.org/about/privacy/data-erasure-request/'
							);
							?>
						</li>
						<li><?php esc_html_e( 'Enter your email address.', 'wporg' ); ?></li>
						<li><?php esc_html_e( 'Click &#8220;Accept Declaration and Request Permanent Account Deletion&#8221;.', 'wporg' ); ?></li>
					</ol>
					<p><?php esc_html_e( 'Note: If you have a WP.org account, it&#8217;s recommended you log in before submitting to associate your account with the request.', 'wporg' ); ?></p>

					<h2><?php esc_html_e( 'Rights in relation to your information', 'wporg' ); ?></h2>
					<p><?php esc_html_e( 'You may have certain rights under data protection law in relation to the personal information we hold about you. In particular, you may have a right to:', 'wporg' ); ?></p>
					<ul>
						<li><?php esc_html_e( 'request a copy of personal information we hold about you;', 'wporg' ); ?></li>
						<li><?php esc_html_e( 'ask that we update the personal information we hold about you, or independently correct such personal information that you think is incorrect or incomplete;', 'wporg' ); ?></li>
						<li><?php esc_html_e( 'ask that we delete personal information that we hold about you from live systems, or restrict the way in which we use such personal information (for information on deletion from archives, see the &#8220;Retention of personal information&#8221; section);', 'wporg' ); ?></li>
						<li><?php esc_html_e( 'object to our processing of your personal information; and/or', 'wporg' ); ?></li>
						<li><?php esc_html_e( 'withdraw your consent to our processing of your personal information (to the extent such processing is based on consent and consent is the only permissible basis for processing).', 'wporg' ); ?></li>
					</ul>
					<p><?php esc_html_e( 'If you would like to exercise these rights or understand if these rights apply to you, please follow the instructions at the end of this Privacy statement.', 'wporg' ); ?></p>

					<h2><?php esc_html_e( 'Third Party Links', 'wporg' ); ?></h2>
					<p><?php esc_html_e( 'Our website may contain links to other websites provided by third parties not under our control. When following a link and providing information to a 3rd-party website, please be aware that we are not responsible for the data provided to that third party.  This privacy policy only applies to the websites listed at the beginning of this document, so when you visit other websites, even when you click on a link posted on WordPress.org, you should read their own privacy policies.', 'wporg' ); ?></p>

					<h2><?php esc_html_e( 'Aggregated Statistics', 'wporg' ); ?></h2>
					<p><?php echo wp_kses_post( __( 'WordPress.org may collect statistics about the behavior of visitors to its websites. For instance, WordPress.org may reveal how many times a particular version of WordPress was downloaded or report on which plugins are the most popular, based on data gathered by <code>api.wordpress.org</code>, a web service used by WordPress installations to check for new versions of WordPress and plugins. However, WordPress.org does not disclose personally-identifying information other than as described in this policy.', 'wporg' ) ); ?></p>

					<h2><?php esc_html_e( 'Cookies', 'wporg' ); ?></h2>
					<p><?php esc_html_e( 'Additionally, information about how you use our website is collected automatically using &#8220;cookies&#8221;. Cookies are text files placed on your computer to collect standard internet log information and visitor behaviour information. This information is used to track visitor use of the website and to compile statistical reports on website activity.', 'wporg' ); ?></p>
					<p><?php echo wp_kses_post( sprintf(
							/* translators: %s: Link to the Cookie policy. */
							__( 'Please see <a href="%s">our cookie policy</a> for more information about what cookies are collected on WordPress.org.', 'wporg' ),
							home_url( '/about/privacy/cookies/' )
						) );
					?></p>

					<h2><?php esc_html_e( 'Privacy Policy Changes', 'wporg' ); ?></h2>
					<p><?php esc_html_e( 'Although most changes are likely to be minor, WordPress.org may change its Privacy Policy from time to time, and at WordPress.org&#8217;s sole discretion. WordPress.org encourages visitors to frequently check this page for any changes to its Privacy Policy. Your continued use of this site after any change in this Privacy Policy will constitute your acceptance of such change.', 'wporg' ); ?></p>

					<h2><?php esc_html_e( 'Contact', 'wporg' ); ?></h2>
					<p><?php printf( esc_html__( 'Please contact us if you have any questions about our privacy policy or information we hold about you by emailing %s.', 'wporg' ), 'dpo@wordpress.org' ); ?></p>

					<p><a rel="license" href="https://creativecommons.org/licenses/by-sa/4.0/"><img alt="Creative Commons License" src="https://s.w.org/images/home/ccbysa40.png"></a></p>
				</section>
			</div><!-- .entry-content -->

		</article><!-- #post-## -->

	</main><!-- #main -->

<?php
get_footer();
