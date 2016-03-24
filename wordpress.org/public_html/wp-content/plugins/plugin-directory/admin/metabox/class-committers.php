<?php
namespace WordPressdotorg\Plugin_Directory\Admin\Metabox;
use WordPressdotorg\Plugin_Directory\Admin\List_Table;
use WordPressdotorg\Plugin_Directory\Tools;

/**
 * The Plugin Committers admin metabox.
 *
 * @package WordPressdotorg\Plugin_Directory\Admin\Metabox
 */
class Committers {

	/**
	 * Filters the postbox classes for custom comment meta boxes.
	 *
	 * @param array $classes An array of postbox classes.
	 * @return array
	 */
	public static function postbox_classes( $classes ) {
		$classes[] = 'committers-meta-box';

		return array_filter( $classes );
	}

	/**
	 * Displays a list of committers for the current plugin.
	 */
	public static function display() {
		$list = new List_Table\Committers();
		$list->prepare_items();
		$list->display();
	}

	/**
	 * Ajax handler for adding a new committer.
	 */
	public static function add_committer() {
		$login    = isset( $_POST['add_committer'] ) ? sanitize_user( $_POST['add_committer'] ) : '';
		$post_id  = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;

		check_ajax_referer( 'add-committer' );
		global $post;

		$response = new \WP_Ajax_Response();
		$post     = get_post( $post_id );

		if ( ! $committer = get_user_by( 'login', $login ) ) {
			$response->add( array(
				'what' => 'committer',
				'data' => new \WP_Error( 'error', sprintf( __( 'The user %s does not exist.', 'wporg-plugins' ), '<code>' . $login . '</code>' ) ),
			) );
			$response->send();
		}

		if ( ! current_user_can( 'plugin_add_committer', $post_id ) ) {
				wp_die( -1 );
		}

		$result = Tools::grant_plugin_committer( $post->post_name, $committer );

		if ( ! $result ) {
			$message = __( 'An error has occurred. Please reload the page and try again.', 'wporg-plugins' );

			$response->add( array(
				'what' => 'committer',
				'data' => new \WP_Error( 'error', $message ),
			) );
			$response->send();
		}

		$wp_list_table = new List_Table\Committers();

		$response->add( array(
			'what'     => 'committer',
			'id'       => $committer->ID,
			'data'     => $wp_list_table->single_row( $committer ),
			'position' => -1,
		) );
		$response->send();
	}

	/**
	 * Ajax handler for removing a committer.
	 */
	public static function remove_committer() {
		$id      = isset( $_POST['id'] )      ? (int) $_POST['id']      : 0;
		$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;

		check_ajax_referer( "remove-committer-$id" );

		$response    = new \WP_Ajax_Response();
		$plugin_slug = get_post( $post_id )->post_name;

		if ( ! $committer = get_user_by( 'id', $id ) ) {
			$response->add( array(
				'what' => 'committer',
				'data' => new \WP_Error( 'error', sprintf( __( 'The user %s does not exist.', 'wporg-plugins' ), '<code>' . $login . '</code>' ) ),
			) );
			$response->send();
		}

		if ( ! current_user_can( 'plugin_remove_committer', $post_id ) ) {
				wp_die( -1 );
		}

		$result = Tools::revoke_plugin_committer( $plugin_slug, $committer );

		wp_die( $result );
	}
}
