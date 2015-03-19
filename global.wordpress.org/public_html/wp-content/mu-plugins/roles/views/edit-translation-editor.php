<div class="wrap">
	<h2><?php _e( 'Edit Translation Editor', 'rosetta' ); ?></h2>

	<?php echo $feedback_message; ?>

	<form method="post">
		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row">
						<?php _e( 'Add editor access for:', 'rosetta' ); ?><br>
						<small style="font-weight:normal"><a href="#clear-all" id="clear-all"><?php _ex( 'Clear All', 'Deselects all checkboxes', 'rosetta' ); ?></a></small>
					</th>
					<td>
						<fieldset>
							<legend class="screen-reader-text"><span><?php _e( 'Add editor access for:', 'rosetta' ); ?></span></legend>
							<p>
								<label for="project-all">
									<input name="projects[]" id="project-all" value="all" type="checkbox"<?php checked( in_array( 'all', $project_access_list ) ); ?>> <?php _e( 'All projects &ndash; If selected, translation editor will have validation permissions for all projects, including newly-added projects.', 'rosetta' ); ?>
								</label>
							</p>
							<?php
							foreach ( $projects as $project ) {
								$project_id = esc_attr( $project->id );
								printf(
									'<p><label for="project-%d"><input name="projects[]" id="project-%d" class="project" value="%d" type="checkbox"%s> %s</label></p>',
									$project_id,
									$project_id,
									$project_id,
									checked( in_array( $project->id, $project_access_list ), true, false ),
									esc_html( $project->name )
								);
							}
							?>
							<p class="description"><?php _e( 'Each project includes sub projects and newly-added sub projects.', 'rosetta' ); ?></p>
						</fieldset>
					</td>
				</tr>
			</tbody>
		</table>

		<input type="hidden" name="action" value="update-translation-editor">
		<input type="hidden" name="user_id" value="<?php echo esc_attr( $user_id ); ?>">
		<?php
		wp_nonce_field( 'update-translation-editor_' . $user_id );
		submit_button( _x( 'Update', 'translation editor', 'rosetta' ) );
		?>
	</form>
</div>
