<?php get_header(); ?>

<div class="wrapper">
	<?php while( have_posts() ): the_post(); ?>
		<div id="post-<?php the_ID(); ?>" <?php post_class(); ?>>

			<h2 class="entry-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>

			<div class="entry-content">
				<?php the_content(); ?>
			</div><!-- .entry-content -->

		</div><!-- #post-## -->
	<?php endwhile; ?>
</div><!-- /wrapper -->

<?php get_footer(); ?>
