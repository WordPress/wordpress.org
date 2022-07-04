<?php
/**
 * Plugin name: GlotPress: Profiles Activity Notifier
 * Description: Adds activity to profiles.wordpress.org when contributors do noteworthy things.
 * Author:      WordPress.org
 * License:     GPLv2 or later
 */

namespace WordPressdotorg\GlotPress\Profiles;

use WordPressdotorg\Profiles as Profiles_API;
use GP, GP_Translation;

defined( 'WPINC' ) || die();

/*
 * Requests will always fail when in local environments, unless the dev is proxied. Proxied devs could test
 * locally if they're careful (especially with user IDs), but it's better to test on w.org sandboxes with
 * test accounts. That prevents real profiles from having test data accidentally added to them.
 */
if ( 'local' === wp_get_environment_type() ) {
	return;
}

add_action( 'gp_translation_created', __NAMESPACE__ . '\add_translation_activity' );
add_action( 'gp_translation_saved', __NAMESPACE__ . '\add_translation_activity', 10, 2 );

/**
 * Add a activity when strings are suggested and approved.
 */
function add_translation_activity( GP_Translation $new_translation, GP_Translation $previous_translation = null ) : void {
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
