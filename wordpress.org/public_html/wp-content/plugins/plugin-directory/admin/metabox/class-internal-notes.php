<?php
namespace WordPressdotorg\Plugin_Directory\Admin\Metabox;

use WordPressdotorg\Plugin_Directory\Admin\List_Table\Plugin_Comments;

/**
 * The Internal Notes admin metabox.
 *
 * @package WordPressdotorg\Plugin_Directory\Admin\Metabox
 */
class Internal_Notes {

	/**
	 * Filters the postbox classes for custom comment meta boxes.
	 *
	 * @param array $classes An array of postbox classes.
	 * @return array
	 */
	public static function postbox_classes( $classes ) {
		$classes[] = 'comments-meta-box';

		return array_filter( $classes );
	}

	/**
	 * Displays comment box for internal notes.
	 */
	public static function display() {
		$wp_list_table = new Plugin_Comments( array(
			'comment_type' => 'internal-note',
		) );
		wp_nonce_field( 'get-comments', 'add_comment_nonce', false );
		?>
		<p class="hide-if-no-js" id="add-new-comment">
			<a class="button" href="#commentstatusdiv"><?php _e( 'Add note', 'wporg-plugins' ); ?></a>
		</p>
		<?php
		$wp_list_table->display( true );
		wp_comment_trashnotice();
	}

	/**
	 * Ajax handler for getting notes.
	 *
	 * @global int $post_id
	 *
	 * @param string $action Action to perform.
	 */
	public static function get_notes( $action ) {
		global $post_id;
		if ( empty( $action ) ) {
			$action = 'get-comments';
		}
		check_ajax_referer( $action );

		if ( empty( $post_id ) && ! empty( $_REQUEST['p'] ) ) {
			$id = absint( $_REQUEST['p'] );
			if ( ! empty( $id ) ) {
				$post_id = $id;
			}
		}

		if ( empty( $post_id ) ) {
			wp_die( -1 );
		}
		$wp_list_table = new Plugin_Comments( [
			'screen' => convert_to_screen( 'edit-comments' ),
		] );

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( -1 );
		}

		$wp_list_table->prepare_items();

		if ( ! $wp_list_table->has_items() ) {
			wp_die( 1 );
		}

		$x = new \WP_Ajax_Response();
		ob_start();
		foreach ( $wp_list_table->items as $comment ) {
			if ( ! current_user_can( 'edit_comment', $comment->comment_ID ) && 0 === $comment->comment_approved ) {
				continue;
			}
			get_comment( $comment );
			$wp_list_table->single_row( $comment );
		}
		$comment_list_item = ob_get_clean();

		$x->add( [
			'what' => 'comments',
			'data' => $comment_list_item,
		] );
		$x->send();
	}
}
