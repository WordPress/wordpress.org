<?php
/**
 * The template for displaying search results pages.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/#search-result
 *
 * @package WordPressdotorg\Theme
 */

namespace WordPressdotorg\Theme;

get_header(); ?>

	<main id="main" class="site-main col-12" role="main">

		<?php if ( have_posts() ) : ?>

			<header class="page-header">
				<h1 class="page-title">
					<?php
					printf(
						/* translators: Search query. */
						esc_html__( 'Search Results for: %s', 'wporg' ),
						get_search_query()
					);
					?>
				</h1>
			</header><!-- .page-header -->

			<?php
			// Start the loop.
			while ( have_posts() ) :
				the_post();
			?>

			<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
				<header class="entry-header">
					<?php the_title( sprintf( '<h2 class="entry-title"><a href="%s" rel="bookmark">', esc_url( get_permalink() ) ), '</a></h2>' ); ?>
				</header><!-- .entry-header -->

				<?php if ( 'post' === get_post_type() ) : ?>
					<div class="entry-meta"><?php entry_meta(); ?></div>
				<?php endif; ?>

				<div class="entry-summary">
					<?php the_excerpt(); ?>
				</div><!-- .entry-summary -->

				<footer class="entry-footer">
					<?php
					edit_post_link(
						sprintf(
							/* translators: %s: Name of current post */
							__( 'Edit<span class="screen-reader-text"> "%s"</span>', 'wporg' ),
							get_the_title()
						),
						'<span class="edit-link">',
						'</span>'
					);
					?>
				</footer><!-- .entry-footer -->
			</article><!-- #post-## -->

			<?php
				// End the loop.
				endwhile;

			the_posts_pagination();

			// If no content, include the "No posts found" template.
		else :
			get_template_part( 'template-parts/content', 'none' );

		endif;
		?>

	</main><!-- .site-main -->

<?php
get_footer();
