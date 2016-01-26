		<div class="col-3">
			<h4><?php _e( 'Top Rated', 'wporg-showcase' ); ?></h4>
			<table class="top-rated">
				<?php get_highest_rated( 'post', 10, 10 ); ?>
			</table>

			<h4><?php _e( 'Most Votes', 'wporg-showcase' ); ?></h4>
			<table class="most-votes">
				<?php get_most_rated(); ?>
			</table>
		</div>
