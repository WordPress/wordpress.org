<?php
namespace WordPressdotorg\Plugin_Directory\Admin;

/**
 * All functionality related to the Administration interface.
 *
 * @package WordPressdotorg_Plugin_Directory
 */
class Admin_Customizations {
	/**
	 * Fetch the instance of the Plugin_Directory class.
	 */
	public static function instance() {
		static $instance = null;
		return ! is_null( $instance ) ? $instance : $instance = new Admin_Customizations();
	}

	private function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'register_admin_metaboxes' ) );
	}

	/**
	 * Register the Admin metaboxes for the plugin management screens.
	 */
	public function register_admin_metaboxes() {
		add_meta_box(
			'plugin-committers',
			__( 'Plugin Committers', 'wporg-plugins' ),
			array( __NAMESPACE__ . '\\Metabox\\Committers', 'display' ),
			'plugin'
		);
	}

}
