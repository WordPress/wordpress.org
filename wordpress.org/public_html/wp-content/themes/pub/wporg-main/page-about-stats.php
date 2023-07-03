<?php
/**
 * Template Name: About -> Stats
 *
 * Page template for displaying the Stats page.
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
				<?php the_title( '<h1 class="entry-title col-8">', '</h1>' ); ?>
			</header><!-- .entry-header -->

			<div class="entry-content row">
				<div class="col-8">
					<p><?php esc_html_e( 'Here are some charts showing what sorts of systems people are running WordPress on. (You&#8217;ll need JavaScript enabled to see them.)', 'wporg' ); ?></p>
					<section>
						<h2><?php esc_html_e( 'WordPress Version', 'wporg' ); ?> <a class="swap-table dashicons dashicons-editor-table" title="<?php esc_attr_e( 'View as Table', 'wporg' ); ?>" aria-hidden="true"></a></h2>
						<div id="wp_versions" class="wporg-stats-chart loading"></div>
					</section>
					<section>
						<h2><?php esc_html_e( 'PHP Versions', 'wporg' ); ?> <a class="swap-table dashicons dashicons-editor-table" title="<?php esc_attr_e( 'View as Table', 'wporg' ); ?>" aria-hidden="true"></a></h2>
						<div id="php_versions" class="wporg-stats-chart loading"></div>
					</section>
					<section>
						<h2><?php esc_html_e( 'Database Version', 'wporg' ); ?> <a class="swap-table dashicons dashicons-editor-table" title="<?php esc_attr_e( 'View as Table', 'wporg' ); ?>" aria-hidden="true"></a></h2>
						<div id="mysql_versions" class="wporg-stats-chart loading"></div>
					</section>
					<section>
						<h2><?php esc_html_e( 'Locales', 'wporg' ); ?> <a class="swap-table dashicons dashicons-editor-table" title="<?php esc_attr_e( 'View as Table', 'wporg' ); ?>" aria-hidden="true"></a></h2>
						<div id="locales" class="wporg-stats-chart loading"></div>
					</section>
				</section>
			</div><!-- .entry-content -->
		</article><!-- #post-## -->

	</main><!-- #main -->

<?php
get_footer();
