<?php

/**
 * Allow moderators to mark users as spammers
 *
 * @author johnjamesjacoby
 * @since 1.0.4
 * @return If not spamming a user
 */
function bbporg_make_user_spammer() {

	// Bail if not capable
	if ( ! current_user_can( 'moderate' ) )
		return;

	// Bail if not viewing a user
	if ( ! bbp_is_single_user() )
		return;

	// Bail if no refresh
	if ( empty( $_GET['spammer'] ) || ( 'true' != $_GET['spammer'] ) )
		return;

	// Get the user ID
	$user_id = bbp_get_displayed_user_id();

	// Bail if empty and protect super admins
	if ( empty( $user_id ) || is_super_admin( $user_id ) )
		return;

	global $wpdb;

	// Make array of post types to mark as spam
	$post_types  = array( bbp_get_topic_post_type(), bbp_get_reply_post_type() );
	$post_types  = "'" . implode( "', '", $post_types ) . "'";
	$status      = bbp_get_public_status_id();

	// Loop through blogs and remove their posts
	// Get topics and replies
	$posts = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_author = {$user_id} AND post_status = '{$status}' AND post_type IN ({$post_types})" );

	// Loop through posts and spam them
	if ( !empty( $posts ) ) {
		foreach ( $posts as $post_id ) {

			// The routines for topics ang replies are different, so use the
			// correct one based on the post type
			switch ( get_post_type( $post_id ) ) {
				case bbp_get_topic_post_type() :
					bbp_spam_topic( $post_id );
					break;

				case bbp_get_reply_post_type() :
					bbp_spam_reply( $post_id );
					break;
			}
		}
	}

	// Delete the users description from their profile
	$user_description = get_user_meta( $user_id, 'description');
	if ( !empty( $user_description ) ) {
		delete_user_meta( $user_id, 'description' );
	}

	// Block the user
	bbp_set_user_role( $user_id, bbp_get_blocked_role() );

	// Redirect without _GET
	wp_safe_redirect( bbp_get_user_profile_url( $user_id ) );
	die;
}
add_action( 'bbp_template_redirect', 'bbporg_make_user_spammer' );
