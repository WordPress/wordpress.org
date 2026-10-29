<?php
/**
 * A set of user-helpers to perform actions on users.
 */

namespace WordPressdotorg\Users;
use WP_User;
use WordPressdotorg\Forums\Plugin as Support_Forums;

/**
 * Block a user from logging in.
 *
 * On WordPress.org we currently tie the user block status to the
 * role in the WordPress.org support forums.
 *
 * @param int|WP_User $user_id The user ID, or WP_User object
 * @param string      $reason  Optional. The reason for blocking the user.
 */
function block_user( $user_id, $reason = '' ) {
	$is_wporg = (
		function_exists( 'is_multisite' ) && is_multisite() &&
		str_ends_with( get_blog_details()->domain, 'wordpress.org' ) &&
		( ! defined( 'IS_WORDCAMP_NETWORK' ) || ! IS_WORDCAMP_NETWORK )
	);

	if ( ! $is_wporg ) {
		// TODO: Other networks.
		return false;
	}

	if ( ! defined( 'WPORG_SUPPORT_FORUMS_BLOGID' ) ) {
		return false;
	}

	if ( ! wp_using_ext_object_cache() ) {
		return false;
	}

	if ( $user_id instanceof WP_User ) {
		$user_id = $user_id->ID;
	}

	if ( ! is_numeric( $user_id ) || $user_id <= 0 ) {
		return false;
	}

	if ( ! _load_and_switch_to_support_forums() ) {
		return false;
	}

	// Add the reason why it's being blocked.
	if ( $reason ) {
		$reason = str_replace( [ '<', '>' ], [ '&lt;', '&gt;' ], $reason );

		$add_note = static function( $text ) use ( $reason ) {
			return "{$reason}\n\n{$text}";
		};

		add_filter( 'wporg_bbp_forum_role_changed_note_text', $add_note );
	}

	// Set the user to blocked. Support forum hooks will take care of the rest.
	$result = bbp_set_user_role( $user_id, bbp_get_blocked_role() );

	if ( isset( $add_note ) ) {
		remove_filter( 'wporg_bbp_forum_role_changed_note_text', $add_note );
	}

	// Reset _load_and_switch_to_support_forums();
	restore_current_blog();

	return $result;
}

/**
 * Add a note to a user's profile.
 *
 * At present user-notes are stored witin the WordPress.org support forums.
 *
 * @param int|WP_User $user_id The user ID, or WP_User object
 * @param string      $note    The note to add.
 * @param int|null    $post    Optional. The URL related to the note.
 * @param int|WP_User $who     Optional. The ID or WP_User of the person adding the
 */
function add_note( $user_id, $note, $post = null, $who = null ) {
	$is_wporg = (
		function_exists( 'is_multisite' ) && is_multisite() &&
		str_ends_with( get_blog_details()->domain, 'wordpress.org' ) &&
		( ! defined( 'IS_WORDCAMP_NETWORK' ) || ! IS_WORDCAMP_NETWORK )
	);

	if ( ! $is_wporg ) {
		// TODO: Other networks.
		return false;
	}

	if ( ! wp_using_ext_object_cache() ) {
		return false;
	}

	if ( $user_id instanceof WP_User ) {
		$user_id = $user_id->ID;
	}

	if ( ! is_numeric( $user_id ) || $user_id <= 0 ) {
		return false;
	}

	$existing_notes = get_user_meta( $user_id, '_wporg_bbp_user_notes', true ) ?: [];

	$site_id = get_current_blog_id();

	// Note: While user notes can be set without loading the Support forums code, at present things like the Audit Log live there, so we load it anyway.
	if ( ! _load_and_switch_to_support_forums() ) {
		return false;
	}

	$result = Support_Forums::get_instance()->user_notes->add_user_note(
		$user_id,
		$note,
		$post,
		0, // $edit_note_id
		$who->ID ?? $who,
		$site_id
	);

	// Reset _load_and_switch_to_support_forums();
	restore_current_blog();

	return $result;
}

/**
 * Load the support forums code, switching to the support forums blog.
 * Internal file use only.
 *
 * @access private
 *
 * @return bool True if loaded, false if not.
 */
function _load_and_switch_to_support_forums() {
	if ( ! defined( 'WPORG_SUPPORT_FORUMS_BLOGID' ) ) {
		return false;
	}

	// Switch first so that bbPress loads with the correct context.
	// This also ensures that the bbp_participant code doesn't kick in.
	switch_to_blog( WPORG_SUPPORT_FORUMS_BLOGID );

	// Load the support forums.. 
	include_once WP_PLUGIN_DIR . '/bbpress/bbpress.php';
	include_once WP_PLUGIN_DIR . '/support-forums/support-forums.php';

	// bbPress roles still aren't quite right, need to switch away and back..
	// This is hacky, but otherwise the bbp_set_user_role() call below will appear to succeed, but no role alteration will actually happen.
	restore_current_blog();
	switch_to_blog( WPORG_SUPPORT_FORUMS_BLOGID );

	return true;
}
