<?php
/**
 * Template Name: Domains
 *
 * Page template for displaying the Domains page.
 *
 * @package WordPressdotorg\MainTheme
 */

namespace WordPressdotorg\MainTheme;

if ( false === stristr( home_url(), 'test' ) ) {
	return get_template_part( 'page' );
}

$GLOBALS['menu_items'] = [
	'about/domains' => __( 'Domains', 'wporg' ),
	'about/license' => __( 'GNU Public License', 'wporg' ),
	'about/privacy' => __( 'Privacy Policy', 'wporg' ),
	'about/stats'   => __( 'Statistics', 'wporg' ),
];

// Prevent Jetpack from looking for a non-existent featured image.
add_filter( 'jetpack_images_pre_get_images', function() {
	return new \WP_Error();
} );

add_filter( 'jetpack_open_graph_tags', function( $tags ) {
	$tags['og:title']            = _esc_html__( 'WordPress Domains', 'wporg' );
	$tags['og:description']      = _esc_html__( 'WordPress domains and site names can be very flexible; however, top-level domains can&#8217;t use the word WordPress. Find out what is allowed and what constitutes a trademark violation, as well as policies on subdomain use. Review the list of official WordPress sites to know how to recognize and advise violators.', 'wporg' );
	$tags['twitter:text:title']  = $tags['og:title'];
	$tags['twitter:description'] = $tags['og:description'];

	return $tags;
} );

get_header( 'child-page' );
the_post();
?>

	<main id="main" class="site-main col-12" role="main">

		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<header class="entry-header">
				<h1 class="entry-title col-8"><?php _esc_html_e( 'Domains', 'wporg' ); ?></h1>
			</header><!-- .entry-header -->

			<div class="entry-content row">
				<section class="col-8">
					<p><?php echo wp_kses_post( ___( 'For various reasons related to our WordPress trademark, we ask if you&#8217;re going to start a site about WordPress or related to it that you <strong>not</strong> use &#8220;WordPress&#8221; in the domain name. Try using &#8220;wp&#8221; instead, or another variation. We&#8217;re not lawyers, but very good ones tell us we have to do this to preserve our trademark. Also, many users have told us they find it confusing.', 'wporg' ) ); ?></p>

					<p><?php _esc_html_e( 'If you already have a domain with &#8220;WordPress&#8221; in it, redirecting it to the &#8220;wp&#8221; equivalent is fine, just as long as the main one users see and you promote doesn&#8217;t contain &#8220;WordPress&#8221; and in the long term you should consider transferring the old one to the Foundation.', 'wporg' ); ?></p>

					<p>
						<?php
						echo wp_kses_post( ___( '&#8220;WordPress&#8221; in sub-domains is fine, like <code>wordpress.example.com</code>, we&#8217;re just concerned about top-level domains.', 'wporg' ) ); // phpcs:ignore WordPress.WP.CapitalPDangit.Misspelled
						?>
					</p>

					<p><?php _esc_html_e( 'We&#8217;ve told this to anyone who has ever asked us, we just wanted to make it public so more people could be aware of this policy.', 'wporg' ); ?></p>

					<p><strong><?php _esc_html_e( '&lt;whine&gt;Other domains are using WordPress in them!&lt;/whine&gt;', 'wporg' ); ?></strong></p>
					<p><?php _esc_html_e( 'If they&#8217;re not WordPress.com, WordPress.net, WordPress.org, WordPress.tv, or WordPressFoundation.org, they&#8217;re not allowed, and you should contact the owner with a pointer to this page. We see this most frequently with spammy sites distributing plugins and themes with malware in them, which you probably don&#8217;t want to be associated with.', 'wporg' ); ?></p>
				</section>
			</div><!-- .entry-content -->

			<?php
			edit_post_link(
				sprintf(
					/* translators: %s: Name of current post */
					esc_html__( 'Edit %s', 'wporg' ),
					the_title( '<span class="screen-reader-text">"', '"</span>', false )
				),
				'<footer class="entry-footer"><span class="edit-link">',
				'</span></footer><!-- .entry-footer -->'
			);
			?>
		</article><!-- #post-## -->

	</main><!-- #main -->

<?php
get_footer();
