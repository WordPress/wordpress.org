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
				<h1 class="entry-title col-8"><?php esc_html_e( 'Accessibility', 'wporg' ); ?></h1>
			</header><!-- .entry-header -->

			<div class="entry-content row">
				<section class="col-8">

					<p>The WordPress community and the open source WordPress project is committed to being as inclusive and accessible as possible. We want users, regardless of device or ability, to be able to publish content and maintain a website or application built with WordPress.</p>

					<p>WordPress aims to make the WordPress Admin and bundled themes fully WCAG 2.0 AA compliant where possible.</p>
					<p>All new and updates code released in WordPress must conform with these guidelines as per the <a href="https://make.wordpress.org/core/handbook/best-practices/coding-standards/accessibility-coding-standards/">WordPress Accessibility Coding Standards</a>. Some current features and functionality in development may not yet fully comply, and known issues are listed in the <a href="https://core.trac.wordpress.org/focus/accessibility">WordPress Trac "accessibility” focus</a>.</p>

					<p>While the WordPress project cannot guarantee that all Themes are compliant, the <a href="https://wordpress.org/themes/tags/accessibility-ready/">accessibility-ready themes</a> have been checked by the Theme Review Team to ensure that these themes pass their <a href="https://make.wordpress.org/themes/handbook/review/accessibility/required/">basic accessibility requirements</a>.</p>

					<h3>The Accessibility Team</h3>
					<p>The <a href="https://make.wordpress.org/accessibility/">WordPress Accessibility Team</a> provides accessibility expertise across the project to improve the accessibility of WordPress core and resources.</p>

					<p>The <a href="https://make.wordpress.org/accessibility/handbook/">Accessibility Handbook</a> shares the <a href="https://make.wordpress.org/accessibility/handbook/best-practices/">best practices</a> for web accessibility, a list of <a href="https://make.wordpress.org/accessibility/handbook/testing-and-development-tools/">accessibility tools</a>, the <a href="https://make.wordpress.org/accessibility/handbook/get-involved/audits-and-testing/">testing we do</a> to improve WordPress, themes, and plugins, and <a href="https://make.wordpress.org/accessibility/handbook/get-involved/">how to get involved</a> in WordPress accessibility.</p>

					<p>To report an Accessibility issue you’ve encountered in WordPress or on WordPress.org, please see the Accessibility Handbook on <a href="https://make.wordpress.org/accessibility/handbook/reporting-issues/">Reporting Accessibility Issues</a>.</p>


				</section>
			</div><!-- .entry-content -->

		</article><!-- #post-## -->

	</main><!-- #main -->

<?php
get_footer();
