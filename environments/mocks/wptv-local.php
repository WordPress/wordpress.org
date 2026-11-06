<?php
/**
 * Plugin Name: WordPress.tv Local
 * Description: Stands in for what WordPress.com provides WordPress.tv, so the theme runs locally.
 *
 * Jetpack runs in offline mode here, which skips the modules that need a
 * WordPress.com connection; WordPress.tv uses VideoPress and Sharing.
 * Uploads stay local instead of going to VideoPress, so submissions get the
 * GUID of a published video instead of one of their own.
 *
 * @package WordPressdotorg\Environments
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Environments\WordPressTV_Local;

/**
 * VideoPress GUID of State of the Word 2024, given to every local submission.
 */
const SUBMISSION_GUID = 'NxCFxkXD';

/**
 * Jetpack modules WordPress.tv uses that need a connection.
 */
const MODULES = array( 'videopress', 'sharedaddy' );

/**
 * Lets WordPress.tv's Jetpack modules load in offline mode.
 *
 * @param array|false $module Module data.
 * @param string      $slug   Module slug.
 * @return array|false
 */
function allow_offline( array|false $module, string $slug ): array|false {
	if ( $module && in_array( $slug, MODULES, true ) ) {
		$module['requires_connection']      = false;
		$module['requires_user_connection'] = false;
	}

	return $module;
}
add_filter( 'jetpack_get_module', __NAMESPACE__ . '\allow_offline', 10, 2 );

/**
 * Turns WordPress.tv's Jetpack modules on, along with the Blocks module.
 *
 * @param string[] $modules Active module slugs.
 * @return string[]
 */
function activate( array $modules ): array {
	return array_merge( $modules, array( 'blocks' ), MODULES );
}
add_filter( 'jetpack_active_modules', __NAMESPACE__ . '\activate' );

/**
 * Gives a submitted video a published VideoPress GUID.
 *
 * @return string VideoPress GUID.
 */
function submission_guid(): string {
	return SUBMISSION_GUID;
}
add_filter( 'wptv_pre_submission_video_guid', __NAMESPACE__ . '\submission_guid' );
