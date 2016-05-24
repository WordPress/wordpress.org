<?php
/**
 * Searchform template.
 *
 * @package P2
 */
?>
<?php $search_url = function_exists( 'wporg_is_handbook' ) && wporg_is_handbook() ? wporg_get_current_handbook_home_url() : home_url( '/' ); ?>
<form role="search" method="get" id="searchform" class="searchform" action="<?php echo esc_url( $search_url ); ?>">
	<div>
		<label class="screen-reader-text" for="s"><?php _ex( 'Search for:', 'label', 'p2' ); ?></label>
		<input type="text" value="<?php echo get_search_query(); ?>" name="s" id="s" />
		<input type="submit" id="searchsubmit" value="<?php echo esc_attr_x( 'Search', 'submit button', 'p2' ); ?>" />
	</div>
</form>

