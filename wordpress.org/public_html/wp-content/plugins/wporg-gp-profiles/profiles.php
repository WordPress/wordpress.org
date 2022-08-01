<?php
/**
 * Plugin name: GlotPress: Profiles Activity Notifier
 * Description: Adds activity to profiles.wordpress.org when contributors do noteworthy things.
 * Author:      WordPress.org
 * License:     GPLv2 or later
 */

namespace WordPressdotorg\GlotPress\Profiles;

use WordPressdotorg\Profiles as Profiles_API;
use GP, GP_Translation, GP_Project, GP_Locale, GP_Translation_Set;

defined( 'WPINC' ) || die();

/*
 * Requests will always fail when in local environments, unless the dev is proxied. Proxied devs could test
 * locally if they're careful (especially with user IDs), but it's better to test on w.org sandboxes with
 * test accounts. That prevents real profiles from having test data accidentally added to them.
 */
if ( 'local' === wp_get_environment_type() ) {
	return;
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\register_callbacks' );

function register_callbacks() : void {
	// Ignore programmatic actions, only notify for user-initiated actions.
	if ( empty( $_POST ) || ! is_user_logged_in() || ! wp_using_themes() ) {
		return;
	}

	add_action( 'gp_translation_created', __NAMESPACE__ . '\add_single_translation_activity' );
	add_action( 'gp_translation_saved', __NAMESPACE__ . '\add_single_translation_activity', 10, 2 );
	add_action( 'gp_translation_set_bulk_action_post', __NAMESPACE__ . '\add_bulk_translation_activity', 10, 4 );
}

/**
 * Add a activity when strings are suggested and approved.
 */
function add_single_translation_activity( GP_Translation $new_translation, GP_Translation $previous_translation = null ) : void {
	if ( ! should_notify( $new_translation->user_id ) ) {
		return;
	}

	$current_user_is_editor = GP::$permission->current_user_can(
		'approve',
		'translation-set',
		$new_translation->translation_set_id
	);

	$activities            = array();
	$review_status         = gp_post( 'status' );
	$valid_review_statuses = array( 'current', 'rejected', 'fuzzy' );
	$is_reviewing          = $current_user_is_editor && get_current_user_id() !== $new_translation->user_id &&
	                         $review_status && in_array( $review_status, $valid_review_statuses, true );

	/*
	 * Regular user is suggesting a string.
	 *
	 * `GP_Route_Translation::translations_post` saves the translation as `waiting` for all users. Then, if the
	 * user is an editor, it saves it a second time to update the status to `current`. We can ignore the first save,
	 * to avoid two notifications for a single action.
	 */
	if ( 'waiting' === $new_translation->status && ! $current_user_is_editor ) {
		$activities[] = array(
			'component' => 'glotpress',
			'type'      => 'glotpress_translation_suggested',
			'user_id'   => $new_translation->user_id,
		);

	// Editor is approving a suggested string.
	// Avoid sending a notification when a `current` post is re-saved, like when dismissing warnings.
	} elseif ( 'current' === $new_translation->status && isset( $previous_translation->status ) && 'current' !== $previous_translation->status ) {
		$activities[] = array(
			'component' => 'glotpress',
			'type'      => 'glotpress_translation_approved',
			'user_id'   => $new_translation->user_id,
		);
	}

	if ( $is_reviewing ) {
		$activities[] = array(
			'component' => 'glotpress',
			'type'      => 'glotpress_translation_reviewed',
			'user_id'   => get_current_user_id(),
		);
	}

	if ( empty( $activities ) ) {
		return;
	}

	$request_body = array(
		'action'    => 'wporg_handle_activity',
		'component' => 'glotpress',
		'activities' => $activities,
	);

	Profiles_API\api( $request_body );
}

/**
 * Determine if a notification should be sent for the current `gp_translation_{created|saved}` action.
 *
 * This isn't needed for `gp_translation_set_bulk_action_post` callbacks, since that only gets triggered by the
 * normal UI flow.
 *
 * @param mixed $user_id Accept mixed so that callers don't have to check the type. `get_userdata()` will do that.
 */
function should_notify( $user_id ) : bool {
	$notify = true;

	/*
	 * `GP_Route_Translation::translations_post()` runs at `template_redirect:10`, and processes user-initiated
	 * actions. After that point, other code -- like
	 * `WordPressdotorg\GlotPress\Plugin_Directory\Sync\Translation_Sync\sync_translations` -- may create
	 * translations programmatically.
	 */
	if ( did_action( 'get_header' ) || did_action( 'shutdown' ) ) {
		$notify = false;
	}

	$bulk_request   = gp_post( 'bulk', null );
	$import_request = isset( $_FILES['import-file'] );

	// Bulk actions are handled by `add_bulk_translation_activity()`. Importing is blocked by
	// https://github.com/GlotPress/GlotPress/issues/1467.
	if ( $bulk_request || $import_request ) {
		$notify = false;
	}

	if ( ! get_userdata( $user_id ) ) {
		$notify = false;
	}

	return $notify;
}

/**
 * Add activity when bulk actions are performed.
 */
function add_bulk_translation_activity( GP_Project $project, GP_Locale $locale, GP_Translation_Set $translation_set, array $bulk ) : void {
	if ( ! in_array( $bulk['action'], array( 'approve', 'reject', 'fuzzy' ) ) ) {
		return;
	}

	$activities      = array();
	$translation_ids = array();

	foreach ( $bulk['row-ids'] as $row_id ) {
		$parts             = explode( '-', $row_id );
		$translation_ids[] = intval( $parts[1] ?? 0 );
	}

	$translations = GP::$translation->find_many(
		sprintf( 'id IN ( %s )', implode( ',', $translation_ids ) )
	);

	if ( 'approve' === $bulk['action'] ) {
		$activities = get_bulk_approve_activities( $translations );
	}

	$activities[] = array(
		'component' => 'glotpress',
		'type'      => 'glotpress_translation_reviewed',
		'user_id'   => get_current_user_id(),
		'bump'      => count( $translations ),
	);

	$request_body = array(
		'action'     => 'wporg_handle_activity',
		'component'  => 'glotpress',
		'activities' => $activities,
	);

	Profiles_API\api( $request_body );
}

/**
 * Generate the activities payload for bulk approval actions.
 */
function get_bulk_approve_activities( array $translations ) : array {
	$type             = 'glotpress_translation_approved';
	$user_type_counts = array();
	$activities       = array();

	foreach ( $translations as $translation ) {
		// `GP_Route_Translation::_bulk_approve()` tracks which `set_status()` calls succeed, but that information
		// isn't passed to this callback. Check this just in case any of them failed.
		if ( 'current' !== $translation->status ) {
			continue;
		}

		$user_id = $translation->user_id;

		if ( ! get_userdata( $user_id ) ) {
			continue;
		}

		if ( isset( $user_type_counts[ $user_id ][ $type ] ) ) {
			$user_type_counts[ $user_id ][ $type ]++;
		} else {
			$user_type_counts[ $user_id ][ $type ] = 1;
		}
	}

	foreach ( $user_type_counts as $user_id => $types ) {
		foreach ( $types as $type => $count ) {
			$activities[] = array(
				'component' => 'glotpress',
				'type'      => $type,
				'user_id'   => $user_id,
				'bump'      => $count,
			);
		}
	}

	return $activities;
}
