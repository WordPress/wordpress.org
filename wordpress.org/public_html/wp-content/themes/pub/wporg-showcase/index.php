<?php get_header(); ?>
<div id="pagebody">
	<div class="wrapper archives">
		<?php get_sidebar( 'left' ); ?>
		<div class="col-5 archives-content">
		<?php
			breadcrumb();
			$num_posts = 400;
			$offset    = $paged > 1 ? "&offset=" . ($paged - 1) * $num_posts : '';
			query_posts("showposts=" . $num_posts . "&post_type=post&post_status=publish" . $offset);
		?>

		<?php if ( have_posts() ) : ?>
			<?php while ( have_posts() ) : the_post(); ?>

				<?php the_date('', '<h3 class="heading">', '</h3>'); ?>
				<div class="storycontent"><a href='<?php the_permalink(); ?>' title='<?php the_title_attribute(); ?>'><?php the_title(); ?></a></div>

			<?php endwhile; // have_posts ?>
			<?php
				the_posts_pagination( [
					'current'  => $paged ?: 1,
					'mid_size' => 2,
				] );
			?>

		<?php else : // have_posts ?>

			<p><?php _e( 'Sorry, no sites in the Showcase matched your criteria.', 'wporg-showcase' ); ?></p>

		<?php endif; ?>

		</div>
		<?php get_sidebar( 'right' ); ?>
	</div>
</div>
<?php get_footer(); ?>
