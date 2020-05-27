<?php
get_header();

?>
<div id="pagebody">
	<div class="wrapper">

		<?php get_sidebar( 'left' ); ?>
		<div class="col-5">
		<?php breadcrumb(); ?>

		<?php if ( have_posts() ) : ?>

			<?php while ( have_posts() ) : the_post(); ?>

				<div class="story-excerpt">
					<a href="<?php the_permalink() ?>" title="<?php the_title_attribute(); ?>">
						<?php site_screenshot_tag( 145, 'screenshot alignleft' ); ?>
					</a>
					<h3 class="heading"><a href="<?php the_permalink() ?>" title="<?php the_title_attribute(); ?>"><?php the_title(); ?></a></h3>
					<div class="excerpt"><?php the_content_limit(200); ?></div>
					<div class="meta"><?php tags_with_count( 'flat', '<strong>' . __( 'Tags:', 'wporg-showcase' ) . '</strong> ', ', ', '<br />'); ?><?php edit_post_link( __( 'Edit this entry', 'wporg-showcase' ) ); ?></div>
					<div style="clear:both;"></div>
				</div>

			<?php endwhile; // have_posts ?>

			<?php
				the_posts_pagination( [
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
