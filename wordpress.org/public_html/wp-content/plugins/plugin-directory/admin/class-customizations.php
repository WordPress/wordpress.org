<?php
namespace WordPressdotorg\Plugin_Directory\Admin;
use \WordPressdotorg\Plugin_Directory;

/**
 * All functionality related to the Administration interface.
 *
 * @package WordPressdotorg\Plugin_Directory\Admin
 */
class Customizations {

	/**
	 * Fetch the instance of the Admin Customizations class.
	 */
	public static function instance() {
		static $instance = null;

		return ! is_null( $instance ) ? $instance : $instance = new Customizations();
	}

	private function __construct() {
		// Admin Metaboxes
		add_action( 'add_meta_boxes', array( $this, 'register_admin_metaboxes' ), 10, 1 );
		add_action( 'do_meta_boxes', array( $this, 'replace_title_global' ) );

		add_action( 'save_post_plugin', array( $this, 'save_plugin_post' ), 10, 2 );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_save-note', array( $this, 'save_note' ) );
	}

	/**
	 * Adds the plugin name into the post editing title.
	 *
	 * @global $title The wp-admin title variable.
	 *
	 * @param string $post_type The post type of the current page
	 * @return void.
	 */
	public function replace_title_global( $post_type ) {
		global $title;
		if ( 'plugin' === $post_type ) {
			$title = sprintf( $title, get_the_title() ); // esc_html() on output
		}
	}

	/**
	 * Enqueue JS and CSS assets needed for any wp-admin screens.
	 *
	 * @param string $hook_suffix The hook suffix of the current screen.
	 * @return void.
	 */
	public function enqueue_assets( $hook_suffix ) {
		global $post_type;

		if ( 'post.php' == $hook_suffix && 'plugin' == $post_type ) {
			wp_enqueue_style( 'plugin-admin-edit-css', plugins_url( 'css/edit-form.css', Plugin_Directory\PLUGIN_FILE ), array( 'edit' ), 1 );
			wp_enqueue_script( 'plugin-admin-edit-js', plugins_url( 'js/edit-form.js', Plugin_Directory\PLUGIN_FILE ), array( 'wp-util' ), 1 );
		}
	}

	/**
	 * Register the Admin metaboxes for the plugin management screens.
	 *
	 * @param string $post_type The post type of the current screen.
	 * @return void.
	 */
	public function register_admin_metaboxes( $post_type ) {
		if ( 'plugin' != $post_type ) {
			return;
		}

		add_meta_box(
			'plugin-committers',
			__( 'Plugin Committers', 'wporg-plugins' ),
			array( __NAMESPACE__ . '\Metabox\Committers', 'display' ),
			'plugin', 'side'
		);

		add_meta_box(
			'plugin-notes',
			__( 'Internal Notes', 'wporg-plugins' ),
			array( __NAMESPACE__ . '\Metabox\Notes', 'display' ),
			'plugin', 'normal', 'high'
		);

		add_meta_box(
			'plugin-review',
			__( 'Plugin Review Tools', 'wporg-plugins' ),
			array( __NAMESPACE__ . '\Metabox\Review_Tools', 'display' ),
			'plugin', 'normal', 'high'
		);

		add_meta_box(
			'plugin-fields',
			__( 'Plugin Meta', 'wporg-plugins' ),
			array( __NAMESPACE__ . '\Metabox\Custom_Fields', 'display' ),
			'plugin', 'normal', 'low'
		);

		// Replace the publish box
		add_meta_box(
			'submitdiv',
			__( 'Plugin Controls', 'wporg-plugins' ),
			array( __NAMESPACE__ . '\Metabox\Controls', 'display' ),
			'plugin', 'side', 'high'
		);

		// Remove the Slug metabox
		add_meta_box( 'slugdiv', false, false, false );
	}

	/**
	 * Hook into the save process for the plugin post_type to save extra metadata.
	 *
	 * Currently saves the tested_with value.
	 *
	 * @param int      $post_id The post_id being updated.
	 * @param \WP_Post $post    The WP_Post object being updated.
	 */
	public function save_plugin_post( $post_id, $post ) {
		// Save meta information
		if ( isset( $_POST['tested_with'] ) && isset( $_POST['hidden_tested_with'] ) && $_POST['tested_with'] != $_POST['hidden_tested_with'] ) {
			update_post_meta( $post_id, 'tested', wp_slash( wp_unslash( $_POST['tested_with'] ) ) );
		}
	}

	/**
	 * Saves a plugin note.
	 */
	public function save_note() {
		check_admin_referer( 'save-note', 'notce' );

		if ( empty( $_POST['id'] ) ) {
			wp_send_json_error( array( 'errorCode' => 'no_post_specified' ) );
		}

		if ( ! current_user_can( 'review_plugin', absint( $_POST['id'] ) ) ) {
			wp_send_json_error( array(
				'error' => __( 'You do not have sufficient permissions to edit notes on this site.' ),
			) );
		}

		update_post_meta( absint( $_POST['id'] ), 'note', wp_kses_post( $_POST['note'] ) );

		wp_send_json_success( array(
			'note' => wpautop( wp_kses_post( $_POST['note'] ) ),
		) );
	}
}
