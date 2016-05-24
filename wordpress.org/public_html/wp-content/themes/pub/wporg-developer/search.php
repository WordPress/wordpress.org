<?php namespace DevHub;
/**
 * The template for displaying Search Results pages.
 *
 * @package wporg-developer
 */

get_header(); ?>

	<div id="content-area">

		<div class="breadcrumb-trail breadcrumbs" itemprop="breadcrumb">
			<span class="trail-browse"><span class="trail-begin"><?php _e( 'Search Results', 'wporg' ); ?></span></span>
			<span class="sep">/</span> <span class="trail-end"><?php echo esc_html( get_search_query() ); ?></span>
		</div>

		<?php get_search_form(); ?>

		<main id="main" class="site-main" role="main">

		<?php if ( have_posts() ) : ?>

			<?php /* Start the Loop */ ?>
			<?php while ( have_posts() ) : the_post(); ?>

				<?php get_template_part( 'content', wporg_is_handbook() ? 'handbook-archive' : 'reference-archive' ); ?>

			<?php endwhile; ?>

			<?php loop_pagination(); ?>

		<?php else : ?>

			<?php get_template_part( 'content', 'none' ); ?>

		<?php endif; ?>

		</main><!-- #main -->
		<?php //get_sidebar(); ?>
	</div><!-- #primary -->
<?php get_footer(); ?>
