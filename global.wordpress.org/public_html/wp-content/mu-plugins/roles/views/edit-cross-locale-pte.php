<?php
/**
 * Admin view for editing a cross-locale project translation editor.
 *
 * @package Rosetta
 */

?>
<div class="wrap">
	<h2><?php esc_html_e( 'Edit Cross-Locale PTE', 'rosetta' ); ?></h2>

	<?php echo $feedback_message; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Notice markup assembled by get_feedback_message() from escaped parts. ?>

	<p><?php
		printf(
			/* translators: %s: WP.org profile link */
			esc_html__( 'You are currently editing the user %s.', 'rosetta' ),
			sprintf( '<a href="%1$s">%2$s</a>',
				esc_url( 'https://profiles.wordpress.org/' . $user->user_nicename . '/' ),
				esc_html( $user->user_login )
			)
		);
	?></p>

	<form method="post">
		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row">
						<?php esc_html_e( 'Add cross-locale PTE access for:', 'rosetta' ); ?><br>
					</th>
					<td>
						<fieldset id="projects">
							<legend class="screen-reader-text"><span><?php esc_html_e( 'Add cross-locale PTE access for:', 'rosetta' ); ?></span></legend>

							<ul id="projects-list" class="projects-list">
								<li id="project-loading" class="loading">
									<?php esc_html_e( 'Loading&hellip;', 'rosetta' ); ?>
								</li>
							</ul>
						</fieldset>
						<p class="description"><?php esc_html_e( 'Each project includes sub projects and newly-added sub projects.', 'rosetta' ); ?></p>
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
