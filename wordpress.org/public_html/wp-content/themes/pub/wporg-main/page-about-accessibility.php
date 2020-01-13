<?php
/**
 * Template Name: About -> Accessibility
 *
 * Page template for displaying the Accessibility page.
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
					<p><?php esc_html_e( 'The WordPress community and the open source WordPress project is committed to being as inclusive and accessible as possible. We want users, regardless of device or ability, to be able to publish content and maintain a website or application built with WordPress.', 'wporg' ); ?></p>
					<p><?php esc_html_e( 'WordPress aims to make the WordPress Admin and bundled themes fully WCAG 2.0 AA compliant where possible.', 'wporg' ); ?></p>
					<p>
					<?php
						printf(
							/* translators: 1: Link to the Core Handbook Accessibility Standards; 2: Link to Core Trac Accessibility focus. */
							wp_kses_post( __( 'All new and updated code released in WordPress must conform with these guidelines as per the <a href="%1$s">WordPress Accessibility Coding Standards</a>. Some current features and functionality in development may not yet fully comply, and known issues are listed in the <a href="%2$s">WordPress Trac &#8220;accessibility&#8221; focus</a>.', 'wporg' ) ),
							'https://developer.wordpress.org/coding-standards/wordpress-coding-standards/accessibility/',
							'https://core.trac.wordpress.org/focus/accessibility'
						);
					?>
					</p>

					<p>
					<?php
						printf(
							/* translators: 1: Link to the Theme Directory Accessible Themes; 2: Link to Accessibility Handbook requirements. */
							wp_kses_post( __( 'While the WordPress project cannot guarantee that all Themes are compliant, the <a href="%1$s">accessibility-ready themes</a> have been checked by the Theme Review Team to ensure that these themes pass their <a href="%2$s">basic accessibility requirements</a>.', 'wporg' ) ),
							esc_url( home_url( '/themes/tags/accessibility-ready/' ) ),
							'https://make.wordpress.org/themes/handbook/review/accessibility/required/'
						);
					?>
					</p>

					<h2><?php esc_html_e( 'The Accessibility Team', 'wporg' ); ?></h2>
					<p>
					<?php
						printf(
							/* translators: %s: Link to the Accessibility team P2 */
							wp_kses_post( __( 'The <a href="%s">WordPress Accessibility Team</a> provides accessibility expertise across the project to improve the accessibility of WordPress core and resources.', 'wporg' ) ),
							'https://make.wordpress.org/accessibility/'
						);
					?>
					</p>

					<p>
					<?php
						printf(
							/* translators: 1: Link to the Accessibility handbook; 2: Link to the Accessibility handbook's Best Practices; 3: Link to the Accessibility handbook's Useful Tools; 4: Link to the Accessibility handbook's Audits & Testing; 5: Link to the Accessibility handbook's Get Involved */
							wp_kses_post( __( 'The <a href="%1$s">Accessibility Handbook</a> shares the <a href="%2$s">best practices</a> for web accessibility, a list of <a href="%3$s">accessibility tools</a>, the <a href="%4$s">testing we do</a> to improve WordPress, themes, and plugins, and <a href="%5$s">how to get involved</a> in WordPress accessibility.', 'wporg' ) ),
							'https://make.wordpress.org/accessibility/handbook/',
							'https://make.wordpress.org/accessibility/handbook/best-practices/',
							'https://make.wordpress.org/accessibility/handbook/which-tools-can-i-use/useful-tools/',
							'https://make.wordpress.org/accessibility/handbook/get-involved/audits-and-testing/',
							'https://make.wordpress.org/accessibility/handbook/get-involved/'
						);
					?>
					</p>

					<p>
					<?php
						printf(
							/* translators: %s: Link to the Accessibility handbook's Reporting Issues */
							wp_kses_post( __( 'To report an Accessibility issue you’ve encountered in WordPress or on WordPress.org, please see the Accessibility Handbook page on <a href="%s">Reporting Accessibility Issues</a>.', 'wporg' ) ),
							'https://make.wordpress.org/accessibility/handbook/reporting-issues/'
						);
					?>
					</p>

					<h2>
						<?php
						/* translators: ATAG: Authoring Tool Accessibility Guidelines; https://www.w3.org/TR/ATAG20/ */
						esc_html_e( 'ATAG Statement', 'wporg' );
						?>
					</h2>
					<p>
						<?php
						printf(
							/* translators: Link to ATAG */
							wp_kses_post( __( 'The <a href="%s">Authoring Tool Accessibility Guidelines version 2.0</a>, or ATAG, are a set of guidelines that govern how to create a tool for creating web pages that are both accessible and encourages the creation of accessible content.', 'wporg' ) ),
							'https://www.w3.org/TR/ATAG20/'
						);
						?>
					</p>
					<p>
						<?php
						printf(
							/* translators: Link to ATAG */
							wp_kses_post( __( 'The <a href="%s">WordPress accessibility coding standards</a> require new or updated code to meet the standards of the Web Content Accessibility Guidelines (WCAG) version 2.0 at level AA. ATAG conformance is an additional goal beyond WCAG compliance.', 'wporg' ) ),
							'https://developer.wordpress.org/coding-standards/wordpress-coding-standards/accessibility/'
						);
						?>
					</p>
					<p><?php echo wp_kses_post( __( 'An application that fully conforms with ATAG 2.0 must not only be usable by people with disabilities, but it should <strong>enable</strong> and <strong>encourage</strong> all users to create accessible content and assist them in repairing accessibility mistakes, without requiring any additional tools or add-ons.', 'wporg' ) ); ?></p>
					<p><?php esc_html_e( 'WordPress is not currently conforming with ATAG 2.0, but pledges to pursue features that are accessible and which help users create accessible content in pursuit of the long-term goal of ATAG compliance.', 'wporg' ); ?></p>
				</section>
			</div><!-- .entry-content -->
		</article><!-- #post-## -->

	</main><!-- #main -->

<?php
get_footer();
