<?php
/**
 * Template Name: Swag
 *
 * Page template for displaying the Swag page.
 *
 * @package WordPressdotorg\MainTheme
 */

namespace WordPressdotorg\MainTheme;

if ( false === stristr( home_url(), 'test' ) ) {
	return get_template_part( 'page' );
}

$GLOBALS['menu_items'] = [
	'philosophy' => __( 'Philosophy', 'wporg' ),
	'etiquette'  => __( 'Etiquette', 'wporg' ),
	'swag'       => __( 'Swag', 'wporg' ),
	'logos'      => __( 'Graphics &amp; Logos', 'wporg' ),
];

// Prevent Jetpack from looking for a non-existent featured image.
add_filter( 'jetpack_images_pre_get_images', function() {
	return new \WP_Error();
} );

add_filter( 'jetpack_open_graph_tags', function( $tags ) {
	$tags['og:title']            = _esc_html__( 'WordPress Swag', 'wporg' );
	$tags['og:description']      = _esc_html__( 'Show your WordPress pride and run with the coolest swag! You&#8217;ll be surprised how widely recognized our logo is around the world, bringing people together through recognition and community. Choose your WordPress swag today (Wapuu t-shirt, anyone?) and your purchase will also support free swag at WordCamps and meetups.', 'wporg' );
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
				<h1 class="entry-title col-8"><?php _esc_html_e( 'Swag', 'wporg' ); ?></h1>
			</header><!-- .entry-header -->

			<div class="entry-content row">
				<section class="col-8">
					<p>
						<a class="alignright" href="https://mercantile.wordpress.org">
							<img width="132" height="177" src="https://s.w.org/images/home/swag_col-1.jpg?1" alt="<?php esc_attr_e( 'WordPress Swag', 'wporg' ); ?>" />
						</a>
						<?php
						/* translators: Link to swag store */
						printf( wp_kses_post( ___( 'Whether you&#8217;re a seasoned WordPress fanatic or just getting warmed up, wear your WordPress love with pride. The official <a href="%s">WordPress Swag Store</a> sells shirts and hoodies in a variety of designs and colors, printed on stock from socially responsible companies.', 'wporg' ) ), esc_url( 'https://mercantile.wordpress.org' ) );
						?>
					</p>
					<p><?php _esc_html_e( 'The swag store also rotates other products through the lineup on a regular basis.', 'wporg' ); ?></p>
					<p><?php _esc_html_e( 'The proceeds from these sales help offset the cost of providing free swag (buttons, stickers, etc.) to WordCamps and WordPress meetups around the world.', 'wporg' ); ?></p>
					<p>
						<?php
						/* translators: Link to swag store */
						printf( wp_kses_post( ___( 'So show the love and spread the word &mdash; get your <a href="%s">WordPress swag</a> today.', 'wporg' ) ), esc_url( 'https://mercantile.wordpress.org' ) );
						?>
					</p>

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
