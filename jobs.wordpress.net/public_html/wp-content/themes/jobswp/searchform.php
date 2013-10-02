<?php
/**
 * The template for displaying search forms in jobswp
 *
 * @package jobswp
 */
?>
<form role="search" method="get" class="search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<input type="submit" class="search-submit" value="<?php echo esc_attr_x( 'Find Jobs', 'submit button', 'jobswp' ); ?>">
	<label>
		<span class="screen-reader-text"><?php _ex( 'Search for:', 'label', 'jobswp' ); ?></span>
		<div class="dashicons dashicons-search"></div>
		<input type="search" class="search-field" placeholder="<?php echo esc_attr_x( 'What kind of job are you looking for?', 'placeholder', 'jobswp' ); ?>" value="<?php echo esc_attr( get_search_query() ); ?>" name="s" title="<?php echo esc_attr_x( 'Find Jobs:', 'label', 'jobswp' ); ?>">
	</label>
</form>
