<?php
/**
 * Template Name: About -> History
 *
 * Page template for displaying the History page.
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
			<header class="entry-header">
				<?php the_title( '<h1 class="entry-title col-8">', '</h1>' ); ?>
			</header><!-- .entry-header -->

			<div class="entry-content row">
				<section class="col-8">
					<p><?php echo wp_kses_post( __( 'We&#8217;ve been working on a new book about the history of WordPress drawing on dozens of interviews with the original folks involved and extensive research. It&#8217;s not ready yet, but for the tenth anniversary of WordPress we&#8217;d like to make a chapter available, <em>On forking WordPress, forks in general, early WordPress, and the community</em>, which you can download below in the following formats:', 'wporg' ) ); ?></p>

					<ul>
						<li>
							<a href="chapter3.epub">
								<?php
								/* translators: file format */
								printf( esc_html__( 'Chapter 3 &#8211; %s', 'wporg' ), 'EPUB' );
								?>
							</a>
						</li>
						<li>
							<a href="chapter3.mobi">
								<?php
								/* translators: file format */
								printf( esc_html__( 'Chapter 3 &#8211; %s', 'wporg' ), 'MOBI' );
								?>
							</a>
						</li>
						<li>
							<a href="chapter3.pdf">
								<?php
								/* translators: file format */
								printf( esc_html__( 'Chapter 3 &#8211; %s', 'wporg' ), 'PDF' );
								?>
							</a>
						</li>
					</ul>
				</section>
			</div><!-- .entry-content -->
		</article><!-- #post-## -->

	</main><!-- #main -->

<?php
get_footer();
