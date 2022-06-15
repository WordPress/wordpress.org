<?php
/**
 * The user profile fields template.
 *
 * This template expects that the global $user variable is set.
 *
 * @package wporg-login
 */

if ( empty( $fields ) ) {
	$user = get_user_by( 'id', get_current_user_id() );

	$fields = [
		'url'       => $user->user_url ?: '',
		'from'      => $user->from ?: '',
		'occ'       => $user->occ ?: '',
		'interests' => $user->interests ?: '',
	];
}

?>
<p class="login-website">
	<label for="user_website"><?php _e( 'Website', 'wporg' ); ?></label>
	<input type="text" name="user_fields[url]" id="user_url" class="input" value="<?php echo esc_attr( $fields['url'] ?? '' ); ?>" size="20" placeholder="https://" data-pattern-after-blur="(https?://)?([a-zA-Z0-9-]+\.\S+)?" />
	<span class="invalid-message"><?php _e( 'That URL appears to be invalid.', 'wporg' ); ?></span>
</p>

<p class="login-location">
	<label for="user_location"><?php _e( 'Location', 'wporg' ); ?></label>
	<input type="text" name="user_fields[from]" id="user_location" class="input" value="<?php echo esc_attr( $fields['from'] ?? '' ); ?>" size="20" />
</p>

<p class="login-occupation">
	<label for="user_occupation"><?php _e( 'Occupation', 'wporg' ); ?></label>
	<input type="text" name="user_fields[occ]" id="user_occupation" class="input" value="<?php echo esc_attr( $fields['occ'] ?? '' ); ?>" size="20" />
</p>

<p class="login-interests">
	<label for="user_interests"><?php _e( 'Interests', 'wporg' ); ?></label>
	<input type="text" name="user_fields[interests]" id="user_interests" class="input" value="<?php echo esc_attr( $fields['interests'] ?? '' ); ?>" size="20" />
</p>

