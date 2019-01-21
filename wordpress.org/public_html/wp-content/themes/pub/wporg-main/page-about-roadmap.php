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
						printf(
							/* translators: %s: Link to Gutenberg demo page */
							wp_kses_post( __( 'WordPress is continually under development. Currently, work is underway on Phase 2 of the Gutenberg project. The Gutenberg project is a reimagination of the way we manage content on the web. Its goal is to broaden access to web presence, which is a foundation of successful modern businesses. Phase 1 was the new block editor, which was released in WordPress 5.0, you can <a href="%s">see that in action here</a>. In 2019 we’re focusing on Phase 2 which brings block editing to the entire site, not just posts and pages.', 'wporg' ) ),
							esc_url( home_url( '/gutenberg/' ) )
						);
						?>
					</p>

					<p>
						<?php
						printf(
							/* translators: %s: Link to make/core blog post */
							wp_kses_post( __( 'For 2019 the project also has the following 9 priorities, as outlined in <a href="%s">this post</a> by project lead Matt Mullenweg:', 'wporg' ) ),
							esc_url( 'https://make.wordpress.org/core/2018/12/08/9-priorities-for-2019/' )
						);
						?>
					</p>

					<ul>
						<li><?php esc_html_e( 'Creating a block for navigation menus.', 'wporg' ); ?></li>
						<li><?php esc_html_e( 'Port all existing widgets to blocks.', 'wporg' ); ?></li>
						<li><?php esc_html_e( 'Upgrade the widgets-editing areas and the Customizer to support blocks.', 'wporg' ); ?></li>
						<li><?php esc_html_e( 'Provide a way for themes to visually register content areas, and expose them in Gutenberg.', 'wporg' ); ?></li>
						<li>
							<?php
							printf(
								/* translators: %s: Link to Health Check plugin */
								wp_kses_post( __( 'Merge <a href="%s">the site health check plugin</a> into Core, to assist with debugging and encourage good software hygiene.', 'wporg' ) ),
								esc_url( home_url( '/plugins/health-check/' ) )
							);
							?>
						</li>
						<li><?php esc_html_e( 'Provide a way for users to opt-in to automatic plugin and theme updates.', 'wporg' ); ?></li>
						<li><?php esc_html_e( 'Provide a way for users to opt-in to automatic updates of major Core releases.', 'wporg' ); ?></li>
						<li><?php esc_html_e( 'Build a WordPress.org directory for discovering blocks, and a way to seamlessly install them.', 'wporg' ); ?></li>
						<li>
							<?php
							printf(
								/* translators: %s: Link to open tickets in Core Trac */
								wp_kses_post( __( 'Form a Triage team to tackle our <a href="%s">6,500 open issues on Trac</a>.', 'wporg' ) ),
								esc_url( 'https://core.trac.wordpress.org/query?status=!closed' )
							);
							?>
						</li>
					</ul>

					<p>
						<?php
						printf(
							/* translators: %s: https://make.wordpress.org/ */
							wp_kses_post( __( 'Want to get involved? Head on over to <a href="%s">Make WordPress</a>! We can always use more people to help translate, design, document, develop and market WordPress.', 'wporg' ) ),
							esc_url( 'https://make.wordpress.org/' )
						);
						?>
					</p>

					<h3><?php esc_html_e( 'Currently planned releases', 'wporg' ); ?></h3>

					<p>
						<?php
						printf(
							/* translators: %s: Link to Core Trac */
							wp_kses_post( __( 'Here are the current planned releases, and links to their respective milestones in our <a href="%s">issue tracker</a>. Any projected dates are for discussion and planning purposes, and will be firmed up as we get closer to release.', 'wporg' ) ),
							esc_url( 'https://core.trac.wordpress.org/' )
						);
						?>
					</p>
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
							<td><?php echo date_i18n( 'F Y', strtotime( 'Feb 2019' ) ); ?></td>
						</tr>
						<tr>
							<th>
								5.2
								<a href="https://core.trac.wordpress.org/milestone/5.2">(Trac)</a>
							</th>
							<td>2019</td>
						</tr>
						</tbody>
					</table>

					<p><?php esc_html_e( 'The month prior to a release new features are frozen and the focus is entirely on ensuring the quality of the release by eliminating bugs and profiling the code for any performance issues.', 'wporg' ); ?></p>

					<p>
						<?php
						printf(
							/* translators: %s: Link to History page */
							wp_kses_post( __( 'You can see an overview of previous releases on our <a href="%s">history page</a>.', 'wporg' ) ),
							esc_url( home_url( '/about/history/' ) )
						);
						?>
					</p>

					<h3><?php esc_html_e( 'Long term roadmap', 'wporg' ); ?></h3>

					<p><?php esc_html_e( 'While we expect to need most or all of 2019 to finish phase 2 of Gutenberg, there are already plans for Phase 3 and 4. Phase 3 will focus on collaboration and multi-user editing. Phase 4 will contain support for multilingual sites.', 'wporg' ); ?></p>
				</section>
			</div><!-- .entry-content -->

		</article><!-- #post-## -->

	</main><!-- #main -->

<?php
get_footer();
