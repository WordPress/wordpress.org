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
						printf(
							/* translators: %s: Link to Gutenberg demo page */
							wp_kses_post( __( 'WordPress is continually under development. Currently, work is underway on Phase 2 of the Gutenberg project. The Gutenberg project is a reimagination of the way we manage content on the web. Its goal is to broaden access to web presence, which is a foundation of successful modern businesses. Phase 1 was the new block editor, which was released in WordPress 5.0, you can <a href="%s">see that in action here</a>. Throughout 2020 there is a focus on full site editing as we continue to progress through Phase 2.', 'wporg' ) ),
							esc_url( home_url( '/gutenberg/' ) )
						);
						?>
					</p>

					<p>
						<?php
						printf(
							/* translators: %s: Link to make/core blog post */
							wp_kses_post( __( 'For 2020 the project also has the following 7 priorities, as outlined in <a href="%s">this post</a> by project executive director Josepha Haden:', 'wporg' ) ),
							'https://make.wordpress.org/core/2019/12/06/update-9-projects-for-2019/'
						);
						?>
					</p>

					<ul>
						<li><?php esc_html_e( 'Creating a block for navigation menus.', 'wporg' ); ?></li>
						<li><?php esc_html_e( 'Build a WordPress.org directory for discovering blocks, and a way to seamlessly install them.', 'wporg' ); ?></li>
						<li><?php esc_html_e( 'Provide a way for users to opt-in to automatic plugin and theme updates.', 'wporg' ); ?></li>
						<li><?php esc_html_e( 'Provide a way for themes to visually register content areas, and expose them in Gutenberg.', 'wporg' ); ?></li>
						<li><?php esc_html_e( 'Upgrade the widgets-editing areas and the Customizer to support blocks.', 'wporg' ); ?></li>
						<li><?php esc_html_e( 'Provide a way for users to opt-in to automatic updates of major Core releases.', 'wporg' ); ?></li>
						<li>
							<?php
							printf(
								/* translators: %s: Link to open tickets in Core Trac */
								wp_kses_post( __( 'Form a Triage team to tackle our <a href="%s">6,500 open issues on Trac</a>.', 'wporg' ) ),
								'https://core.trac.wordpress.org/query?status=!closed'
							);
							?>
						</li>
					</ul>

					<p>
						<?php
						printf(
							/* translators: %s: https://make.wordpress.org/ */
							wp_kses_post( __( 'Want to get involved? Head on over to <a href="%s">Make WordPress</a>! We can always use more people to help translate, design, document, develop and market WordPress.', 'wporg' ) ),
							'https://make.wordpress.org/'
						);
						?>
					</p>

					<h2><?php esc_html_e( 'Currently planned releases', 'wporg' ); ?></h2>

					<p>
						<?php
						printf(
							/* translators: %s: Link to Core Trac */
							wp_kses_post( __( 'Here are the current planned releases, and links to their respective milestones in our <a href="%s">issue tracker</a>. Any projected dates are for discussion and planning purposes, and will be firmed up as we get closer to release.', 'wporg' ) ),
							'https://core.trac.wordpress.org/'
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
								5.6
								<a href="https://core.trac.wordpress.org/tickets/major">(Trac)</a>
							</th>
							<td><?php echo date_i18n( 'F Y', strtotime( 'Dec 2020' ) ); ?></td>
						</tr>
						<tr>
							<th>5.7</th>
							<td><?php echo date_i18n( 'F Y', strtotime( 'Mar 2021' ) ); ?></td>
						</tr>
						<tr>
							<th>5.8</th>
							<td><?php echo date_i18n( 'F Y', strtotime( 'Jun 2021' ) ); ?></td>
						</tr>
						<tr>
							<th>5.9</th>
							<td><?php echo date_i18n( 'F Y', strtotime( 'Sep 2021' ) ); ?></td>
						</tr>
						<tr>
							<th>6.0</th>
							<td><?php echo date_i18n( 'F Y', strtotime( 'Dec 2021' ) ); ?></td>
						</tr>
						</tbody>
					</table>

					<p><?php
						printf(
							/* translators: %s: Link to Make WordPress Core blog post */
							wp_kses_post( __( 'For more information on the planned release schedule, please read the Make WordPress Core post about the <a href="%s">tentative release calendar for 2020-2021</a>.', 'wporg' ) ),
							'https://make.wordpress.org/core/2019/11/21/tentative-release-calendar-2020-2021/'
						);
						?>
					</p>

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

					<h2><?php esc_html_e( 'Long term roadmap', 'wporg' ); ?></h2>

					<p><?php
						printf(
							/* translators: %s: link to wordpress.tv State of the Word 2019 */
							wp_kses_post( __( 'While Phase 2 of Gutenberg is expected to continue at least through 2020, there are already plans for Phase 3 and 4. During the <a href="%s">State of the Word from WordCamp US 2019</a>, Matt shared the following vision for phases in Gutenberg:', 'wporg' ) ),
							'https://wordpress.tv/2019/11/03/2019-state-of-the-word/' );
						?>
					</p>

						<p><?php esc_html_e( 'The Four Phases of Gutenberg', 'wporg' ); ?></p>
						<ol>
							<li><?php esc_html_e( 'Easier Editing &mdash; Already available in WordPress, with ongoing improvements', 'wporg' ); ?></li>
							<li><?php esc_html_e( 'Customization &mdash; Full Site editing, Block Patterns, Block Directory, Block based themes', 'wporg' ); ?></li>
							<li><?php esc_html_e( 'Collaboration &mdash; A more intuitive way to co-author content', 'wporg' ); ?></li>
							<li><?php esc_html_e( 'Multi-lingual &mdash; Core implementation for Multi-lingual sites', 'wporg' ); ?></li>
						</ol>

				</section>

			</div><!-- .entry-content -->

		</article><!-- #post-## -->

	</main><!-- #main -->

<?php
get_footer();
