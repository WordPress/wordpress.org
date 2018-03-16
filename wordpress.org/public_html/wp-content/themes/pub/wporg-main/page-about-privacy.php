<?php
/**
 * Template Name: Privacy
 *
 * Page template for displaying the Privacy page.
 *
 * @package WordPressdotorg\MainTheme
 */

namespace WordPressdotorg\MainTheme;

if ( false === stristr( home_url(), 'test' ) ) {
	return get_template_part( 'page' );
}

$GLOBALS['menu_items'] = [
	'about/domains'       => __( 'Domains', 'wporg' ),
	'about/license'       => __( 'GNU Public License', 'wporg' ),
	'about/accessibility' => __( 'Accessibility', 'wporg' ),
	'about/privacy'       => __( 'Privacy Policy', 'wporg' ),
	'about/stats'         => __( 'Statistics', 'wporg' ),
];

// Prevent Jetpack from looking for a non-existent featured image.
add_filter( 'jetpack_images_pre_get_images', function() {
	return new \WP_Error();
} );

// See inc/page-meta-descriptions.php for the meta description for this page.

get_header( 'child-page' );
the_post();
?>

	<main id="main" class="site-main col-12" role="main">

		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<header class="entry-header">
				<h1 class="entry-title col-8"><?php esc_html_e( 'Privacy', 'wporg' ); ?></h1>
			</header><!-- .entry-header -->

			<div class="entry-content row">
				<section class="col-8">
					<p><?php esc_html_e( 'WordPress.org websites (collectively &ldquo;WordPress.org&rdquo; in this document) refer to sites hosted on the WordPress.org, WordPress.net, WordCamp.org, BuddyPress.org, bbPress.org, and other related domains and subdomains thereof.', 'wporg' ); ?></p>

					<h3><?php esc_html_e( 'Website Visitors', 'wporg' ); ?></h3>
					<p><?php esc_html_e( 'Like most website operators, WordPress.org collects non-personally-identifying information of the sort that web browsers and servers typically make available, such as the browser type, language preference, referring site, and the date and time of each visitor request. WordPress.org&#8217;s purpose in collecting non-personally identifying information is to better understand how WordPress.org&#8217;s visitors use its website. From time to time, WordPress.org may release non-personally-identifying information in the aggregate, e.g., by publishing a report on trends in the usage of its website.', 'wporg' ); ?></p>

					<p><?php esc_html_e( 'WordPress.org also collects potentially personally-identifying information like Internet Protocol (IP) addresses. WordPress.org does not use such information to identify its visitors, however, and does not disclose such information, other than under the same circumstances that it uses and discloses personally-identifying information, as described below.', 'wporg' ); ?></p>

					<h3><?php esc_html_e( 'Gathering of Personally-Identifying Information', 'wporg' ); ?></h3>
					<p><?php esc_html_e( 'Certain visitors to WordPress.org choose to interact with WordPress.org in ways that require WordPress.org to gather personally-identifying information. The amount and type of information that WordPress.org gathers depends on the nature of the interaction. For example, we ask visitors who use our forums to provide a username and email address. In each case, WordPress.org collects such information only insofar as is necessary or appropriate to fulfill the purpose of the visitor&#8217;s interaction with WordPress.org. WordPress.org does not disclose personally-identifying information other than as described below. And visitors can always refuse to supply personally-identifying information, with the caveat that it may prevent them from engaging in certain website-related activities.', 'wporg' ); ?></p>

					<h3><?php esc_html_e( 'Aggregated Statistics', 'wporg' ); ?></h3>
					<p><?php echo wp_kses_post( __( 'WordPress.org may collect statistics about the behavior of visitors to its websites. For instance, WordPress.org may reveal how many downloads a particular version got, or say which plugins are most popular based on checks from <code>api.wordpress.org</code>, a web service used by WordPress installations to check for new versions of WordPress and plugins. However, WordPress.org does not disclose personally-identifying information other than as described below.', 'wporg' ) ); ?></p>

					<h3><?php esc_html_e( 'Protection of Certain Personally-Identifying Information', 'wporg' ); ?></h3>
					<p><?php esc_html_e( 'WordPress.org discloses potentially personally-identifying and personally-identifying information only to those of its employees, contractors, and affiliated organizations that (i) need to know that information in order to process it on WordPress.org&#8217;s behalf or to provide services available at WordPress.org, and (ii) that have agreed not to disclose it to others. Some of those employees, contractors and affiliated organizations may be located outside of your home country; by using WordPress.org, you consent to the transfer of such information to them. WordPress.org will not rent or sell potentially personally-identifying and personally-identifying information to anyone. Other than to its employees, contractors, and affiliated organizations, as described above, WordPress.org discloses potentially personally-identifying and personally-identifying information only when required to do so by law, or when WordPress.org believes in good faith that disclosure is reasonably necessary to protect the property or rights of WordPress.org, third parties, or the public at large. If you are a registered user of a WordPress.org website and have supplied your email address, WordPress.org may occasionally send you an email to tell you about new features, solicit your feedback, or just keep you up to date with what&#8217;s going on with WordPress.org and our products. We primarily use our blog to communicate this type of information, so we expect to keep this type of email to a minimum. If you send us a request (for example via a support email or via one of our feedback mechanisms), we reserve the right to publish it in order to help us clarify or respond to your request or to help us support other users. WordPress.org takes all measures reasonably necessary to protect against the unauthorized access, use, alteration, or destruction of potentially personally-identifying and personally-identifying information.', 'wporg' ); ?></p>

					<h3><?php esc_html_e( 'Cookies', 'wporg' ); ?></h3>
					<p><?php esc_html_e( 'A cookie is a string of information that a website stores on a visitor&#8217;s computer, and that the visitor&#8217;s browser provides to the website each time the visitor returns. WordPress.org uses cookies to help WordPress.org identify and track visitors, their usage of WordPress.org, and their website access preferences. WordPress.org visitors who do not wish to have cookies placed on their computers should set their browsers to refuse cookies before using WordPress.org&#8217;s websites, with the drawback that certain features of WordPress.org&#8217;s websites may not function properly without the aid of cookies.', 'wporg' ); ?></p>

					<h3><?php esc_html_e( 'Privacy Policy Changes', 'wporg' ); ?></h3>
					<p><?php esc_html_e( 'Although most changes are likely to be minor, WordPress.org may change its Privacy Policy from time to time, and in WordPress.org&#8217;s sole discretion. WordPress.org encourages visitors to frequently check this page for any changes to its Privacy Policy. Your continued use of this site after any change in this Privacy Policy will constitute your acceptance of such change.', 'wporg' ); ?></p>

					<p><a rel="license" href="https://creativecommons.org/licenses/by-sa/2.5/"><img alt="Creative Commons License" src="https://creativecommons.org/images/public/somerights20.png"></a></p>
				</section>
			</div><!-- .entry-content -->

		</article><!-- #post-## -->

	</main><!-- #main -->

<?php
get_footer();
