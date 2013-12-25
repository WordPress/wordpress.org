<?php

if ( !class_exists( 'Organizer_Admin' ) ) :
/**
 * Organizer_Admin
 *
 * Loads plugin admin area
 *
 * @package WordCamp Post Type
 * @subpackage Admin
 * @since WordCamp Post Type (0.1)
 */
class Organizer_Admin {

	/**
	 * wcpt_admin ()
	 *
	 * Initialize WCPT Admin
	 */
	function Organizer_Admin () {

		// Add some general styling to the admin area
		add_action( 'wcpt_admin_head',                                     array( $this, 'admin_head' ) );

		// Forum column headers.
		add_filter( 'manage_' . WCO_POST_TYPE_ID . '_posts_columns',       array( $this, 'column_headers' ) );

		// Forum columns (in page row)
		add_action( 'manage_posts_custom_column',                          array( $this, 'column_data' ), 10, 2 );
		add_filter( 'page_row_actions',                                    array( $this, 'post_row_actions' ), 10, 2 );

		// Topic metabox actions
		add_action( 'admin_menu',                                          array( $this, 'metabox' ) );
		add_action( 'save_post',                                           array( $this, 'metabox_save' ) );
	}

	/**
	 * metabox ()
	 *
	 * Add the metabox
	 *
	 * @uses add_meta_box
	 */
	function metabox () {
		add_meta_box (
			'wco_parent_id',
			__( 'Organizer Information', 'wcpt' ),
			'wco_metabox',
			WCO_POST_TYPE_ID,
			'normal'
		);
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
		// Post meta keys
		$meta_keys = Organizer_Admin::meta_keys();

		// Loop through meta keys and update
		foreach ( $meta_keys as $key => $value ) {
			$post_value   = wcpt_key_to_str( $key, 'wco_' );
			$values[ $key ] = isset( $_POST[ $post_value ] ) ? wp_strip_all_tags( $_POST[ $post_value ] ) : '';
			update_post_meta( $post_id, $key, $values[$key] );
		}
	}

	/**
	 * meta_keys ()
	 *
	 * Returns post meta key
	 *
	 * @return array
	 */
	function meta_keys () {
		return apply_filters( 'wco_admin_meta_keys', array(
			'Email Address'   => 'text',
			'Mailing Address' => 'text',
			'Telephone'       => 'text'
		) );
	}

	/**
	 * admin_head ()
	 *
	 * Add some general styling to the admin area
	 */
	function admin_head () {
		// Icons for top level admin menus
		$menu_icon_url	= WCPT_IMAGES_URL . '/icon-organizer.png';

		// Top level menu classes
		$icon_class = sanitize_html_class( WCO_POST_TYPE_ID ); ?>

		#menu-posts-<?php echo $icon_class; ?> .wp-menu-image {
			background: url(<?php echo $menu_icon_url; ?>) no-repeat 0 -32px;
		}
		#menu-posts-<?php echo $icon_class; ?>:hover .wp-menu-image,
		#menu-posts-<?php echo $icon_class; ?>.wp-has-current-submenu .wp-menu-image {
			background: url(<?php echo $menu_icon_url; ?>) no-repeat 0 0;
		}

		<?php if ( $_GET['post_type'] == WCO_POST_TYPE_ID ) : ?>

			#icon-edit, #icon-post {
				background: url(<?php echo WCPT_IMAGES_URL . '/icon32.png'; ?>) no-repeat 4px 0;
			}

			.column-title { width: 40%; }
			.column-wcpt_location, .column-wcpt_date, column-wcpt_organizer { white-space: nowrap; }

		<?php endif;
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
	}

	/**
	 * column_headers ()
	 *
	 * Manage the column headers
	 *
	 * @param array $columns
	 * @return array $columns
	 */
	function column_headers ( $columns ) {
		$columns = array (
			'cb'                     => '<input type="checkbox" />',
			'title'                  => __( 'Name', 'wcpt' ),
			'wcpt_email_address'     => __( 'Email Address', 'wcpt' ),
			'wcpt_telephone'         => __( 'Telephone', 'wcpt' ),
			'wcpt_mailing_address'   => __( 'Mailing Address', 'wcpt' ),
			'date'                   => __( 'Status' , 'wcpt' )
		);
		return $columns;
	}

	/**
	 * column_data ( $column, $post_id )
	 *
	 * Print extra columns
	 *
	 * @param string $column
	 * @param int $post_id
	 */
	function column_data ( $column, $post_id ) {
		if ( $_GET['post_type'] !== WCO_POST_TYPE_ID )
			return $column;

		switch ( $column ) {
			case 'wcpt_email_address' :
				wcpt_organizer_email();
				break;

			case 'wcpt_telephone' :
				wcpt_organizer_telephone();
				break;

			case 'wcpt_mailing_address' :
				wcpt_organizer_mailing();
				break;
		}
	}

	/**
	 * post_row_actions ( $actions, $post )
	 *
	 * Remove the quick-edit action link and display the description under
	 *
	 * @param array $actions
	 * @param array $post
	 * @return array $actions
	 */
	function post_row_actions ( $actions, $post ) {
		if ( WCPT_POST_TYPE_ID == $post->post_type ) {
			unset( $actions['inline'] );

			the_content();
		}
		return $actions;
	}
}
endif; // class_exists check

/**
 * wcpt_metabox ()
 *
 * The metabox that holds all of the additional information
 *
 * @package WordCamp Post Type
 * @subpackage Template Tags
 * @since WordCamp Post Type (0.1)
 */
function wco_metabox () {
	global $post_id;

	$meta_keys = Organizer_Admin::meta_keys(); ?>

		<div class="inside">

<?php foreach ( $meta_keys as $key => $value ) : $object_name = wcpt_key_to_str( $key, 'wco_' ); ?>

			<p>
				<strong><?php echo $key; ?></strong>
			</p>
			<p>
				<label class="screen-reader-text" for="<?php echo $object_name; ?>"><?php echo $key; ?></label>
				<input type="text" size="36" name="<?php echo $object_name; ?>" id="<?php echo $object_name; ?>" value="<?php echo esc_attr( get_post_meta( $post_id, $key, true ) ); ?>" />
			</p>

<?php endforeach; ?>

		</div>

<?php
}

?>
