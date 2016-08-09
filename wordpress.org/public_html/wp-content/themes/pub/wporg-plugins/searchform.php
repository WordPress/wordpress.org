<?php
namespace WordPressdotorg\Plugin_Directory\Theme;
?>
<form role="search" method="get" class="search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label for="s" class="screen-reader-text"><?php _ex( 'Search for:', 'label', 'wporg-plugins' ); ?></label>
	<input type="search" id="s" class="search-field" placeholder="<?php echo esc_attr_x( 'Search plugins', 'placeholder', 'wporg-plugins' ); ?>" value="<?php the_search_query(); ?>" name="s" />
	<button class="button button-primary button-search"><i class="dashicons dashicons-search"></i><span class="screen-reader-text"><?php _e( 'Search plugins', 'wporg-plugins' ); ?></span></button>
</form>
