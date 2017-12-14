		<div class="col-3 rightsidebar">
			<div class="rightsidebarwrapper">
			<div class="currentSiteRating">
				<p class="button"><a href="http://<?php get_site_domain( false ); ?>"><?php _e( 'Visit Site', 'wporg-showcase' ); ?></a></p>

				<h4><?php _e( 'Rating', 'wporg-showcase' ); ?></h4>
				<?php the_ratings(); ?>
				<p class='rating-descrip'><?php _e( 'Rate this site based on their implementation and use of WordPress.', 'wporg-showcase' ); ?></p>

				<?php wp_flavors(); ?>
				<br />
				<?php tags_with_count( 'list', '<h4>' . __( 'Tags', 'wporg-showcase' ) . '</h4><ul>', '', '</ul>' ); ?>
			</div>

			<h4><?php _e( 'Top Rated', 'wporg-showcase' ); ?></h4>
			<table class="top-rated">
				<?php get_highest_rated( 'post', 10, 10 ); ?>
			</table>

			<h4><?php _e( 'Most Votes', 'wporg-showcase' ); ?></h4>
			<table class="most-votes">
				<?php get_most_rated(); ?>
			</table>
		</div>
	</div>
