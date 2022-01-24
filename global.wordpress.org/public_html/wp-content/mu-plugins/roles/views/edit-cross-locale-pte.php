<div class="wrap">
	<h2><?php _e( 'Edit Cross-Locale PTE', 'rosetta' ); ?></h2>

	<?php echo $feedback_message; ?>

	<p><?php
		printf(
			/* translators: %s: WP.org profile link */
			__( 'You are currently editing the user %s.', 'rosetta' ),
			sprintf( '<a href="%1$s">%2$s</a>',
				'https://profiles.wordpress.org/' . $user->user_nicename . '/',
				$user->user_login
			)
		);
	?></p>

	<form method="post">
		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row">
						<?php _e( 'Add cross-locale PTE access for:', 'rosetta' ); ?><br>
					</th>
					<td>
						<fieldset id="projects">
							<legend class="screen-reader-text"><span><?php _e( 'Add cross-locale PTE access for:', 'rosetta' ); ?></span></legend>

							<ul id="projects-list" class="projects-list">
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
		<input type="hidden" name="action" value="update-cross-locale-pte" />
		<input type="hidden" name="user_id" value="<?php echo esc_attr( $user->ID ); ?>" />
		<?php
		wp_nonce_field( 'update-cross-locale-pte_' . $user->ID );
		submit_button( _x( 'Update', 'translation editor', 'rosetta' ) );
		?>
	</form>
</div>
