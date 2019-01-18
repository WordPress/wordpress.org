<?php
/**
 * Template Name: About -> Roadmap
 *
 * Page template for displaying the Roadmap page.
 *
 * @package WordPressdotorg\MainTheme
 */

namespace WordPressdotorg\MainTheme;

$GLOBALS['menu_items'] = [
	'about/requirements' => _x( 'Requirements', 'Page title', 'wporg' ),
	'about/features'     => _x( 'Features', 'Page title', 'wporg' ),
	'about/security'     => _x( 'Security', 'Page title', 'wporg' ),
	'about/roadmap'      => _x( 'Roadmap', 'Page title', 'wporg' ),
	'about/history'      => _x( 'History', 'Page title', 'wporg' ),
];

// Prevent Jetpack from looking for a non-existent featured image.
add_filter( 'jetpack_images_pre_get_images', function() {
	return new \WP_Error();
} );

/* See inc/page-meta-descriptions.php for the meta description for this page. */

get_header( 'child-page' );
the_post();

$date_format = get_option( 'date_format' );
?>

	<main id="main" class="site-main col-12" role="main">

		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<header class="entry-header">
				<h1 class="entry-title col-8"><?php the_title(); ?></h1>
			</header><!-- .entry-header -->

			<div class="entry-content row">
				<section class="col-8">
					<p>
						<?php
						/* translators: 1: Link to Ideas forum; 2: Link to Core Trac */
						printf( wp_kses_post( __( 'After the 2.1 release, we decided to adopt a regular release schedule every 3-4 months with the features primarily driven by <a href="%1$s">ideas voted on by our users</a>. Here are the current planned releases, and links to their respective milestones in our <a href="%2$s">issue tracker</a>.', 'wporg' ) ), esc_url( 'https://wordpress.org/ideas/' ), esc_url( 'https://core.trac.wordpress.org/' ) );
						?>
					</p>
					<p><?php esc_html_e( 'Any projected dates are for discussion and planning purposes, and will be firmed up as we get closer to release.', 'wporg' ); ?></p>
					<table>
						<thead>
						<tr>
							<th><?php esc_html_e( 'Version', 'wporg' ); ?></th>
							<th><?php esc_html_e( 'Planned', 'wporg' ); ?></th>
						</tr>
						</thead>
						<tbody>
						<tr>
							<th>
								<a href="https://make.wordpress.org/core/5-1/">5.1</a>
								<a href="https://core.trac.wordpress.org/tickets/major">(Trac)</a>
							</th>
							<td>2019</td>
						</tr>
						</tbody>
					</table>

					<p><?php esc_html_e( 'The month prior to a release new features are frozen and the focus is entirely on ensuring the quality of the release by eliminating bugs and profiling the code for any performance issues.', 'wporg' ); ?></p>
				</section>
			</div><!-- .entry-content -->

		</article><!-- #post-## -->

	</main><!-- #main -->

<?php
get_footer();
