<div id="submitpost" class="submitbox">
	<div id="minor-publishing">
		<div id="misc-publishing-actions">
			<div class="misc-pub-section misc-pub-post-status">
				<label for="post_status"><?php _e( 'Status:' ) ?></label>

				<span id="post-status-display">
					<?php if ( 'paid' == $post->post_status ) : ?>
						<?php _e( 'Paid' ); ?>
					<?php else : ?>
						<?php _e( 'Not Paid' ); ?>
					<?php endif; ?>
				</span>
			</div> <!-- .misc-pub-section -->

			<div class="clear"></div>
		</div> <!-- #misc-publishing-actions -->

		<div class="clear"></div>
	</div> <!-- #minor-publishing -->


	<div id="major-publishing-actions">
		<?php if ( $current_user_can_edit_request ) : ?>

			<div id="delete-action">
				<?php if ( current_user_can( 'delete_post', $post->ID ) ) : ?>
					<a class="submitdelete deletion" href="<?php echo get_delete_post_link( $post->ID ); ?>">
						<?php echo $delete_text; ?>
					</a>
				<?php endif; ?>
			</div>

			<div id="publishing-action">
				<input name="original_publish" type="hidden" id="original_publish" value="<?php esc_attr( $submit_text ) ?>" />
				<?php submit_button( $submit_text, 'primary button-large', 'save', false, array( 'accesskey' => 'p' ) ); ?>
			</div>

			<div class="clear"></div>

		<?php else : ?>

			<p><?php _e( 'Paid requests are closed and cannot be edited.', 'wordcamporg' ); ?></p>

		<?php endif; ?>
	</div> <!-- #major-publishing-actions -->

</div> <!-- .submitbox -->
