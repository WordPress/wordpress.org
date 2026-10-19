<?php
/**
 * Plugin Name: Jetpack Offline Subscriptions
 * Description: Loads Jetpack's Subscriptions module without a WordPress.com connection, so subscription forms render locally.
 *
 * Subscribing still needs WordPress.com; the forms only render.
 *
 * @package WordPressdotorg\Environments
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Environments\Jetpack_Offline_Subscriptions;

add_filter( 'jetpack_get_module', __NAMESPACE__ . '\allow_offline', 10, 2 );
add_filter( 'jetpack_active_modules', __NAMESPACE__ . '\activate' );

/**
 * Lets the Subscriptions module load in Jetpack's offline mode.
 *
 * @param array|false $module Module data.
 * @param string      $slug   Module slug.
 * @return array|false
 */
function allow_offline( array|false $module, string $slug ): array|false {
	if ( 'subscriptions' === $slug && $module ) {
		$module['requires_connection']      = false;
		$module['requires_user_connection'] = false;
	}

	return $module;
}

/**
 * Turns the Subscriptions module on, along with the Blocks module its form needs.
 *
 * @param string[] $modules Active module slugs.
 * @return string[]
 */
function activate( array $modules ): array {
	return array_merge( $modules, array( 'blocks', 'subscriptions' ) );
}
