<div class="wrap">
	<h2>
		<?php
		_e( 'Translation Editors', 'rosetta' );

		if ( ! empty( $_REQUEST['s'] ) ) {
			echo '<span class="subtitle">' . sprintf( __( 'Search results for &#8220;%s&#8221;', 'rosetta' ), esc_html( wp_unslash( $_REQUEST['s'] ) ) ) . '</span>';
		}
		?>
	</h2>

	<?php echo $feedback_message; ?>

	<form method="get">
		<input type="hidden" name="page" value="translation-editors">
		<?php $list_table->search_box( __( 'Search Translation Editors', 'rosetta' ), 'rosetta' ); ?>
	</form>

	<?php $list_table->views(); ?>

	<form method="post">
		<?php $list_table->display(); ?>
	</form>

	<?php if ( current_user_can( Rosetta_Roles::MANAGE_TRANSLATION_EDITORS_CAP ) ) : ?>
		<h3><?php _e( 'Add Translation Editor', 'rosetta' ); ?></h3>
		<p><?php _e( 'Enter the email address or username of an existing user on wordpress.org.', 'rosetta' ); ?></p>
		<form action="" method="post">
			<table class="form-table">
				<tr>
					<th scope="row"><label for="user"><?php _e( 'E-mail or Username', 'rosetta' ); ?></label></th>
					<td><input type="text" class="regular-text" name="user" id="user"></td>
				</tr>
				<tr>
					<th scope="row"><label for="user"><?php _e( 'Add editor access for:', 'rosetta' ); ?></label></th>
					<td>
						<fieldset>
							<legend class="screen-reader-text"><?php _e( 'Add editor access for:', 'rosetta' ); ?></legend>
							<label for="all-projects" >
								<input type="radio" name="projects" value="all" id="all-projects" checked="checked"> <?php _e( 'All projects &ndash; If selected, translation editor will have validation permissions for all projects, including newly-added projects.', 'rosetta' ); ?>
							</label>
							<br>
							<label for="custom-projects">
								<input type="radio" name="projects" value="custom" id="custom-projects"> <?php _e( 'Custom &ndash; After the user is added you will be redirected to set the projects.', 'projects', 'rosetta' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>
			</table>
			<input type="hidden" name="action" value="add-translation-editor">
			<?php wp_nonce_field( 'add-translation-editor', '_nonce_add-translation-editor' ) ?>
			<?php submit_button( __( 'Add Translation Editor', 'rosetta' ) ); ?>
		</form>
	<?php endif; ?>
</div>
