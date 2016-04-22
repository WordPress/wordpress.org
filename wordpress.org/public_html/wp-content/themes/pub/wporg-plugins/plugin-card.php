<div class="plugin-card">
	 <div class="plugin-card-top">

		 <a href="<?php the_permalink(); ?>" class="plugin-icon">
		 	<?php echo WordPressdotorg\Plugin_Directory\Template::get_plugin_icon( $post->post_name, 'html' ); ?>
		 </a>
		 <div class="name column-name">
			 <h4><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h4>
		 </div>
		<div class="desc column-description">
			<p><?php the_excerpt(); ?></p>
			<p class="authors"><?php echo wporg_plugins_template_authors(); ?></p>
		</div>
	</div>

	<div class="plugin-card-bottom">
		<div class="vers column-rating">
			<?php
				if ( function_exists( 'wporg_get_dashicons_stars' ) ) {
					echo wporg_get_dashicons_stars( get_post_meta( $post->ID, 'rating', true ) );
				}
			?>
		</div>
		<div class="column-updated">
			<strong><?php _e( 'Last Updated:', 'wporg-plugins' ); ?></strong> <?php echo wporg_plugins_template_last_updated(); ?>
		</div>
		<div class="column-installs">
			<?php echo worg_plugins_template_active_installs( true ); ?>
		</div>
		<div class="column-compatibility">
			<strong><?php _e( 'Compatible up to:', 'wporg-plugins' ); ?></strong> <?php echo wporg_plugins_template_compatible_up_to(); ?>
		</div>
	</div>
</div>
