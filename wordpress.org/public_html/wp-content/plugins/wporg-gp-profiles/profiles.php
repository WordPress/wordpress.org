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

add_action( 'gp_translation_created', __NAMESPACE__ . '\add_single_translation_activity' );
add_action( 'gp_translation_saved', __NAMESPACE__ . '\add_single_translation_activity', 10, 2 );
add_action( 'gp_translation_set_bulk_action_post', __NAMESPACE__ . '\add_bulk_translation_activity', 10, 4 );

/**
 * Add a activity when strings are suggested and approved.
 */
function add_single_translation_activity( GP_Translation $new_translation, GP_Translation $previous_translation = null ) : void {
	$bulk_request   = gp_post( 'bulk', null );
	$import_request = isset( $_FILES['import-file'] );

	// Bulk actions are handled by `add_bulk_translation_activity()`. Importing is blocked by
	// https://github.com/GlotPress/GlotPress/issues/1467.
	if ( $bulk_request || $import_request ) {
		return;
	}

	$current_user_is_editor = GP::$permission->current_user_can(
		'approve',
		'translation-set',
		$new_translation->translation_set_id
	);

	/*
	 * `GP_Route_Translation::translations_post` saves the translation as `waiting` for all users. Then, if the
	 * user is an editor, it saves it second time to update the status to `current`. We can ignore the first save
	 * since two avoid two bumps for a single action.
	 */
	if ( 'waiting' === $new_translation->status && ! $current_user_is_editor ) {
		$type = 'glotpress_translation_suggested';
	} elseif ( 'current' === $new_translation->status && 'current' !== $previous_translation->status ) {
		$type = 'glotpress_translation_approved';
	} else {
		return;
	}

	$request_body = array(
		'action'    => 'wporg_handle_activity',
		'component' => 'glotpress',
		'type'      => $type,
		'user_id'   => $new_translation->user_id,
	);

	Profiles_API\api( $request_body );
}

/**
 * Add activity when bulk actions are performed.
 */
function add_bulk_translation_activity( GP_Project $project, GP_Locale $locale, GP_Translation_Set $translation_set, array $bulk ) : void {
	switch ( $bulk['action'] ) {
		case 'approve':
			$type = 'glotpress_translation_approved';
			break;

		default:
			return;
	}

	$translation_ids = array();
	foreach ( $bulk['row-ids'] as $row_id ) {
		$parts             = explode( '-', $row_id );
		$translation_ids[] = intval( $parts[1] ?? 0 );
	}

	$translations = GP::$translation->find_many(
		sprintf( 'id IN ( %s )', implode( ',', $translation_ids ) )
	);

	$user_type_counts = array();
	foreach ( $translations as $translation ) {
		// `GP_Route_Translation::_bulk_approve()` tracks which `set_status()` calls succeed, but that information
		// isn't passed to this callback. Check this just in case any of them failed.
		if ( 'current' !== $translation->status ) {
			continue;
		}

		$user_id = $translation->user_id;

		if ( isset( $user_type_counts[ $user_id ][ $type ] ) ) {
			$user_type_counts[ $user_id ][ $type ]++;
		} else {
			$user_type_counts[ $user_id ][ $type ] = 1;
		}
	}

	$activities = array();
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

	$request_body = array(
		'action'     => 'wporg_handle_activity',
		'component'  => 'glotpress',
		'activities' => $activities,
	);

	Profiles_API\api( $request_body );
}
