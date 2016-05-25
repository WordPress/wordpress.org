<?php
namespace WordPressdotorg\Plugin_Directory\Theme;
?>
<form role="search" method="get" class="search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label>
		<span class="screen-reader-text"><?php _ex( 'Search for:', 'label', 'wporg-plugins' ); ?></span>
		<input type="search" class="search-field" placeholder="<?php echo esc_attr_x( 'Search plugins&hellip;', 'placeholder', 'wporg-plugins' ); ?>" value="<?php the_search_query(); ?>" name="s" />
		<button class="button button-primary button-search"><i class="dashicons dashicons-search"></i></button>
	</label>
</form>
