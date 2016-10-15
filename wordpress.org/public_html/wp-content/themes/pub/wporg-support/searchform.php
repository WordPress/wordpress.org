<?php
namespace WordPressdotorg\Forums;
?>
<form role="search" method="get" class="search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label for="s" class="screen-reader-text"><?php _ex( 'Search for:', 'label', 'wporg-forums' ); ?></label>
	<input type="search" id="s" class="search-field" placeholder="<?php echo esc_attr_x( 'Search forums', 'placeholder', 'wporg-forums' ); ?>" value="<?php the_search_query(); ?>" name="s" />
	<button class="button button-primary button-search"><i class="dashicons dashicons-search"></i><span class="screen-reader-text"><?php _e( 'Search forums', 'wporg-forums' ); ?></span></button>
</form>
