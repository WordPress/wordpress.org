<?php get_header(); ?>

<div class="pagebody">
	<div class="wrapper">
		<?php // get_template_part( 'breadcrumbs' ); ?>

		<?php if ( have_posts() ) : the_post(); ?>

			<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>

				<h1><?php the_title(); ?></h1>

				<section class="content">
					<?php the_content(); ?>
				</section>

				<?php // comments_template( '/examples.php' ); ?>

			</article>
		
		<?php else : ?>
		
			<h1><?php _e('Not Found'); ?></h1>
		
		<?php endif; ?>

	</div><!-- /wrapper -->
</div><!-- /pagebody -->

<?php get_footer(); ?>
