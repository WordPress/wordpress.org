<div class="wrap">
	<h2><?php _e( 'Edit Translation Editor', 'rosetta' ); ?></h2>

	<?php echo $feedback_message; ?>

	<p><?php
		printf(
			/* translators: %s: username */
			__( 'You are currently editing the user %s.', 'rosetta' ),
			'<em>' . get_user_by( 'id', $user_id )->user_login . '</em>'
		);
	?></p>

	<form method="post">
		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row">
						<?php _e( 'Add editor access for:', 'rosetta' ); ?><br>
					</th>
					<td>
						<fieldset id="projects">
							<legend class="screen-reader-text"><span><?php _e( 'Add editor access for:', 'rosetta' ); ?></span></legend>

							<ul id="projects-list" class="projects-list">
								<li id="project-all" class="active">
									<label>
										<input type="checkbox"<?php checked( in_array( 'all', $project_access_list ) ); ?>> <?php _e( 'All projects', 'rosetta' ); ?>
									</label>
									<div class="sub-projects-wrapper">
										<?php _e( 'The translation editor has validation permissions for all projects, including newly-added projects.', 'rosetta' ); ?>
									</div>
								</li>
								<li id="project-loading" class="loading">
									<?php _e( 'Loading&hellip;', 'rosetta' ); ?>
								</li>
							</ul>
						</fieldset>
						<p class="description"><?php _e( 'Each project includes sub projects and newly-added sub projects.', 'rosetta' ); ?></p>
					</td>
				</tr>
			</tbody>
		</table>

		<input type="hidden" id="project-access-list" name="projects" value="<?php esc_attr( implode( ',', $project_access_list ) ); ?>">
		<input type="hidden" name="action" value="update-translation-editor">
		<input type="hidden" name="user_id" value="<?php echo esc_attr( $user_id ); ?>">
		<?php
		wp_nonce_field( 'update-translation-editor_' . $user_id );
		submit_button( _x( 'Update', 'translation editor', 'rosetta' ) );
		?>
	</form>
</div>
