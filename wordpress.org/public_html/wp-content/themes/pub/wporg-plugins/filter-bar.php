<?php
namespace WordPressdotorg\Plugin_Directory\Theme;

?>
<div class="wrapper">
	<div class="col-12 filter-bar">
		<div class="wp-filter">
			<ul class="filter-links">
				<?php if ( get_query_var('s' ) ) { ?>
					<li class="plugin-install-search"><a href="<?php echo esc_url( home_url( 'search/' . urlencode( get_query_var('s') ) . '/' ) ); ?>" class="current"><?php _ex( 'Search Results', 'tab', 'wporg-plugins' ); ?></a></li>
				<?php } ?>
				<li class="plugin-install-featured"><a href="<?php echo esc_url( home_url( 'browse/featured/' ) ); ?>" <?php if ( (is_front_page() && !get_query_var('browse') ) || 'featured' == get_query_var('browse') ) { echo 'class="current"'; } ?>><?php _ex( 'Featured', 'plugins', 'wporg-plugins' ); ?></a></li>
				<li class="plugin-install-popular"><a href="<?php echo esc_url( home_url( 'browse/popular/' ) ); ?>" <?php if ( 'popular' == get_query_var('browse') ) { echo 'class="current"'; } ?>><?php _ex( 'Popular', 'plugins', 'wporg-plugins' ); ?></a> </li>
				<?php if ( is_user_logged_in() ) { ?>
					<li class="plugin-install-favorites"><a href="<?php echo esc_url( home_url( 'browse/favorites/' ) ); ?>" <?php if ( 'favorites' == get_query_var('browse') ) { echo 'class="current"'; } ?>><?php _ex( 'Favorites', 'plugins', 'wporg-plugins' ); ?></a></li>
				<?php } ?>
				<li class="plugin-install-beta"><a href="<?php echo esc_url( home_url( 'browse/beta/' ) ); ?>" <?php if ( 'beta' == get_query_var('browse') ) { echo 'class="current"'; } ?>><?php _ex( 'Beta Testing', 'plugins', 'wporg-plugins' ); ?></a></li>
				<li class="plugin-developer"><a href="<?php echo get_permalink( get_page_by_path( 'about' ) ); ?>" <?php if ( 'about' == get_query_var( 'pagename' ) ) { echo 'class="current"'; } ?>><?php _ex( 'Developers', 'plugins', 'wporg-plugins' ); ?></a></li>
			</ul>

			<form class="search-form search-plugins" method="get" action="<?php echo home_url('/'); ?>">
				<label>
					<span class="screen-reader-text"><?php _e( 'Search Plugins', 'wporg-plugins' ); ?></span>
					<input type="search" name="s" value="<?php echo esc_attr( get_query_var( 's' ) ); ?>" class="wp-filter-search" placeholder="<?php esc_attr_e( 'Search plugins...', 'wporg-plugins' ); ?>">
				</label>
				<input type="submit" name="" id="search-submit" class="button screen-reader-text" value="<?php esc_attr_e( 'Search Plugins', 'wporg-plugins' ); ?>">
			</form>
		</div>
	</div>
</div>
