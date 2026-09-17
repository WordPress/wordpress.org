<?php
namespace WordPressdotorg\Make\Badge_Management;
use function WordPressdotorg\Profiles\{ has_badge, get_badges, badge_api, get_users_with_badge, find_user_id };

if ( ! defined( 'WPINC' ) ) {
	die;
}

add_action( 'admin_post_wporg_profile_manage_badges', __NAMESPACE__ . '\manage_badges' );
add_action( 'admin_post_badge_settings', __NAMESPACE__ . '\save_settings' );

function manage_badges() {
	if ( ! current_user_can( MANAGE_BADGES_CAP ) ) {
		wp_die( 'Unauthorized user' );
	}

	check_admin_referer( 'wporg_profile_badges' );

	$badges_to_assign = $_POST['badges'] ?? array();
	$badges_to_assign = array_intersect( (array) $badges_to_assign, get_option( 'wporg_profile_badges', [] ) );
	$badge_action    = $_POST['badge-action'] ?? '';

	if ( empty( $badges_to_assign ) || ! in_array( $badge_action, [ 'add', 'remove' ], true ) ) {
		wp_safe_redirect( admin_url( 'tools.php?page=profile-badges' ) );
		exit;
	}

	$users = isset( $_POST['users'] ) ? array_filter( array_map( 'trim', preg_split( "/[,\n]+/", wp_unslash( $_POST['users'] ) ) ) ) : array();

	$unknown_users = [];
	$operate_on_users = [];
	$skip_users = [];

	foreach ( $users as $user ) {
		$user_id = find_user_id( $user );
		if ( ! $user_id ) {
			$unknown_users[] = $user;
			continue;
		}

		foreach ( $badges_to_assign as $badge ) {
			if ( 'add' === $badge_action ) {
				if ( ! has_badge( $badge, $user_id ) ) {
					$operate_on_users[] = $user_id;
				} else {
					// User has badge already, skip them.
					$skip_users[] = $user_id;
				}
			} elseif ( 'remove' === $badge_action ) {
				if ( has_badge( $badge, $user_id ) ) {
					$operate_on_users[] = $user_id;
				} else {
					// User doesn't have badge, skip them.
					$skip_users[] = $user_id;
				}
			}
		}
	}

	$errors = 0;
	foreach ( $badges_to_assign as $badge ) {
		if ( ! badge_api( $badge_action, $badge, $operate_on_users, $async = false ) ) {
			$errors++;
		}
	}

	$url = wp_get_referer();
	$url = add_query_arg( [
		'updated'   => 'true',
		'processed' => count( $operate_on_users ),
		'skipped'   => count( $skip_users ),
		'unknown'   => count( $unknown_users ),
		'errors'    => $errors,
	], $url );

	wp_safe_redirect( $url );
	exit;
}

function save_settings() {
	if ( ! current_user_can( MANAGE_BADGES_CAP ) ) {
		wp_die( 'Unauthorized user' );
	}

	check_admin_referer( 'badge_settings' );

	// Required cap. The select is disabled for users who can't change it, but a stale form may still post
	// the field, so it's ignored from them rather than refused. For those who can, it's limited to the
	// offered choices and to capabilities they hold.
	$required_cap = isset( $_POST['required_role'] ) ? sanitize_text_field( wp_unslash( $_POST['required_role'] ) ) : null;
	if ( ! current_user_can_change_required_cap() ) {
		$required_cap = null;
	}

	if (
		null !== $required_cap &&
		(
			! array_key_exists( $required_cap, get_required_cap_choices() ) ||
			! current_user_can( $required_cap )
		)
	) {
		wp_die(
			'You are not allowed to set that capability. Nothing was saved.',
			'Unauthorized',
			[
				'response'  => 403,
				'back_link' => true,
			]
		);
	}

	// Note to team.
	$note_to_team = isset( $_POST['note_to_team'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note_to_team'] ) ) : '';
	update_option( 'wporg_profile_badge_note_to_team', $note_to_team );

	if ( null !== $required_cap ) {
		update_option( 'wporg_profile_badge_required_cap', $required_cap );
	}

	// Allowed badges. ( array of slugs )
	if ( is_super_admin() ) {
		$badges = isset( $_POST['badges'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['badges'] ) ) : array();
		$badges = array_intersect( $badges, array_keys( get_badges() ) );
		update_option( 'wporg_profile_badges', $badges );
	}

	wp_safe_redirect( wp_get_referer() );
	exit;
}
