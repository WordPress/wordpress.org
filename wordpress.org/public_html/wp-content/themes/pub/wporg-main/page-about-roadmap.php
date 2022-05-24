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
							/* translators: 1: Link to Gutenberg demo page, 2: Link to Feature Projects Overview page */
							wp_kses_post( __( 'WordPress is continually under development. Currently, work is underway on Phase 2 of the Gutenberg project. The Gutenberg project is a reimagination of the way we manage content on the web. Its goal is to broaden access to web presence, which is a foundation of successful modern businesses. Phase 1 was the new block editor, which was released in WordPress 5.0, you can <a href="%1$s">see that in action here</a>. In 2021 we’re focusing on merging full site editing (Phase 2) into WordPress which brings block editing to the entire site, not just posts and pages. For more information on full site editing, its components, and other active feature work, check out the <a href="%2$s">Feature Projects Overview</a> page.', 'wporg' ) ),
							esc_url( home_url( '/gutenberg/' ) ),
							'https://make.wordpress.org/core/features/'
						);
						?>
					</p>

					<p>
						<?php
						printf(
							/* translators: %s: Link to make/core blog post */
							wp_kses_post( __( 'For 2021 the project has some big picture goals, as outlined in <a href="%s">this post</a>:', 'wporg' ) ),
							'https://make.wordpress.org/updates/2021/01/21/big-picture-goals-2021/'
						);
						?>
					</p>

					<ul>
						<li><?php echo wp_kses_post( __( '<strong>Full site editing</strong>: Bring into the Gutenberg plugin, and subsequently WordPress Core, the ability to edit all elements of a site using Gutenberg blocks. This will include all in-progress features designed to help existing users transition to Gutenberg as well. Scope/Timeline: MVP in the plugin by April 2021, v1 in Core by WordPress 5.8.', 'wporg' ) ); ?></li>
						<li><?php echo wp_kses_post( __( '<strong>LearnWP</strong>: Enable WordPress skills-leveling by providing workshops, pre-recorded trainings, and self-serve learning opportunities on learn.wordpress.org. Scope/Timeline: regularly publish new workshops and lesson plans, maintain a high pass rate on workshop quizzes to establish learner success and comprehension.', 'wporg' ) ); ?></li>
						<li><?php echo wp_kses_post( __( '<strong>Contributor tools</strong>: Decrease the manual overhead of maintenance work for teams through better tooling. Scope/Timeline: Varied, and pending additional testing.', 'wporg' ) ); ?></li>
					</ul>

					<p>
						<?php
						printf(
							/* translators: %s: https://make.wordpress.org/ */
							wp_kses_post( __( 'Want to get involved? Head on over to <a href="%s">Make WordPress</a>! We can always use more people to help translate, design, document, develop, and market WordPress.', 'wporg' ) ),
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
								6.1
								<a href="https://core.trac.wordpress.org/tickets/major">(Trac)</a>
							</th>
							<td>2022</td>
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
							wp_kses_post( __( 'You can see an overview of past releases on our <a href="%s">history page</a>.', 'wporg' ) ),
							esc_url( home_url( '/about/history/' ) )
						);
						?>
					</p>

					<h2><?php esc_html_e( 'Long term roadmap', 'wporg' ); ?></h2>

					<p><?php esc_html_e( 'Phase 2 of Gutenberg won’t be finished when it’s merged into WordPress. The work to gather feedback and iterate based on user needs will continue after WordPress 5.8 is released. As a reminder, these are the four phases outlined in the Gutenberg project:', 'wporg' ); ?></p>

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
