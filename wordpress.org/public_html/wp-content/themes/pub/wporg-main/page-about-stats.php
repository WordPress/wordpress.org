<?php
/**
 * Template Name: Stats
 *
 * Page template for displaying the Stats page.
 *
 * @package WordPressdotorg\MainTheme
 */

namespace WordPressdotorg\MainTheme;

if ( false === stristr( home_url(), 'test' ) && ! isset( $_GET['test'] ) ) {
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
			<header class="entry-header row">
				<h1 class="entry-title col-8"><?php esc_html_e( 'Statistics', 'wporg' ); ?></h1>
			</header><!-- .entry-header -->

			<div class="entry-content row">
				<section class="col-8">
					<p><?php esc_html_e( 'Here are some charts showing what sorts of systems people are running WordPress on. (You&#8217;ll need JavaScript enabled to see them.)', 'wporg' ); ?></p>
					<div id="wp_versions" class="wporg-stats-chart loading"></div>
					<div id="php_versions" class="wporg-stats-chart loading"></div>
					<div id="mysql_versions" class="wporg-stats-chart loading"></div>
					<div id="locales" class="wporg-stats-chart loading"></div>
				</section>
			</div><!-- .entry-content -->

		</article><!-- #post-## -->

	</main><!-- #main -->

<?php
get_footer();
