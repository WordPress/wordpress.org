		<div class="col-2 secondary">
			<a href="<?php echo home_url( '/submit-a-wordpress-site/' ); ?>" class="wpsc-submit-site">Submit a Site &rarr;</a>
			
			<h4 class="search">Search</h4>
			<?php // @todo: use get_search_form(); ?>
			<form method="get" id="searchform" action="<?php echo home_url( '/' ); ?>">
				<input type="text" value="<?php the_search_query(); ?>" name="s" id="s" class="text" />
				<input type="submit" id="searchsubmit" value="Go" class="button" />
			</form>
			

			<?php popular_tags(); ?>
			<a href='<?php echo home_url( '/tag-cloud/' ); ?>' class="wpsc-all-tags">View All Tags &rarr;</a>
			
			<h4>Browse by Flavor</h4>
			<ul class="submenu">
				<?php wp_list_categories( 'exclude=4&title_li=' ); ?>
			</ul>
		</div>
