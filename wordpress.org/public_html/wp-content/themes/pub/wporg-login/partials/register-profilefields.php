<?php
/**
 * The user profile fields template.
 *
 * This template expects that the global $user variable is set.
 *
 * @package wporg-login
 */

$fields = array(
	'url'       => wp_get_current_user()->user_url ?: '',
	'from'      => get_user_meta( get_current_user_id(), 'from', true ) ?: '',
	'occ'       => get_user_meta( get_current_user_id(), 'occ', true ) ?: '',
	'interests' => get_user_meta( get_current_user_id(), 'interests', true ) ?: '',
);

?>
<p class="login-website">
	<label for="user_website"><?php _e( 'Website', 'wporg-login' ); ?></label>
	<input type="text" name="user_fields[url]" id="user_url" class="input" value="<?php echo esc_attr( $fields['url'] ); ?>" size="20" placeholder="https://" />
</p>

<p class="login-location">
	<label for="user_location"><?php _e( 'Location', 'wporg-login' ); ?></label>
	<input type="text" name="user_fields[from]" id="user_location" class="input" value="<?php echo esc_attr( $fields['from'] ); ?>" size="20" />
</p>

<p class="login-occupation">
	<label for="user_occupation"><?php _e( 'Occupation', 'wporg-login' ); ?></label>
	<input type="text" name="user_fields[occ]" id="user_occupation" class="input" value="<?php echo esc_attr( $fields['occ'] ); ?>" size="20" />
</p>

<p class="login-interests">
	<label for="user_interests"><?php _e( 'Interests', 'wporg-login' ); ?></label>
	<input type="text" name="user_fields[interests]" id="user_interests" class="input" value="<?php echo esc_attr( $fields['interests'] ); ?>" size="20" />
</p>

