<?php
namespace WordPressdotorg\Plugin_Directory\Admin\Metabox;

use WordPressdotorg\Plugin_Directory\Admin\List_Table;
use WordPressdotorg\Plugin_Directory\Tools;

/**
 * The Plugin Support Reps admin metabox.
 *
 * @package WordPressdotorg\Plugin_Directory\Admin\Metabox
 */
class Support_Reps {

	/**
	 * Filters the postbox classes for custom comment meta boxes.
	 *
	 * @param array $classes An array of postbox classes.
	 * @return array
	 */
	public static function postbox_classes( $classes ) {
		$classes[] = 'support-reps-meta-box';

		return array_filter( $classes );
	}

	/**
	 * Displays a list of support reps for the current plugin.
	 */
	public static function display() {
		$list = new List_Table\Support_Reps();
		$list->prepare_items();
		$list->display();
	}

	/**
	 * Ajax handler for adding a new support rep.
	 */
	public static function add_support_rep() {
		$login   = isset( $_POST['add_support_rep'] ) ? sanitize_user( $_POST['add_support_rep'] ) : '';
		$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;

		check_ajax_referer( 'add-support-rep' );

		if ( ! current_user_can( 'plugin_add_support_rep', $post_id ) || 'publish' !== get_post_status( $post_id ) ) {
			wp_die( -1 );
		}

		global $post;

		$response = new \WP_Ajax_Response();
		$post     = get_post( $post_id );

		if ( ! $support_rep = get_user_by( 'login', $login ) ) {
			$response->add( array(
				'what' => 'support_rep',
				'data' => new \WP_Error( 'error', sprintf( __( 'The user %s does not exist.', 'wporg-plugins' ), '<code>' . $login . '</code>' ) ),
			) );
			$response->send();
		}

		$result = Tools::add_plugin_support_rep( $post->post_name, $support_rep );

		if ( ! $result ) {
			$message = __( 'An error has occurred. Please reload the page and try again.', 'wporg-plugins' );

			$response->add( array(
				'what' => 'support_rep',
				'data' => new \WP_Error( 'error', $message ),
			) );
			$response->send();
		}

		$wp_list_table = new List_Table\Support_Reps();

		$response->add( array(
			'what'     => 'support_rep',
			'id'       => $support_rep->ID,
			'data'     => $wp_list_table->single_row( $support_rep ),
			'position' => -1,
		) );
		$response->send();
	}

	/**
	 * Ajax handler for removing a support rep.
	 */
	public static function remove_support_rep() {
		$id      = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;

		check_ajax_referer( "remove-support-rep-$id" );

		if ( ! current_user_can( 'plugin_remove_support_rep', $post_id ) || 'publish' !== get_post_status( $post_id ) ) {
			wp_die( -1 );
		}

		$response    = new \WP_Ajax_Response();
		$plugin_slug = get_post( $post_id )->post_name;

		if ( ! $support_rep = get_user_by( 'id', $id ) ) {
			$response->add( array(
				'what' => 'support_rep',
				'data' => new \WP_Error( 'error', __( 'The specified user does not exist.', 'wporg-plugins' ) ),
			) );
			$response->send();
		}

		$result = Tools::remove_plugin_support_rep( $plugin_slug, $support_rep );

		wp_die( $result );
	}
}
