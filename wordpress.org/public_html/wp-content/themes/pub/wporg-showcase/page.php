<?php get_header(); ?>

<div id="pagebody">
	<div class="wrapper">
		<?php get_sidebar( 'left' ); ?>
		<div class="col-7">
			
			<?php if ( have_posts() ) : ?>
				<?php while ( have_posts() ) : the_post(); ?>
					
					<?php breadcrumb(); ?>
					<div class="storycontent">
						<?php the_content(); ?>
					</div>
				
				<?php endwhile; // have_posts ?>
			<?php endif; // have_posts ?>

		</div>
		<?php get_sidebar( 'right' ); ?>
	</div>
</div>
<?php get_footer(); ?>
