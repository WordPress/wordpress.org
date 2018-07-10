		<div class="col-3 rightsidebar">
			<div class="rightsidebarwrapper">
			<div class="currentSiteRating">
				<p class="button"><a href="http://<?php get_site_domain( false ); ?>"><?php _e( 'Visit Site', 'wporg-showcase' ); ?></a></p>

				<?php wp_flavors(); ?>
				<br />
				<?php tags_with_count( 'list', '<h4>' . __( 'Tags', 'wporg-showcase' ) . '</h4><ul>', '', '</ul>' ); ?>
			</div>
		</div>
	</div>
