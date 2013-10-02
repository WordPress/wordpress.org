<?php
/**
 * The main template file.
 *
 * This is the most generic template file in a WordPress theme
 * and one of the two required files for a theme (the other being style.css).
 * It is used to display a page when nothing more specific matches a query.
 * E.g., it puts together the home page when no home.php file exists.
 * Learn more: http://codex.wordpress.org/Template_Hierarchy
 *
 * @package jobswp
 */

get_header(); ?>

<?php get_sidebar(); ?>

	<div id="primary" class="content-area grid_9">
		<main id="main" class="site-main" role="main">

		<?php if ( is_front_page() ) : ?>

			<?php get_template_part( 'content', 'home' ); ?>

		<?php elseif ( is_tax( 'job_category' ) ) : ?>

			<?php get_template_part( 'content', 'category' ); ?>

		<?php elseif ( is_search() ) : ?>

			<?php get_template_part( 'content', 'search' ); ?>

		<?php elseif ( have_posts() ) : ?>

			<?php /* Start the Loop */ ?>
			<?php while ( have_posts() ) : the_post(); ?>

				<?php
					/* Include the Post-Format-specific template for the content.
					 * If you want to override this in a child theme, then include a file
					 * called content-___.php (where ___ is the Post Format name) and that will be used instead.
					 */
					if ( is_page() )
						$content_type = 'page';
					elseif ( is_single() )
						$content_type = 'single';
					else
						$content_type = get_post_format();

					get_template_part( 'content', $content_type );
				?>

			<?php endwhile; ?>

		<?php else : ?>

			<?php get_template_part( 'no-results', 'index' ); ?>

		<?php endif; ?>

		</main><!-- #main -->
	</div><!-- #primary -->

<?php get_footer(); ?>