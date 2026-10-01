<?php
/**
 * PHPUnit bootstrap file.
 *
 * Loads common.php without WordPress: the functions it calls are stubbed, and users come from a fixture list.
 *
 * @package WordPressdotorg\API\FreeScout
 */

declare( strict_types = 1 );

// Load the project's composer autoloader (PHPUnit, yoast/phpunit-polyfills).
require_once dirname( __DIR__, 5 ) . '/vendor/autoload.php';

// Keeps common.php from loading WordPress.
define( 'ABSPATH', __DIR__ . '/' );
define( 'KB_IN_BYTES', 1024 );
define( 'MINUTE_IN_SECONDS', 60 );

require_once __DIR__ . '/stubs/class-wp-user.php';

/**
 * Finds a fixture user, like WordPress's get_user_by().
 *
 * @param string     $field email, login, or slug.
 * @param string|int $value Value to look for.
 * @return WP_User|false
 */
function get_user_by( string $field, string|int $value ): WP_User|false {
	foreach ( $GLOBALS['freescout_test_users'] ?? array() as $user ) {
		if ( 'email' === $field && 0 === strcasecmp( $user->user_email, (string) $value ) ) {
			return $user;
		}

		if ( in_array( $field, array( 'login', 'slug' ), true ) && $user->user_login === $value ) {
			return $user;
		}
	}

	return false;
}

/**
 * Strips tags, like WordPress's wp_strip_all_tags().
 *
 * @param string $text Text.
 * @return string
 */
function wp_strip_all_tags( string $text ): string {
	return trim( (string) preg_replace( '/<[^>]*>/', '', $text ) );
}

require_once dirname( __DIR__ ) . '/common.php';
