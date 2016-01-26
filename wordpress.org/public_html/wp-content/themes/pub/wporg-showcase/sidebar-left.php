		<div class="col-2 secondary">
			<a href="<?php echo home_url( '/submit-a-wordpress-site/' ); ?>" class="wpsc-submit-site"><?php _e( 'Submit a Site &rarr;', 'wporg-showcase' ); ?></a>

			<h4 class="search"><?php _e( 'Search', 'wporg-showcase' ); ?></h4>
			<?php // @todo: use get_search_form(); ?>
			<form method="get" id="searchform" action="<?php echo home_url( '/' ); ?>">
				<input type="text" value="<?php the_search_query(); ?>" name="s" id="s" class="text" />
				<input type="submit" id="searchsubmit" value="<?php esc_attr_e( 'Go', 'wporg-showcase' ); ?>" class="button" />
			</form>

			<?php popular_tags(); ?>
			<a href='<?php echo home_url( '/tag-cloud/' ); ?>' class="wpsc-all-tags"><?php _e( 'View All Tags &rarr;', 'wporg-showcase' ); ?></a>

			<h4><?php _e( 'Browse by Flavor', 'wporg-showcase' ); ?></h4>
			<ul class="submenu">
				<?php wp_list_categories( 'exclude=4&title_li=' ); ?>
			</ul>
		</div>
