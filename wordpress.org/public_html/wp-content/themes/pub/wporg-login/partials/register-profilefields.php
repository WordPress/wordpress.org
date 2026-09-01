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
		'purpose'   => $user->purpose ?: '',
	];
}

$purpose_options = wporg_login_purpose_options();

?>
<p class="login-website">
	<label for="user_url"><?php esc_html_e( 'Your WordPress site', 'wporg' ); ?></label>
	<input type="url" name="user_fields[url]" id="user_url" class="input" value="<?php echo esc_attr( $fields['url'] ?? '' ); ?>" size="20" placeholder="https://example.com" data-pattern-after-blur="(https?:\/\/)?([a-zA-Z0-9\-]+\.\S+)?" />
	<span class="small"><?php esc_html_e( 'The address of your own WordPress site, if you have one. Leave blank if you don’t.', 'wporg' ); ?></span>
	<span class="invalid-message"><?php _e( 'That URL appears to be invalid.', 'wporg' ); ?></span>
</p>

<p class="login-purpose">
	<label for="user_purpose"><?php esc_html_e( 'Account purpose', 'wporg' ); ?></label>
	<select name="user_fields[purpose]" id="user_purpose" class="input">
		<?php foreach ( $purpose_options as $key => $label ) : ?>
			<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $fields['purpose'] ?? '', $key ); ?>><?php echo esc_html( $label ); ?></option>
		<?php endforeach; ?>
	</select>
	<span class="small"><?php esc_html_e( 'Tell us what you’ll use this account for. This helps us tailor your experience.', 'wporg' ); ?></span>
</p>

<p class="login-location">
	<label for="user_location"><?php esc_html_e( 'Public Location', 'wporg' ); ?></label>
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

<p class="login-biography" aria-hidden="true" style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden;">
	<label for="user_biography"><?php esc_html_e( 'Biography', 'wporg' ); ?></label>
	<input type="text" name="user_fields[biography]" id="user_biography" value="" size="20" autocomplete="off" tabindex="-1" />
</p>

