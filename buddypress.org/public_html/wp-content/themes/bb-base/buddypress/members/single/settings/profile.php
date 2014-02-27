<?php do_action( 'bp_before_member_settings_template' ); ?>

<form action="<?php echo bp_displayed_user_domain() . bp_get_settings_slug() . '/profile'; ?>" method="post" class="standard-form" id="settings-form">

	<?php if ( bp_has_profile( array(
				'user_id'                => bp_displayed_user_id(),
				'profile_group_id'       => false,
				'hide_empty_groups'      => false,
				'hide_empty_fields'      => false,
				'fetch_fields'           => true,
				'fetch_field_data'       => false,
				'fetch_visibility_level' => true,
				'exclude_groups'         => false,
				'exclude_fields'         => false
			) ) ) : ?>

		<?php while ( bp_profile_groups() ) : bp_the_profile_group(); ?>

			<table class="notification-settings" id="xprofile-settings-<?php bp_the_profile_group_slug(); ?>">
				<thead>
					<tr>
						<th class="icon">&nbsp;</th>
						<th class="title"><?php bp_the_profile_group_name(); ?></th>
						<th class="title"><?php _e( 'Visibility', 'buddypress' ); ?></th>
						
					</tr>
				</thead>

				<tbody>

					<?php while ( bp_profile_fields() ) : bp_the_profile_field(); ?>

						<tr <?php bp_field_css_class(); ?>>
							<td>&nbsp;</td>
							<td><?php bp_the_profile_field_name(); ?></td>
							<td><?php bp_xprofile_settings_visibility_select(); ?></td>
						</tr>

						<?php do_action( 'bp_profile_field_item' ); ?>

					<?php endwhile; ?>

				</tbody>
			</table>

		<?php endwhile; ?>

		<?php do_action( 'bp_profile_field_buttons' ); ?>

	<?php endif; ?>

	<div class="submit">
		<input id="submit" type="submit" name="xprofile-settings-submit" value="<?php _e( 'Save Settings', 'buddypress' ); ?>" class="auto" />
	</div>

	<?php do_action( 'bp_core_xprofile_settings_after_submit' ); ?>

	<?php wp_nonce_field( 'bp_xprofile_settings' ); ?>

	<input type="hidden" name="field_ids" id="field_ids" value="<?php bp_the_profile_group_field_ids(); ?>" />

</form>

<?php do_action( 'bp_after_member_settings_template' ); ?>
