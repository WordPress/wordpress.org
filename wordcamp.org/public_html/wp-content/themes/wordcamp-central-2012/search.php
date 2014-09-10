<?php
/**
 * Search results template
 */
?>
<?php get_header(); ?>

	<div id="container" class="group">
		<div id="content" role="main" class="group">
			<h1 class="page-title"><?php printf( __( 'Search Results for: %s', 'twentyten' ), '<span>' . get_search_query() . '</span>' ); ?></h1>
						
			<?php get_search_form(); ?>
			
			<?php if ( have_posts() ) : ?>
				
				<?php get_template_part( 'navigation-above' ); ?>

				<?php while ( have_posts() ) : the_post(); ?>

						<div id="post-<?php the_ID(); ?>" <?php post_class(); ?>>

							<?php get_template_part( 'content', get_post_format() ); ?>

						</div><!-- #post-## -->

				<?php endwhile; // End the loop. Whew. ?>

				<?php get_template_part( 'navigation-below' ); ?>
				
			<?php else : // have_posts ?>
				
				<div id="post-0" class="post no-results not-found">
					<h2 class="entry-title"><?php _e( 'We couldn&#8217;t find anything!', 'twentyten' ); ?></h2>
					<div class="entry-content">
						<p><?php _e( 'Sorry, but nothing matched your search criteria. Please try again with some different keywords.', 'twentyten' ); ?></p>
						<p><?php get_search_form(); ?></p>
					</div><!-- .entry-content -->
				</div><!-- #post-0 -->
				
			<?php endif; ?>

		</div><!-- #content -->

	</div><!-- #container -->

	<?php get_sidebar(); ?>

<?php get_footer(); ?>
