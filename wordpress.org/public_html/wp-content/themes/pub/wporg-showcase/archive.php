<?php 
get_header(); 

// order the displayed posts by the ratings average
global $query_string;
query_posts( $query_string . '&meta_key=ratings_average&orderby=meta_value_num' );
?>
<div id="pagebody">
	<div class="wrapper">
		
		<?php get_sidebar( 'left' ); ?>
		<div class="col-7">
		<?php breadcrumb(); ?>
		
		<?php if ( have_posts() ) : ?>
			
			<?php while ( have_posts() ) : the_post(); ?>

				<div class="story-excerpt">
					<a href="<?php the_permalink() ?>" title="<?php the_title_attribute(); ?>">
						<?php site_screenshot_tag( 145, 'screenshot alignleft' ); ?>
					</a>
					<h5><a href="<?php the_permalink() ?>" title="<?php the_title_attribute(); ?>"><?php the_title(); ?></a></h5>
					<div class="excerpt"><?php the_content_limit(200); ?></div>
					<div class="meta"><?php tags_with_count( 'flat', '<strong>Tags:</strong> ', ', ', '<br />'); ?><?php edit_post_link( 'Edit this entry' ); ?></div>
					<div style="clear:both;"></div>
				</div>

			<?php endwhile; // have_posts ?>
			
			<?php if ( 1 != $wp_query->max_num_pages || function_exists( 'wp_page_numbers' ) ) { wp_page_numbers(); } ?>
		
		<?php else : // have_posts ?>

			<p><?php _e('Sorry, no sites in the Showcase matched your criteria.'); ?></p>

		<?php endif; ?>

		</div>
		
		<?php get_sidebar( 'right' ); ?>
		
	</div>
</div>

<?php get_footer(); ?>
