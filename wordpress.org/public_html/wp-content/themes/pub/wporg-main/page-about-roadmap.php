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
							wp_kses_post( __( 'WordPress is continually under development. Currently, work is underway on Phase 2 of the Gutenberg project. The Gutenberg project is a reimagination of the way we manage content on the web. Its goal is to broaden access to web presence, which is a foundation of successful modern businesses. Phase 1 was the new block editor, which was released in WordPress 5.0, you can <a href="%1$s">see that in action here</a>. In 2021 we were focusing on merging full site editing (Phase 2) into WordPress which brings block editing to the entire site, not just posts and pages. For more information on full site editing, its components, and other active feature work, check out the <a href="%2$s">Feature Projects Overview</a> page.', 'wporg' ) ),
							esc_url( home_url( '/gutenberg/' ) ),
							'https://make.wordpress.org/core/features/'
						);
						?>
					</p>

					<p>
						<?php
						printf(
							/* translators: %s: Link to make/core blog post */
							wp_kses_post( __( 'For 2022 the project has some big picture goals, as outlined in <a href="%s">this post</a>:', 'wporg' ) ),
							'https://make.wordpress.org/project/2022/01/21/big-picture-goals-2022/'
						);
						?>
					</p>

					<ol>
						<li><?php echo wp_kses_post( __( '<strong>Drive adoption of the new WordPress editor</strong> – Following WordPress 5.9, our focus will be driving user adoption by making full site editing (and its tools) easy to find and use.', 'wporg' ) ); ?>
							<ol>
								<li><?php echo wp_kses_post( __( '<strong>For the CMS</strong> – Get high quality feedback, ensure actionable tickets come from the feedback with collaboration from design as needed, and ship code that solves our users’ most pressing needs.', 'wporg' ) ); ?>
									<ol>
										<li><?php esc_html_e( 'Invite more users and extenders to participate in the FSE Outreach program (10–12 calls for testing).', 'wporg' ); ?></li>
										<li><?php esc_html_e( 'Host regular design-driven user testing (one test a week).', 'wporg' ); ?></li>
									</ol>
								</li>
								<li><?php echo wp_kses_post( __( '<strong>For the Community</strong> – Share our knowledge and resources in a way that inspires and motivates our users to action.', 'wporg' ) ); ?>
									<ol>
										<li><?php esc_html_e( 'Invite more users and extenders to augment their skills through LearnWP.', 'wporg' ); ?></li>
										<li><?php esc_html_e( 'Turn routine support issues into new evergreen content (10–15 pieces of canonical content using Learn, Docs, WordPress.org, etc).', 'wporg' ); ?></li>
										<li><?php esc_html_e( 'Translate high impact user-facing content across Rosetta sites (15–20 locales).', 'wporg' ); ?></li>
										<li><?php esc_html_e( 'Host audience-specific WordPress events (10–12 by common language, interest, or profession).', 'wporg' ); ?></li>
									</ol>
								</li>
								<li><?php echo wp_kses_post( __( '<strong>For the Ecosystem</strong> – Prioritize full site editing tools and content across the ecosystem for all users.', 'wporg' ) ); ?>
									<ol>
										<li><?php esc_html_e( 'Highlight block themes and plugins in the directories.', 'wporg' ); ?></li>
										<li><?php esc_html_e( 'Provide tools/training to learn how to build block themes.', 'wporg' ); ?></li>
										<li><?php esc_html_e( 'Improve the block developer experience.', 'wporg' ); ?></li>
									</ol>
								</li>
							</ol>
						</li>
						<li><?php echo wp_kses_post( __( '<strong>Support open source alternatives for all site-building necessities</strong> – Provide access to open source elements needed to get a site up and running.', 'wporg' ) ); ?>
							<ol>
								<li><?php echo wp_kses_post( __( '<strong>For the CMS</strong>', 'wporg' ) ); ?>
									<ol>
										<li><?php esc_html_e( 'Update new user onboarding flow to match modern standards.', 'wporg' ); ?></li>
										<li><?php esc_html_e( 'Integrate Openverse into wp-admin.', 'wporg' ); ?></li>
										<li><?php esc_html_e( 'Integrate Photo Directory submissions into wp-admin.', 'wporg' ); ?></li>
										<li><?php esc_html_e( 'Pattern creator', 'wporg' ); ?></li>
									</ol>
								</li>
								<li><?php echo wp_kses_post( __( '<strong>For the Community</strong>', 'wporg' ) ); ?>
									<ol>
										<li><?php esc_html_e( 'Ship LearnWP learning opportunities (1 workshop/week, 6 courses/year)', 'wporg' ); ?></li>
										<li><?php esc_html_e( 'Increase the number of social learning spaces (4 SLSs/week)', 'wporg' ); ?></li>
										<li><?php esc_html_e( 'Block theme contribution drive (500 block themes in the repo).', 'wporg' ); ?></li>
									</ol>
								</li>
								<li><?php echo wp_kses_post( __( '<strong>For the Ecosystem</strong>', 'wporg' ) ); ?>
									<ol>
										<li><?php esc_html_e( 'Update the theme previewer to support block themes.', 'wporg' ); ?></li>
										<li><?php esc_html_e( 'Update the content &amp; design across WP.org.', 'wporg' ); ?></li>
										<li><?php esc_html_e( 'Update Polyglots tools to improve the translation experience.', 'wporg' ); ?></li>
										<li><?php esc_html_e( 'Create a developer-focused communications site.', 'wporg' ); ?></li>
									</ol>
								</li>
							</ol>
						</li>
						<li><?php echo wp_kses_post( __( '<strong>Open Source stewards</strong>: Iterate on WordPress’ open source methodologies to guide and sustain long term success for WordPress as well as the overall open source community that we are part of.', 'wporg' ) ); ?>
							<ol>
								<li><?php esc_html_e( 'For All', 'wporg' ); ?>
									<ol>
										<li><?php esc_html_e( '5ftF program expansion', 'wporg' ); ?></li>
										<li><?php esc_html_e( 'Recruitment of future leaders in the community', 'wporg' ); ?></li>
										<li><?php esc_html_e( 'Onboarding of current leaders in the community', 'wporg' ); ?></li>
										<li><?php esc_html_e( 'Upstream contributions to other OS projects (PHP, JS, Matrix, or the like)', 'wporg' ); ?></li>
										<li><?php esc_html_e( 'WordPress Project maintenance', 'wporg' ); ?></li>
										<li><?php esc_html_e( 'Ancillary programs', 'wporg' ); ?></li>
									</ol>
								</li>
							</ol>
						</li>
						<li><?php echo wp_kses_post( __( '<strong>Bonus</strong>: Preparations for WordPress’ 20th birthday', 'wporg' ) ); ?></li>
					</ol>

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
								6.2
								<a href="https://core.trac.wordpress.org/tickets/major">(Trac)</a>
							</th>
							<td>2023</td>
						</tr>
						</tbody>
					</table>

					<p><?php
						printf(
							/* translators: %s: Link to Make WordPress Core blog post */
							wp_kses_post( __( 'For more information on the planned release schedule, please read the Make WordPress Core post about the <a href="%s">proposed major release timing for 2022</a>.', 'wporg' ) ),
							'https://make.wordpress.org/core/2022/01/27/proposal-2022-major-release-timing/'
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
