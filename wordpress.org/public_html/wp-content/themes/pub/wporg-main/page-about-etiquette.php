<?php
/**
 * Template Name: About -> Etiquette
 *
 * Page template for displaying the Etiquette page.
 *
 * @package WordPressdotorg\MainTheme
 */

namespace WordPressdotorg\MainTheme;

$GLOBALS['menu_items'] = [
	'about/philosophy'   => _x( 'Philosophy', 'Page title', 'wporg' ),
	'about/etiquette'    => _x( 'Etiquette', 'Page title', 'wporg' ),
	'about/swag'         => _x( 'Swag', 'Page title', 'wporg' ),
	'about/logos'        => _x( 'Graphics &amp; Logos', 'Page title', 'wporg' ),
	'about/testimonials' => _x( 'Testimonials', 'Page title', 'wporg' ),
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
			<header class="entry-header">
				<h1 class="entry-title col-8"><?php the_title(); ?></h1>
			</header><!-- .entry-header -->

			<div class="entry-content row">
				<section class="col-8">
					<p>
						<?php
						/* translators: Link to blog post */
						printf( wp_kses_post( __( 'In the WordPress open source project, we realize that our biggest asset is the community that we foster. The project, as a whole, follows these basic philosophical principles from <a href="%s">The Cathedral and The Bazaar</a>.', 'wporg' ) ), 'http://www.catb.org/esr/writings/cathedral-bazaar/cathedral-bazaar/index.html' );
						?>
					</p>

					<ul>
						<li><?php esc_html_e( 'Contributions to the WordPress open source project are for the benefit of the WordPress community as a whole, not specific businesses or individuals. All actions taken as a contributor should be made with the best interests of the community in mind.', 'wporg' ); ?></li>
						<li><?php esc_html_e( 'Participation in the WordPress open source project is open to all who wish to join, regardless of ability, skill, financial status, or any other criteria.', 'wporg' ); ?></li>
						<li><?php esc_html_e( 'The WordPress open source project is a volunteer-run community. Even in cases where contributors are sponsored by companies, that time is donated for the benefit of the entire open source community.', 'wporg' ); ?></li>
						<li>
							<?php
							/* translators: Link to make.wordpress.org */
							printf( wp_kses_post( __( 'Any member of the community can donate their time and contribute to the project in any form including design, code, documentation, community building, etc. For more information, go to <a href="%s">make.wordpress.org</a>.', 'wporg' ) ), 'https://make.wordpress.org/' );
							?>
						</li>
						<li><?php esc_html_e( 'The WordPress open source community cares about diversity. We strive to maintain a welcoming environment where everyone can feel included, by keeping communication free of discrimination, incitement to violence, promotion of hate, and unwelcoming behavior.', 'wporg' ); ?></li>
					</ul>

					<p><?php esc_html_e( 'There is a project currently underway to create a project-wide code of conduct so that we can ensure the safety of our contributors.', 'wporg' ); ?></p>

					<p>
						<?php
						/* translators: 1: Link to community team slack channel; 2: Link to tag archive on make/community */
						printf( wp_kses_post( __( 'Meetings are conducted in the <a href="%1$s">#community-team Slack channel</a>, and minutes published on the <a href="%2$s">Make Community blog</a>.', 'wporg' ) ), 'https://wordpress.slack.com/messages/community-team', 'https://make.wordpress.org/community/tag/ccoc/' );
						?>
					</p>
				</section>
			</div><!-- .entry-content -->
		</article><!-- #post-## -->

	</main><!-- #main -->

<?php
get_footer();
