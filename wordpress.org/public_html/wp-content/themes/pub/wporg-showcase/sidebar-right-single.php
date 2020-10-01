		<div class="col-3 rightsidebar">
			<div class="rightsidebarwrapper">
			<div class="currentSiteRating">
				<p class="button"><a href="http://<?php get_site_domain( false ); ?>"><?php _e( 'Visit Site', 'wporg-showcase' ); ?></a></p>

				<?php wp_flavors(); ?>
				<br />
				<?php tags_with_count( 'list', '<h2 class="heading">' . __( 'Tags', 'wporg-showcase' ) . '</h2><ul>', '', '</ul>' ); ?>
			</div>
			</div>
		</div>
