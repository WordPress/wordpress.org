<?php
/**
 * Template Name: About -> Domains
 *
 * Page template for displaying the Domains page.
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

/* See inc/page-meta-descriptions.php for the meta description for this page.*/

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
					<p><?php echo wp_kses_post( __( 'For various reasons related to our WordPress trademark, we ask if you&#8217;re going to start a site about WordPress or related to it that you <strong>not</strong> use &#8220;WordPress&#8221; in the domain name. Try using &#8220;wp&#8221; instead, or another variation. We&#8217;re not lawyers, but very good ones tell us we have to do this to preserve our trademark. Also, many users have told us they find it confusing.', 'wporg' ) ); ?></p>

					<p><?php esc_html_e( 'If you already have a domain with &#8220;WordPress&#8221; in it, redirecting it to the &#8220;wp&#8221; equivalent is fine, just as long as the main one users see and you promote doesn&#8217;t contain &#8220;WordPress&#8221; and in the long term you should consider transferring the old one to the Foundation.', 'wporg' ); ?></p>

					<p>
						<?php
						echo wp_kses_post( __( '&#8220;WordPress&#8221; in sub-domains is fine, like <code>wordpress.example.com</code>, we&#8217;re just concerned about top-level domains.', 'wporg' ) ); // phpcs:ignore WordPress.WP.CapitalPDangit.Misspelled
						?>
					</p>

					<p><?php esc_html_e( 'We&#8217;ve told this to anyone who has ever asked us, we just wanted to make it public so more people could be aware of this policy.', 'wporg' ); ?></p>

					<p><strong><?php esc_html_e( '&lt;whine&gt;Other domains are using WordPress in them!&lt;/whine&gt;', 'wporg' ); ?></strong></p>
					<p><?php esc_html_e( 'If they&#8217;re not WordPress.com, WordPress.net, WordPress.org, WordPress.tv, or WordPressFoundation.org, they&#8217;re not allowed, and you should contact the owner with a pointer to this page. We see this most frequently with spammy sites distributing plugins and themes with malware in them, which you probably don&#8217;t want to be associated with.', 'wporg' ); ?></p>
				</section>
			</div><!-- .entry-content -->
		</article><!-- #post-## -->

	</main><!-- #main -->

<?php
get_footer();
