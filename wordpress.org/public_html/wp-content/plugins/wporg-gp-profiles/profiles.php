<?php
/**
 * Plugin name: GlotPress Profiles Activity Notifier
 * Description: Adds activity to profiles.wordpress.org when contributors do noteworthy things.
 * Author:      WordPress.org
 * License:     GPLv2 or later
 */

namespace WordPressdotorg\GlotPress\Profiles;

use WordPressdotorg\Profiles as Profiles_API;
use GP_Translation;

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
add_action( 'gp_translation_saved', __NAMESPACE__ . '\add_translation_activity' );

/**
 * Add a activity when strings are suggested and approved.
 */
function add_translation_activity( GP_Translation $translation ) : void {
	if ( 'waiting' === $translation->status ) {
		$type = 'glotpress_translation_suggested';
	} elseif ( 'current' === $translation->status ) {
		$type = 'glotpress_translation_approved';
	} else {
		return;
	}

	$request_body = array(
		'action'    => 'wporg_handle_activity',
		'component' => 'glotpress',
		'type'      => $type,
		'user_id'   => $translation->user_id,
	);

	Profiles_API\api( $request_body );
}
