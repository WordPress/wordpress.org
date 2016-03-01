<?php
namespace WordPressdotorg\Plugin_Directory\Admin\Metabox;
use WordPressdotorg\Plugin_Directory\Tools;

/**
 * The Internal Notes admin metabox.
 *
 * @package WordPressdotorg\Plugin_Directory\Admin\Metabox
 */
class Notes {

	/**
	 *
	 */
	static function display( $post ) {
		$note = (string) get_post_meta( $post->ID, 'note', true );

		?>
		<div class="view-note hide-if-no-js">
			<?php echo empty( $note ) ? __( 'Add note', 'wporg-plugins' ) : wpautop( $note ); ?>
		</div>
		<div class="edit-note show-if-no-js" style="display: none;">
			<?php wp_nonce_field( 'save-note', 'notce' ); ?>
			<textarea class="note-content" rows="5" style="width: 100%;"><?php echo $note; ?></textarea>
			<p>
				<button type="button" class="button button-primary save-note"><?php _e( 'Save', 'wporg-plugins' ); ?></button>
				<button type="reset" class="button button-secondary cancel-note"><?php _e( 'Cancel', 'wporg-plugins' ); ?></button>
			</p>
		</div>
		<?php
	}
}
