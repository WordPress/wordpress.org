<?php
/**
 * @var WP_Post             $post
 * @var WCP_Payment_Request $this
 * @var bool                $current_user_can_edit_request
 * @var string              $submit_text
 * @var string              $submit_note
 * @var string              $submit_note_class
 * @var bool                $date_vendor_paid_readonly
 * @var string              $incomplete_notes
 * @var bool                $incomplete_readonly
 */
?>
<div id="submitpost" class="wcb submitbox">
	<div id="minor-publishing">

		<?php if ( $current_user_can_edit_request && ! current_user_can( 'manage_network' ) ) : ?>
		<div id="minor-publishing-actions">
			<div id="save-action">
				<?php submit_button( esc_html__( 'Save Draft', 'wordcamporg' ), 'button', 'wcb-save-draft', false ); ?>
				<span class="spinner"></span>
			</div>
			<div class="clear"></div>
		</div>
		<?php endif; ?>

		<div id="misc-publishing-actions">
			<div class="misc-pub-section">
				<?php _e( 'ID:', 'wordcamporg' ); ?>
				<span>
					<?php echo esc_html( $this->get_field_value( 'request_id', $post ) ); ?>
				</span>
			</div>

			<div class="misc-pub-section">
				<?php _e( 'Requested By:', 'wordcamporg' ); ?>
				<span>
					<?php echo esc_html( $this->get_field_value( 'requester', $post ) ); ?>
				</span>
			</div>

			<?php if ( $post->post_status != 'auto-draft' ) : ?>
			<div class="misc-pub-section">
				<?php $this->render_text_input( $post, 'Date Vendor was Paid', 'date_vendor_paid', '', 'date', array(), $date_vendor_paid_readonly, false ); ?>
			</div>

			<div class="misc-pub-section misc-pub-post-status">
				<label>
					<?php _e( 'Status:' ) ?>

					<span id="post-status-display">
						<?php if ( current_user_can( 'manage_network' ) ) : ?>

							<select id="wcb_status" name="post_status">
								<?php foreach ( WCP_Payment_Request::get_post_statuses() as $status ) : ?>
									<?php $status = get_post_status_object( $status ); ?>
									<option value="<?php echo esc_attr( $status->name ); ?>" <?php selected( $post->post_status, $status->name ); ?> >
										<?php echo esc_html( $status->label ); ?>
									</option>
								<?php endforeach; ?>
							</select>

						<?php else : ?>

							<?php $status = get_post_status_object( $post->post_status ); ?>
							<?php echo esc_html( $status->label ); ?>
							<input type="hidden" name="post_status" value="<?php echo esc_attr( $post->post_status ); ?>" />

						<?php endif; ?>
					</span>
				</label>
			</div>
			<?php endif; ?>

			<div class="misc-pub-section hide-if-js wcb-mark-incomplete-notes">
				<label for="wcp_mark_incomplete_notes">
					<?php esc_html_e( 'What information is needed?', 'wordcamporg' ); ?>
				</label>

				<textarea
					id="wcp_mark_incomplete_notes"
					name="wcp_mark_incomplete_notes"
					class="large-text"
					rows="5"
					placeholder="<?php esc_html_e( 'Need to attach receipt, etc', 'wordcamporg' ); ?>"
					<?php echo $incomplete_readonly; ?>
				><?php
					echo esc_textarea( $incomplete_notes );
				?></textarea>
			</div>

			<div class="clear"></div>
		</div> <!-- #misc-publishing-actions -->

		<div class="clear"></div>
	</div> <!-- #minor-publishing -->

	<?php require dirname( __DIR__ ) . '/wordcamp-budgets/major-publishing-actions.php'; ?>

</div> <!-- .submitbox -->
