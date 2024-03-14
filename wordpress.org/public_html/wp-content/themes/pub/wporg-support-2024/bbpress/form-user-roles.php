<?php
/**
 * Modify the roles section of the bbPress profiles.
 * - Hide site roles from Moderators as they can't be set.
 * - Limit the role selection UI for moderators to lower-roles.
 *
 * bbPress doesn't expect moderators to have the ability to change roles as much as we allow,
 * that causes us to need to filter capabilities and the resulting UI.
 */
if ( bbp_is_user_keymaster( get_current_user_id() ) ) {
?><div>
	<label for="role"><?php esc_html_e( 'Blog Role', 'bbpress' ); ?></label>

	<?php bbp_edit_user_blog_role(); ?>

</div>
<?php } ?>

<div>
	<label for="forum-role"><?php esc_html_e( 'Forum Role', 'bbpress' ); ?></label>

	<?php bbp_edit_user_forums_role(); ?>

	<?php
	if ( ! bbp_is_user_keymaster( get_current_user_id() ) ) {
		/*
		 * Capabilities are handled server-side, but the UI isn't reflective of that.
		 * This JS is a quick fix without filtering bbPress functions.
		 */
		?>
		<script>
			jQuery( '#bbp-forums-role').find( '[value="bbp_moderator"],[value="bbp_keymaster"]' ).remove();
		</script>
	<?php } ?>
</div>
