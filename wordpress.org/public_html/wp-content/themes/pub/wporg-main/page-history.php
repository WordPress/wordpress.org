<?php
/**
 * Template Name: History
 *
 * Page template for displaying the History page.
 *
 * @package WordPressdotorg\MainTheme
 */

namespace WordPressdotorg\MainTheme;

if ( false === stristr( home_url(), 'test' ) ) {
	return get_template_part( 'page' );
}

get_header();
the_post();
?>

	<main id="main" class="site-main col-12" role="main">

		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<header class="entry-header">
				<h1 class="entry-title"><?php _esc_html_e( 'History', 'wporg' ); ?></h1>
			</header><!-- .entry-header -->

			<div class="entry-content row">
				<section class="col-8">
					<p class="intro">We&#8217;ve been working on a new book about the history of WordPress drawing on dozens of interviews with the original folks involved and extensive research. It&#8217;s not ready yet, but for the tenth anniversary of WordPress we&#8217;d like to make a chapter available, <em>On forking WordPress, forks in general, early WordPress, and the community</em>, which you can download below in the following formats:</p>

					<ul>
						<li><a href="chapter3.epub">Chapter 3 - EPUB</a></li>
						<li><a href="chapter3.mobi">Chapter 3 - MOBI</a></li>
						<li><a href="chapter3.pdf">Chapter 3 - PDF</a></li>
					</ul>
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
