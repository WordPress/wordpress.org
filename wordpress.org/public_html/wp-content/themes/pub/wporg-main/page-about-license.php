<?php
/**
 * Template Name: About -> License
 *
 * Page template for displaying the License page.
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
					<p>
						<?php
						/* translators: 1: Link to Free Software Foundation; 2: Link to GPL text */
						printf( wp_kses_post( __( 'The license under which the WordPress software is released is the GPLv2 (or later) from the <a href="%1$s">Free Software Foundation</a>. A copy of the license is included with every copy of WordPress, but you can also <a href="%2$s">read the text of the license here</a>.', 'wporg' ) ), esc_url( 'https://www.fsf.org/' ), esc_url( 'https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html' ) );
						?>
					</p>

					<p>
						<?php
						/* translators: 1: Link to Drupal; 2: Link to Drupal's licensing faq */
						printf( wp_kses_post( __( 'Part of this license outlines requirements for derivative works, such as plugins or themes. Derivatives of WordPress code inherit the GPL license. <a href="%1$s">Drupal</a>, which has the same GPL license as WordPress, has an excellent page on <a href="%2$s">licensing as it applies to themes and modules</a> (their word for plugins).', 'wporg' ) ), esc_url( 'https://www.drupal.org/' ), esc_url( 'https://www.drupal.org/about/licensing' ) );
						?>
					</p>

					<p>
						<?php
						printf( wp_kses_post( __( 'There is some legal grey area regarding what is considered a derivative work, but we feel strongly that plugins and themes are derivative work and thus inherit the GPL license. If you disagree, you might want to consider a non-GPL platform such as <a href="%s">Serendipity</a> (BSD license) instead.', 'wporg' ) ), esc_url( 'https://docs.s9y.org/' )  );
						?>
					</p>
				</section>
			</div><!-- .entry-content -->

		</article><!-- #post-## -->

	</main><!-- #main -->

<?php
get_footer();
