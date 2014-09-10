<?php
/**
 * Displays posts list and fallback for missing templates.
 */
?>
<?php get_header(); ?>

<div id="container" class="group">
	<div id="content" role="main" class="group">
	<h1 class="page-title">WordCamp News</h1>
	<?php get_search_form(); ?>

	<?php get_template_part( 'navigation-above' ); ?>
	
	<?php while ( have_posts() ) : the_post(); ?>

			<div id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
				
				<?php get_template_part( 'content', get_post_format() ); ?>

			</div><!-- #post-## -->

	<?php endwhile; // End the loop. Whew. ?>
	
	<?php get_template_part( 'navigation-below' ); ?>
	
	</div><!-- #content -->
	
</div><!-- #container -->

<?php get_sidebar('blog'); ?>

<?php get_footer(); ?>
