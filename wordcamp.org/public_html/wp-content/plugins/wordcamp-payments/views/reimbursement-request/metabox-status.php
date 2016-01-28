<?php

namespace WordCamp\Budgets\Reimbursement_Requests;
defined( 'WPINC' ) or die();

?>

<div id="submitpost" class="wcb submitbox">
	<div id="minor-publishing">
		<?php if ( $show_draft_button ) : ?>
			<div id="minor-publishing-actions">
				<div id="save-action">
					<?php submit_button( __( 'Save Draft' ), 'secondary', 'wcbsi-save-draft', false ); ?>
				</div>
			</div>
		<?php endif; ?>

		<div id="misc-publishing-actions">
			<div class="misc-pub-section misc-pub-request-id">
				<?php _e( 'ID:' ) ?>

				<span id="request_id">
					<?php echo esc_html( $request_id ); ?>
				</span>
			</div> <!-- .misc-pub-section -->

			<div class="misc-pub-section misc-pub-requested-by">
				<label><?php _e( 'Requested By:' ) ?>

					<span id="requested_by">
						<?php echo esc_html( $requested_by ); ?>
					</span>
				</label>
			</div> <!-- .misc-pub-section -->

			<div class="misc-pub-section misc-pub-post-status">
				<label>
					<?php _e( 'Status:' ) ?>

					<span id="post-status-display">
						<?php if ( current_user_can( 'manage_network' ) ) : ?>

							<select name="post_status">
								<?php foreach ( $available_statuses as $status_slug => $status_name ) : ?>
									<option value="<?php echo esc_attr( $status_slug ); ?>" <?php selected( $post->post_status, $status_slug ); ?> >
										<?php echo esc_html( $status_name ); ?>
									</option>
								<?php endforeach; ?>
							</select>

						<?php else : ?>

							<?php echo esc_html( $status_name ); ?>

						<?php endif; ?>
					</span>
				</label>
			</div> <!-- .misc-pub-section -->

			<div class="misc-pub-section misc-pub-total-amount-requested">
				<label>
					<?php _e( 'Total Amount Requested:' ) ?>

					<span id="total_amount_requested" class="loading-content">
						<span class="spinner is-active"></span>
					</span>
				</label>
			</div> <!-- .misc-pub-section -->

			<div class="clear"></div>
		</div> <!-- #misc-publishing-actions -->

		<div class="clear"></div>
	</div> <!-- #minor-publishing -->


	<div id="major-publishing-actions">
		<?php if ( $show_submit_button ) : ?>

			<div id="delete-action">
				<?php if ( current_user_can( 'delete_post', $post->ID ) ) : ?>
					<a class="submitdelete deletion" href="<?php echo get_delete_post_link( $post->ID ); ?>">
						<?php echo $delete_text; ?>
					</a>
				<?php endif; ?>
			</div>

			<div id="publishing-action">
				<input name="original_publish" type="hidden" id="original_publish" value="<?php esc_attr( $update_text ) ?>" />
				<?php submit_button(
					$update_text,
					'primary button-large',
					'send-reimbursement-request',
					false,
					array( 'accesskey' => 'p' )
				); ?>
			</div>

			<div class="clear"></div>

		<?php else : ?>

			<p>
				<?php _e( "Requests can't be edited after they've been submitted.", 'wordcamporg' ); ?>
			</p>

		<?php endif; ?>
	</div> <!-- #major-publishing-actions -->

</div> <!-- .submitbox -->
