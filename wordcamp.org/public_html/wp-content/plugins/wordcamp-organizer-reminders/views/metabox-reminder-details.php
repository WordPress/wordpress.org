<?php

defined( 'WPINC' ) || die();

/**
 * @var WP_Post $post
 * @var array   $send_where
 */

?>

<h4>Who should this e-mail be sent to?</h4>

<table>
	<tbody>
		<tr>
			<th><input id="wcor_send_organizers" name="wcor_send_where[]" type="checkbox" value="wcor_send_organizers" <?php checked( in_array( 'wcor_send_organizers', $send_where ) ); ?>></th>
			<td colspan="2"><label for="wcor_send_organizers">The organizing team</label></td>
		</tr>

		<tr>
			<th><input id="wcor_send_sponsor_wrangler" name="wcor_send_where[]" type="checkbox" value="wcor_send_sponsor_wrangler" <?php checked( in_array( 'wcor_send_sponsor_wrangler', $send_where ) ); ?>></th>
			<td colspan="2"><label for="wcor_send_sponsor_wrangler">The Sponsor Wrangler</label></td>
		</tr>

		<tr>
			<th><input id="wcor_send_budget_wrangler" name="wcor_send_where[]" type="checkbox" value="wcor_send_budget_wrangler" <?php checked( in_array( 'wcor_send_budget_wrangler', $send_where ) ); ?>></th>
			<td colspan="2"><label for="wcor_send_budget_wrangler">The Budget Wrangler</label></td>
		</tr>

		<tr>
			<th><input id="wcor_send_venue_wrangler" name="wcor_send_where[]" type="checkbox" value="wcor_send_venue_wrangler" <?php checked( in_array( 'wcor_send_venue_wrangler', $send_where ) ); ?>></th>
			<td colspan="2"><label for="wcor_send_venue_wrangler">The Venue Wrangler</label></td>
		</tr>

		<tr>
			<th><input id="wcor_send_speaker_wrangler" name="wcor_send_where[]" type="checkbox" value="wcor_send_speaker_wrangler" <?php checked( in_array( 'wcor_send_speaker_wrangler', $send_where ) ); ?>></th>
			<td colspan="2"><label for="wcor_send_speaker_wrangler">The Speaker Wrangler</label></td>
		</tr>

		<tr>
			<th><input id="wcor_send_food_wrangler" name="wcor_send_where[]" type="checkbox" value="wcor_send_food_wrangler" <?php checked( in_array( 'wcor_send_food_wrangler', $send_where ) ); ?>></th>
			<td colspan="2"><label for="wcor_send_food_wrangler">The Food/Beverage Wrangler</label></td>
		</tr>

		<tr>
			<th><input id="wcor_send_swag_wrangler" name="wcor_send_where[]" type="checkbox" value="wcor_send_swag_wrangler" <?php checked( in_array( 'wcor_send_swag_wrangler', $send_where ) ); ?>></th>
			<td colspan="2"><label for="wcor_send_swag_wrangler">The Swag Wrangler</label></td>
		</tr>

		<tr>
			<th><input id="wcor_send_volunteer_wrangler" name="wcor_send_where[]" type="checkbox" value="wcor_send_volunteer_wrangler" <?php checked( in_array( 'wcor_send_volunteer_wrangler', $send_where ) ); ?>></th>
			<td colspan="2"><label for="wcor_send_volunteer_wrangler">The Volunteer Wrangler</label></td>
		</tr>

		<tr>
			<th><input id="wcor_send_printing_wrangler" name="wcor_send_where[]" type="checkbox" value="wcor_send_printing_wrangler" <?php checked( in_array( 'wcor_send_printing_wrangler', $send_where ) ); ?>></th>
			<td colspan="2"><label for="wcor_send_printing_wrangler">The Printing Wrangler</label></td>
		</tr>

		<tr>
			<th><input id="wcor_send_design_wrangler" name="wcor_send_where[]" type="checkbox" value="wcor_send_design_wrangler" <?php checked( in_array( 'wcor_send_design_wrangler', $send_where ) ); ?>></th>
			<td colspan="2"><label for="wcor_send_design_wrangler">The Design Wrangler</label></td>
		</tr>

		<tr>
			<th><input id="wcor_send_website_wrangler" name="wcor_send_where[]" type="checkbox" value="wcor_send_website_wrangler" <?php checked( in_array( 'wcor_send_website_wrangler', $send_where ) ); ?>></th>
			<td colspan="2"><label for="wcor_send_website_wrangler">The Website Wrangler</label></td>
		</tr>

		<tr>
			<th><input id="wcor_send_social_wrangler" name="wcor_send_where[]" type="checkbox" value="wcor_send_social_wrangler" <?php checked( in_array( 'wcor_send_social_wrangler', $send_where ) ); ?>></th>
			<td colspan="2"><label for="wcor_send_social_wrangler">The Social Media/Publicity Wrangler</label></td>
		</tr>

		<tr>
			<th><input id="wcor_send_a_v_wrangler" name="wcor_send_where[]" type="checkbox" value="wcor_send_a_v_wrangler" <?php checked( in_array( 'wcor_send_a_v_wrangler', $send_where ) ); ?>></th>
			<td colspan="2"><label for="wcor_send_a_v_wrangler">The A/V Wrangler</label></td>
		</tr>

		<tr>
			<th><input id="wcor_send_party_wrangler" name="wcor_send_where[]" type="checkbox" value="wcor_send_party_wrangler" <?php checked( in_array( 'wcor_send_party_wrangler', $send_where ) ); ?>></th>
			<td colspan="2"><label for="wcor_send_party_wrangler">The Party Wrangler</label></td>
		</tr>

		<tr>
			<th><input id="wcor_send_travel_wrangler" name="wcor_send_where[]" type="checkbox" value="wcor_send_travel_wrangler" <?php checked( in_array( 'wcor_send_travel_wrangler', $send_where ) ); ?>></th>
			<td colspan="2"><label for="wcor_send_travel_wrangler">The Travel Wrangler</label></td>
		</tr>

		<tr>
			<th><input id="wcor_send_safety_wrangler" name="wcor_send_where[]" type="checkbox" value="wcor_send_safety_wrangler" <?php checked( in_array( 'wcor_send_safety_wrangler', $send_where ) ); ?>></th>
			<td colspan="2"><label for="wcor_send_safety_wrangler">The Safety Wrangler</label></td>
		</tr>

		<tr>
			<th><input id="wcor_send_mes" name="wcor_send_where[]" type="checkbox" value="wcor_send_mes" <?php checked( in_array( 'wcor_send_mes', $send_where ) ); ?>></th>
			<td colspan="2"><label for="wcor_send_mes">The WordCamp's Multi-Event Sponsors</label></td>
		</tr>

		<tr>
			<th><input id="wcor_send_camera_wrangler" name="wcor_send_where[]" type="checkbox" value="wcor_send_camera_wrangler" <?php checked( in_array( 'wcor_send_camera_wrangler', $send_where ) ); ?>></th>
			<td colspan="2"><label for="wcor_send_camera_wrangler">The Region's Camera Kit Wrangler</label></td>
		</tr>

		<tr>
			<th><input id="wcor_send_custom" name="wcor_send_where[]" type="checkbox" value="wcor_send_custom" <?php checked( in_array( 'wcor_send_custom', $send_where ) ); ?>></th>
			<td><label for="wcor_send_custom">A custom address: </label></td>
			<td><input id="wcor_send_custom_address" name="wcor_send_custom_address" type="text" class="regular-text" value="<?php echo esc_attr( $post->wcor_send_custom_address ); ?>" /></td>
		</tr>
	</tbody>
</table>


<h4>When should this e-mail be sent?</h4>

<table>
	<tbody>
		<tr>
			<th><input id="wcor_send_before" name="wcor_send_when" type="radio" value="wcor_send_before" <?php checked( $post->wcor_send_when, 'wcor_send_before' ); ?>></th>
			<td><label for="wcor_send_before">before the camp starts: </label></td>
			<td>
				<input id="wcor_send_days_before" name="wcor_send_days_before" type="text" class="small-text" value="<?php echo esc_attr( $post->wcor_send_days_before ); ?>" />
				<label for="wcor_send_days_before">days</label>
			</td>
		</tr>

		<tr>
			<th><input id="wcor_send_after" name="wcor_send_when" type="radio" value="wcor_send_after" <?php checked( $post->wcor_send_when, 'wcor_send_after' ); ?>></th>
			<td><label for="wcor_send_after">after the camp ends: </label></td>
			<td>
				<input id="wcor_send_days_after" name="wcor_send_days_after" type="text" class="small-text" value="<?php echo esc_attr( $post->wcor_send_days_after ); ?>" />
				<label for="wcor_send_days_after">days</label>
			</td>
		</tr>

		<tr>
			<th><input id="wcor_send_after_pending" name="wcor_send_when" type="radio" value="wcor_send_after_pending" <?php checked( $post->wcor_send_when, 'wcor_send_after_pending' ); ?>></th>
			<td><label for="wcor_send_after_pending">after added to pending schedule: </label></td>
			<td>
				<input id="wcor_send_days_after_pending" name="wcor_send_days_after_pending" type="text" class="small-text" value="<?php echo esc_attr( $post->wcor_send_days_after_pending ); ?>" />
				<label for="wcor_send_days_after_pending">days</label>
			</td>
		</tr>

		<tr>
			<th><input id="wcor_send_trigger" name="wcor_send_when" type="radio" value="wcor_send_trigger" <?php checked( $post->wcor_send_when, 'wcor_send_trigger' ); ?>></th>
			<td><label for="wcor_send_trigger">on a trigger: </label></td>
			<td>
				<select name="wcor_which_trigger">
					<option value="null" <?php selected( $post->wcor_which_trigger, false ); ?>></option>

					<?php foreach ( $GLOBALS['WCOR_Mailer']->triggers as $trigger_id => $trigger ) : ?>
						<option value="<?php echo esc_attr( $trigger_id ); ?>" <?php selected( $post->wcor_which_trigger, $trigger_id ); ?>><?php echo esc_html( $trigger['name'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
	</tbody>
</table>
