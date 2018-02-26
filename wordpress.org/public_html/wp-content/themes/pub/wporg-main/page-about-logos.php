<?php
/**
 * Template Name: Logos
 *
 * Page template for displaying the Logos and Graphics page.
 *
 * @package WordPressdotorg\MainTheme
 */

namespace WordPressdotorg\MainTheme;

if ( false === stristr( home_url(), 'test' ) ) {
	return get_template_part( 'page' );
}

// Prevent Jetpack from looking for a non-existent featured image.
add_filter( 'jetpack_images_pre_get_images', function() {
	return new \WP_Error();
} );

get_header();
the_post();
?>

	<main id="main" class="site-main col-12" role="main">

		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<header class="entry-header">
				<h1 class="entry-title"><?php _esc_html_e( 'Graphics &amp; Logos', 'wporg' ); ?></h1>
			</header><!-- .entry-header -->

			<div class="entry-content row">
				<section class="col-8">
					<h3 class="graphics">Official WordPress Logo</h3>
					<p>When you need the official WordPress logo for a web site or publication, please use one of the following. Please only use logos in accordance with the <a href="http://wordpressfoundation.org/trademark-policy/">WordPress trademark&nbsp;policy</a>.</p>

					<h3 class="graphics">Downloads</h3>

					<section class="all-logos col-12 row gutters">
						<div class="logo col-4" id="logo-all">
							<img src="//s.w.org/style/images/about/allversions.png" alt="<?php esc_attr_e( 'WordPress Logotypes - All Versions', 'wporg' ); ?>" />
							<div class="logo-header">
								<strong><?php _esc_html_e( 'WordPress Logotypes', 'wporg' ); ?></strong>
								<span><?php _esc_html_e( 'All versions', 'wporg' ); ?></span>
							</div>
							<ul class="resources-list">
								<li><a href="//s.w.org/style/images/about/WordPress-logotype-all.ai_.zip"><?php _esc_html_e( 'Adobe Illustrator CS5+ Sketch 3', 'wporg' ); ?></a></li>
							</ul>
						</div>

						<div class="logo col-4" id="logo-standard">
							<img src="//s.w.org/style/images/about/standard.png" alt="<?php esc_attr_e( 'WordPress Logotype - Standard', 'wporg' ); ?>" />
							<div class="logo-header">
								<strong><?php _esc_html_e( 'WordPress Logotype', 'wporg' ); ?></strong>
								<span><?php _esc_html_e( 'Standard', 'wporg' ); ?></span>
							</div>
							<ul class="resources-list">
								<li><a href="//s.w.org/style/images/about/WordPress-logotype-standard.pdf"><?php echo wp_kses_post( ___( 'PDF <span>(Vector)</span>', 'wporg' ) ); ?></a></li>
								<li><a href="//s.w.org/style/images/about/WordPress-logotype-standard.png"><?php echo wp_kses_post( ___( 'PNG <span>(BaseGray/transparent)</span>', 'wporg' ) ); ?></a></li>
								<li><a href="//s.w.org/style/images/about/WordPress-logotype-standard-white.png"><?php echo wp_kses_post( ___( 'PNG <span>(White/transparent)</span>', 'wporg' ) ); ?></a></li>
							</ul>
						</div>

						<div class="logo col-4" id="logo-alternative">
							<img src="//s.w.org/style/images/about/alternative.png" alt="<?php esc_attr_e( 'WordPress Logotype - Alternative', 'wporg' ); ?>" />
							<div class="logo-header">
								<strong><?php _esc_html_e( 'WordPress Logotype', 'wporg' ); ?></strong>
								<span><?php _esc_html_e( 'Alternative, vertical arrangement', 'wporg' ); ?></span>
							</div>
							<ul class="resources-list">
								<li><a href="//s.w.org/style/images/about/WordPress-logotype-alternative.pdf"><?php echo wp_kses_post( ___( 'PDF <span>(Vector)</span>', 'wporg' ) ); ?></a></li>
								<li><a href="//s.w.org/style/images/about/WordPress-logotype-alternative.png"><?php echo wp_kses_post( ___( 'PNG <span>(BaseGray/transparent)</span>', 'wporg' ) ); ?></a></li>
								<li><a href="//s.w.org/style/images/about/WordPress-logotype-alternative-white.png"><?php echo wp_kses_post( ___( 'PNG <span>(White/transparent)</span>', 'wporg' ) ); ?></a></li>
							</ul>
						</div>

						<div class="logo col-4" id="logo-word-mark">
							<img src="//s.w.org/style/images/about/wordmark.png" alt="<?php esc_attr_e( 'WordPress Logotype - Word Mark', 'wporg' ); ?>" />
							<div class="logo-header">
								<strong><?php _esc_html_e( 'WordPress Logotype', 'wporg' ); ?></strong>
								<span><?php _esc_html_e( 'Word Mark', 'wporg' ); ?></span>
							</div>
							<ul class="resources-list">
								<li><a href="//s.w.org/style/images/about/WordPress-logotype-wordmark.pdf"><?php echo wp_kses_post( ___( 'PDF <span>(Vector)</span>', 'wporg' ) ); ?></a></li>
								<li><a href="//s.w.org/style/images/about/WordPress-logotype-wordmark.png"><?php echo wp_kses_post( ___( 'PNG <span>(BaseGray/transparent)</span>', 'wporg' ) ); ?></a></li>
								<li><a href="//s.w.org/style/images/about/WordPress-logotype-wordmark-white.png"><?php echo wp_kses_post( ___( 'PNG <span>(White/transparent)</span>', 'wporg' ) ); ?></a></li>
							</ul>
						</div>

						<div class="logo col-4" id="logo-w-mark">
							<img src="//s.w.org/style/images/about/wmark.png" alt="<?php esc_attr_e( 'WordPress Logotype - W Mark', 'wporg' ); ?>" />
							<div class="logo-header">
								<strong><?php _esc_html_e( 'WordPress Logotype', 'wporg' ); ?></strong>
								<span><?php _esc_html_e( 'W Mark', 'wporg' ); ?></span>
							</div>
							<ul class="resources-list">
								<li><a href="//s.w.org/about/images/logos/WordPress-logotype-wmark.pdf"><?php echo wp_kses_post( ___( 'PDF <span>(Vector)</span>', 'wporg' ) ); ?></a></li>
								<li><a href="//s.w.org/about/images/logos/WordPress-logotype-wmark.png"><?php echo wp_kses_post( ___( 'PNG <span>(BaseGray/transparent)</span>', 'wporg' ) ); ?></a></li>
								<li><a href="//s.w.org/about/images/logos/WordPress-logotype-wmark-white.png"><?php echo wp_kses_post( ___( 'PNG <span>(White/transparent)</span>', 'wporg' ) ); ?></a></li>
							</ul>
						</div>



						<div class="logo col-4"></div>
					</section>

					<h3 class="graphics">Fight the Fake Logo (Fauxgo)</h3>
					<p>Friends don&#8217;t let friends use the wrong WordPress logo. If you see one of these in the wild, please suggest a change.</p>
					<img class="aligncenter" src="//s.w.org/about/images/logo-comparison.png" width="500" />
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
