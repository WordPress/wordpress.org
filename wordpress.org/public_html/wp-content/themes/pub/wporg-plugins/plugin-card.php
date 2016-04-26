<?php
namespace WordPressdotorg\Plugin_Directory\Theme;
use WordPressdotorg\Plugin_Directory\Template;

?>
<div class="plugin-card">
	 <div class="plugin-card-top">

		 <a href="<?php the_permalink(); ?>" class="plugin-icon">
		 	<?php echo Template::get_plugin_icon( $post->post_name, 'html' ); ?>
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
			<?php echo Template::dashicons_stars( get_post_meta( $post->ID, 'rating', true ) ); ?>
		</div>
		<div class="column-updated">
			<strong><?php _e( 'Last Updated:', 'wporg-plugins' ); ?></strong> <?php echo wporg_plugins_template_last_updated(); ?>
		</div>
		<div class="column-installs">
			<?php echo Template::active_installs(); ?>
		</div>
		<div class="column-compatibility">
			<strong><?php _e( 'Compatible up to:', 'wporg-plugins' ); ?></strong> <?php echo wporg_plugins_template_compatible_up_to(); ?>
		</div>
	</div>
</div>
