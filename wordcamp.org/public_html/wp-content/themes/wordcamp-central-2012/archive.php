<?php
/**
 * Archives template
 */
?>
<?php get_header(); ?>

	<div id="container" class="group">
		<div id="content" role="main" class="group">
			<?php if ( have_posts() ) the_post(); ?>

			<h1 class="page-title">
				<?php if ( is_day() ) : ?>
					<?php printf( __( 'Daily Archives: <span>%s</span>', 'twentyten' ), get_the_date() ); ?>
				<?php elseif ( is_month() ) : ?>
						<?php printf( __( 'Monthly Archives: <span>%s</span>', 'twentyten' ), get_the_date( _x( 'F Y', 'monthly archives date format', 'twentyten' ) ) ); ?>
				<?php elseif ( is_year() ) : ?>
						<?php printf( __( 'Yearly Archives: <span>%s</span>', 'twentyten' ), get_the_date( _x( 'Y', 'yearly archives date format', 'twentyten' ) ) ); ?>
				<?php else : ?>
						<?php _e( 'Blog Archives', 'twentyten' ); ?>
				<?php endif; ?>
			</h1>
		
			<?php rewind_posts(); // due to the_post() above ?>
							
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

	<?php get_sidebar(); ?>

<?php get_footer(); ?>
