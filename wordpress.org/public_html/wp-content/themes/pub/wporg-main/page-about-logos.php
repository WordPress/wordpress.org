<?php
/**
 * Template Name: About -> Logos
 *
 * Page template for displaying the Logos and Graphics page.
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
			<header class="entry-header row">
				<h1 class="entry-title col-8"><?php the_title(); ?></h1>
			</header><!-- .entry-header -->

			<div class="entry-content row">
				<section class="col-8">
					<h2><?php esc_html_e( 'Official WordPress Logo', 'wporg' ); ?></h2>
					<p>
						<?php
						/* translators: Link to Trademark Policy of WordPress Foundation */
						printf( wp_kses_post( __( 'When you need the official WordPress logo for a web site or publication, please use one of the following. Please only use logos in accordance with the <a href="%s">WordPress trademark&nbsp;policy</a>.', 'wporg' ) ), 'http://wordpressfoundation.org/trademark-policy/' );
						?>
					</p>

					<h2><?php esc_html_e( 'Downloads', 'wporg' ); ?></h2>

					<section class="all-logos col-12 row gutters">
						<div class="logo col-4" id="logo-all">
							<img src="//s.w.org/style/images/about/allversions.png" alt="<?php esc_attr_e( 'WordPress Logotypes - All Versions', 'wporg' ); ?>" />
							<div class="logo-header">
								<strong><?php esc_html_e( 'WordPress Logotypes', 'wporg' ); ?></strong>
								<span><?php esc_html_e( 'All versions', 'wporg' ); ?></span>
							</div>
							<ul class="resources-list">
								<li><a href="//s.w.org/style/images/about/WordPress-logotype-all.ai_.zip"><?php esc_html_e( 'Adobe Illustrator CS5+ Sketch 3', 'wporg' ); ?></a></li>
							</ul>
						</div>

						<div class="logo col-4" id="logo-standard">
							<img src="//s.w.org/style/images/about/standard.png" alt="<?php esc_attr_e( 'WordPress Logotype - Standard', 'wporg' ); ?>" />
							<div class="logo-header">
								<strong><?php esc_html_e( 'WordPress Logotype', 'wporg' ); ?></strong>
								<span><?php esc_html_e( 'Standard', 'wporg' ); ?></span>
							</div>
							<ul class="resources-list">
								<li><a href="//s.w.org/style/images/about/WordPress-logotype-standard.pdf"><?php echo wp_kses_post( __( 'PDF <span>(Vector)</span>', 'wporg' ) ); ?></a></li>
								<li><a href="//s.w.org/style/images/about/WordPress-logotype-standard.png"><?php echo wp_kses_post( __( 'PNG <span>(BaseGray/transparent)</span>', 'wporg' ) ); ?></a></li>
								<li><a href="//s.w.org/style/images/about/WordPress-logotype-standard-white.png"><?php echo wp_kses_post( __( 'PNG <span>(White/transparent)</span>', 'wporg' ) ); ?></a></li>
							</ul>
						</div>

						<div class="logo col-4" id="logo-alternative">
							<img src="//s.w.org/style/images/about/alternative.png" alt="<?php esc_attr_e( 'WordPress Logotype - Alternative', 'wporg' ); ?>" />
							<div class="logo-header">
								<strong><?php esc_html_e( 'WordPress Logotype', 'wporg' ); ?></strong>
								<span><?php esc_html_e( 'Alternative, vertical arrangement', 'wporg' ); ?></span>
							</div>
							<ul class="resources-list">
								<li><a href="//s.w.org/style/images/about/WordPress-logotype-alternative.pdf"><?php echo wp_kses_post( __( 'PDF <span>(Vector)</span>', 'wporg' ) ); ?></a></li>
								<li><a href="//s.w.org/style/images/about/WordPress-logotype-alternative.png"><?php echo wp_kses_post( __( 'PNG <span>(BaseGray/transparent)</span>', 'wporg' ) ); ?></a></li>
								<li><a href="//s.w.org/style/images/about/WordPress-logotype-alternative-white.png"><?php echo wp_kses_post( __( 'PNG <span>(White/transparent)</span>', 'wporg' ) ); ?></a></li>
							</ul>
						</div>

						<div class="logo col-4" id="logo-word-mark">
							<img src="//s.w.org/style/images/about/wordmark.png" alt="<?php esc_attr_e( 'WordPress Logotype - Word Mark', 'wporg' ); ?>" />
							<div class="logo-header">
								<strong><?php esc_html_e( 'WordPress Logotype', 'wporg' ); ?></strong>
								<span><?php esc_html_e( 'Word Mark', 'wporg' ); ?></span>
							</div>
							<ul class="resources-list">
								<li><a href="//s.w.org/style/images/about/WordPress-logotype-wordmark.pdf"><?php echo wp_kses_post( __( 'PDF <span>(Vector)</span>', 'wporg' ) ); ?></a></li>
								<li><a href="//s.w.org/style/images/about/WordPress-logotype-wordmark.png"><?php echo wp_kses_post( __( 'PNG <span>(BaseGray/transparent)</span>', 'wporg' ) ); ?></a></li>
								<li><a href="//s.w.org/style/images/about/WordPress-logotype-wordmark-white.png"><?php echo wp_kses_post( __( 'PNG <span>(White/transparent)</span>', 'wporg' ) ); ?></a></li>
							</ul>
						</div>

						<div class="logo col-4" id="logo-w-mark">
							<img src="//s.w.org/style/images/about/wmark.png" alt="<?php esc_attr_e( 'WordPress Logotype - W Mark', 'wporg' ); ?>" />
							<div class="logo-header">
								<strong><?php esc_html_e( 'WordPress Logotype', 'wporg' ); ?></strong>
								<span><?php esc_html_e( 'W Mark', 'wporg' ); ?></span>
							</div>
							<ul class="resources-list">
								<li><a href="//s.w.org/style/images/about/WordPress-logotype-wmark.pdf"><?php echo wp_kses_post( __( 'PDF <span>(Vector)</span>', 'wporg' ) ); ?></a></li>
								<li><a href="//s.w.org/style/images/about/WordPress-logotype-wmark.png"><?php echo wp_kses_post( __( 'PNG <span>(BaseGray/transparent)</span>', 'wporg' ) ); ?></a></li>
								<li><a href="//s.w.org/style/images/about/WordPress-logotype-wmark-white.png"><?php echo wp_kses_post( __( 'PNG <span>(White/transparent)</span>', 'wporg' ) ); ?></a></li>
							</ul>
						</div>

						<div class="logo col-4" id="logo-simplified">
							<img src="//s.w.org/style/images/about/simplified.png" alt="<?php esc_attr_e( 'WordPress Logotype - Simplified', 'wporg' ); ?>" />
							<div class="logo-header">
								<strong><?php esc_html_e( 'WordPress Logotype', 'wporg' ); ?></strong>
								<span><?php esc_html_e( 'Simplified', 'wporg' ); ?></span>
							</div>
							<ul class="resources-list">
								<li><a href="//s.w.org/style/images/about/WordPress-logotype-simplified.pdf?1"><?php echo wp_kses_post( __( 'PDF <span>(Vector)</span>', 'wporg' ) ); ?></a></li>
								<li><a href="//s.w.org/style/images/about/WordPress-logotype-simplified.png"><?php echo wp_kses_post( __( 'PNG <span>(BaseGray/transparent)</span>', 'wporg' ) ); ?></a></li>
							</ul>
						</div>
					</section>

					<h2><?php esc_html_e( 'Fight the Fake Logo (Fauxgo)', 'wporg' ); ?></h2>
					<p><?php esc_html_e( 'Friends don&#8217;t let friends use the wrong WordPress logo. If you see one of these in the wild, please suggest a change.', 'wporg' ); ?></p>
					<img class="aligncenter" src="//s.w.org/about/images/logo-comparison.png" width="500" />
				</section>
			</div><!-- .entry-content -->

		</article><!-- #post-## -->

	</main><!-- #main -->

<?php
get_footer();
