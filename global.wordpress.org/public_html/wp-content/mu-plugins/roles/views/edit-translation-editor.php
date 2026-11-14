<?php
/**
 * Admin view for editing a translation editor.
 *
 * @package Rosetta
 */

$user = get_user_by( 'id', $user_id );
if ( ! $user ) {
	return;
}
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Edit Translation Editor', 'rosetta' ); ?></h1>

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
						<?php esc_html_e( 'Add editor access for:', 'rosetta' ); ?><br>
					</th>
					<td>
						<fieldset id="projects">
							<legend class="screen-reader-text"><span><?php esc_html_e( 'Add editor access for:', 'rosetta' ); ?></span></legend>

							<ul id="projects-list" class="projects-list">
								<li id="project-all"<?php echo $is_all ? ' class="active"' : ''; ?>>
									<label>
										<input type="checkbox"<?php checked( $is_all ); ?>> <?php esc_html_e( 'All projects', 'rosetta' ); ?>
									</label>
									<div class="sub-projects-wrapper">
										<?php esc_html_e( 'The translation editor has validation permissions for all projects, including newly-added projects.', 'rosetta' ); ?>
									</div>
								</li>
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

		<?php submit_button( _x( 'Update', 'translation editor', 'rosetta' ) ); ?>
	</form>
</div>
