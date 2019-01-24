<?php
/**
 * @var WP_Post $post
 * @var bool    $current_user_can_edit_request
 * @var string  $submit_text
 * @var string  $submit_note
 * @var string  $submit_note_class
 */
?>

<div id="major-publishing-actions">
	<?php if ( $current_user_can_edit_request ) : ?>

		<?php if ( ! empty( $submit_note ) ) : ?>
			<div class="notice notice-<?php echo esc_attr( $submit_note_class ); ?> inline">
				<?php echo wpautop( esc_html( $submit_note ) ); ?>
			</div>
		<?php endif; ?>

		<?php if ( current_user_can( 'delete_post', $post->ID ) ) : ?>
			<div id="delete-action">
				<a class="submitdelete deletion" href="<?php echo get_delete_post_link( $post->ID ); ?>">
					<?php _e( 'Delete', 'wordcamporg' ); ?>
				</a>
			</div>
		<?php endif; ?>

		<?php if ( \WordCamp_Budgets::can_submit_request( $post ) ) : ?>
			<div id="publishing-action">
				<input name="original_publish" type="hidden" id="original_publish" value="<?php esc_attr( $submit_text ) ?>" />
				<?php submit_button( $submit_text, 'primary button-large', 'wcb-update', false, array( 'accesskey' => 'p' ) ); ?>
			</div>
		<?php endif; ?>

		<div class="clear"></div>

	<?php else : ?>

		<?php _e( 'This request can not be edited.', 'wordcamporg' ); ?>

	<?php endif; ?>
</div> <!-- #major-publishing-actions -->
