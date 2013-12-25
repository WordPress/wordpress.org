<?php

if ( !class_exists( 'WCPT_Admin' ) ) :
/**
 * WCPT_Admin
 *
 * Loads plugin admin area
 *
 * @package WordCamp Post Type
 * @subpackage Admin
 * @since WordCamp Post Type (0.1)
 */
class WCPT_Admin {

	/**
	 * wcpt_admin ()
	 *
	 * Initialize WCPT Admin
	 */
	function WCPT_Admin () {

		// Attach the WordCamp Post Type admin init action to the WordPress admin init action.
		add_action( 'admin_init',               array( $this, 'init' ) );

		// User profile edit/display actions
		add_action( 'edit_user_profile',        array( $this, 'user_profile_wordcamp' ) );
		add_action( 'show_user_profile',        array( $this, 'user_profile_wordcamp' ) );

		// User profile save actions
		add_action( 'personal_options_update',  array( $this, 'user_profile_update' ) );
		add_action( 'edit_user_profile_update', array( $this, 'user_profile_update' ) );

		// Add some general styling to the admin area
		add_action( 'admin_head',               array( $this, 'admin_head' ) );

		// Add separator to admin menu
		add_action( 'custom_menu_order',        array( $this, 'admin_custom_menu_order' ) );
		add_action( 'menu_order',               array( $this, 'admin_menu_order'        ) );
	}

	/**
	 * init()
	 *
	 * WordCamp Post Type's dedicated admin init action
	 *
	 * @uses do_action
	 */
	function init () {
		do_action ( 'wcpt_admin_init' );
	}

	/**
	 * metabox ()
	 *
	 * Add the metabox
	 *
	 * @uses add_meta_box
	 */
	function metabox () {
		do_action( 'wcpt_metabox' );
	}

	/**
	 * metabox_save ()
	 *
	 * Pass the metabox values before saving
	 *
	 * @param int $post_id
	 * @return int
	 */
	function metabox_save ( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			return $post_id;

		if ( !current_user_can( 'edit_post', $post_id ) )
			return $post_id;

		do_action( 'wcpt_metabox_save' );
	}

	/**
	 * admin_head ()
	 *
	 * Add some general styling to the admin area
	 */
	function admin_head () {
?>
		<style type="text/css" media="screen">
		/*<![CDATA[*/
<?php
			// Add extra actions to WordCamp Post Type admin header area
			do_action( 'wcpt_admin_head' );
?>

		/*]]>*/
		</style>
<?php
	}

	/**
	 * user_profile_update ()
	 *
	 * Responsible for showing additional profile options and settings
	 *
	 * @todo Everything
	 */
	function user_profile_update ( $user_id ) {
		if ( !wcpt_has_access() )
			return false;

		// Add extra actions to WordCamp Post Type profile update
		do_action( 'wcpt_user_profile_update' );
	}

	/**
	 * user_profile_wordcamp ()
	 *
	 * Responsible for saving additional profile options and settings
	 *
	 * @todo Everything
	 */
	function user_profile_wordcamp ( $profileuser ) {

		if ( !wcpt_has_access() )
			return false;

?>
		<h3><?php _e( 'WordCamps', 'wcpt' ); ?></h3>
		<table class="form-table">
			<tr valign="top">
				<th scope="row"><?php _e( 'WordCamps', 'wcpt' ); ?></th>
				<td>

				</td>
			</tr>
		</table>
<?php

		// Add extra actions to WordCamp Post Type profile update
		do_action( 'wcpt_user_profile_wordcamps' );
	}

	/**
	 * Tell WordPress we have a custom menu order
	 *
	 * @param bool $menu_order Menu order
	 * @return bool Always true
	 */
	function admin_custom_menu_order( $menu_order ) {
		return true;
	}

	/**
	 * Move our custom separator above our custom post types
	 *
	 * @param array $menu_order Menu Order
	 * @uses bbp_get_forum_post_type() To get the forum post type
	 * @return array Modified menu order
	 */
	function admin_menu_order( $menu_order ) {
		global $menu;

		$menu[] = array( '', 'read', 'separator-wcpt', '', 'wp-menu-separator' );

		// Initialize our custom order array
		$wcpt_menu_order = array();

		// Get the index of our custom separator
		$wcpt_separator = array_search( 'separator-wcpt', $menu_order );

		// Loop through menu order and do some rearranging
		foreach ( $menu_order as $index => $item ) {

			// Current item is our forum CPT, so set our separator here
			if ( ( ( 'edit.php?post_type=' . WCPT_POST_TYPE_ID ) == $item ) ) {
				$wcpt_menu_order[] = 'separator-wcpt';
				unset( $menu_order[$wcpt_separator] );
			}

			// Skip our separator
			if ( !in_array( $item, array( 'separator-wcpt' ) ) )
				$wcpt_menu_order[] = $item;

		}

		// Return our custom order
		return $wcpt_menu_order;
	}
}
endif; // class_exists check

?>
