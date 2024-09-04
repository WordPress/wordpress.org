<?php
/**
 * Searchform template.
 *
 * @package wporg-breathe
 */
?>
<?php $search_url = function_exists( 'wporg_is_handbook' ) && wporg_is_handbook() ? wporg_get_current_handbook_home_url() : home_url( '/' ); ?>
<form method="get" id="searchform" class="searchform" action="<?php echo esc_url( $search_url ); ?>" role="search">
	<label for="s" class="screen-reader-text"><?php _ex( 'Search', 'label', 'wporg' ); ?></label>
	<input type="search" class="field" name="s" value="<?php echo get_search_query(); ?>" id="s" placeholder="<?php _ex( 'Search &hellip;', 'placeholder', 'wporg' ); ?>">
	<input type="submit" class="submit" id="searchsubmit" value="<?php echo esc_attr_x( 'Search', 'submit button', 'wporg' ); ?>">
</form>
