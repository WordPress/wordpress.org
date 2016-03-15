<?php
namespace WordPressdotorg\Plugin_Directory\Admin\Metabox;
use WordPressdotorg\Plugin_Directory\Admin\Plugin_Comments_List_Table;

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
		$wp_list_table = new Plugin_Comments_List_Table( array(
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
}
