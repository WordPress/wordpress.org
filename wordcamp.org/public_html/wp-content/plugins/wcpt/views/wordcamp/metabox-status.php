<?php defined( 'WPINC' ) or die(); ?>

<div id="submitpost" class="wcb submitbox">
	<div id="minor-publishing">
		<div id="misc-publishing-actions">
			<div class="misc-pub-section misc-pub-post-status">
				<label>
					WordCamp Status:

					<?php if ( current_user_can( 'manage_network' ) ) : ?>

						<span id="post-status-display">
							<select name="post_status">
								<?php $transitions = WordCamp_Loader::get_valid_status_transitions( $post->post_status ); ?>
								<?php foreach ( WordCamp_Loader::get_post_statuses() as $key => $label ) : ?>
									<?php $status = get_post_status_object( $key ); ?>
									<option value="<?php echo esc_attr( $status->name ); ?>" <?php
										if ( $post->post_status == $status->name ) {
											selected( true );
										} elseif ( ! in_array( $status->name, $transitions ) ) {
											echo ' disabled ';
										}
									?>>
										<?php echo esc_html( $status->label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</span>

					<?php else : ?>

						<span id="post-status-display">
							<?php
								$status = get_post_status_object( $post->post_status );
								echo esc_html( $status->label );
							?>
						</span>

					<?php endif; ?>
				</label>
			</div>
			<div class="clear"></div>
		</div> <!-- #misc-publishing-actions -->

		<div class="clear"></div>
	</div> <!-- #minor-publishing -->
	
	<div id="major-publishing-actions">
		<div id="delete-action">
			<?php if ( current_user_can( 'delete_post', $post->ID ) ) : ?>
				<a class="submitdelete deletion" href="<?php echo get_delete_post_link( $post->ID ); ?>">
					<?php _e( 'Delete', 'wordcamporg' ); ?>
				</a>
			<?php endif; ?>
		</div>

		<div id="publishing-action">
			<?php submit_button( 'Update', 'primary button-large', 'wcpt-update', false, array( 'accesskey' => 'p' ) ); ?>
		</div>

		<div class="clear"></div>
	</div> <!-- #major-publishing-actions -->

</div> <!-- .submitbox -->
