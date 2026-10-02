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
define( 'FREESCOUT_SECRET', 'test-secret' );

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

/**
 * Encodes JSON, like WordPress's wp_json_encode().
 *
 * @param mixed $data Data.
 * @return string|false
 */
function wp_json_encode( mixed $data ): string|false {
	return json_encode( $data ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- This is the stand-in.
}

/**
 * Registers global cache groups; nothing to do for the array cache.
 *
 * @param array $groups Group names.
 * @return void
 */
function wp_cache_add_global_groups( array $groups ): void {}

/**
 * Adds to an in-memory cache, like WordPress's wp_cache_add(): false if the key exists.
 *
 * @param string $key    Cache key.
 * @param mixed  $data   Value.
 * @param string $group  Group.
 * @param int    $expire Ignored.
 * @return bool
 */
function wp_cache_add( string $key, mixed $data, string $group = '', int $expire = 0 ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WordPress's signature.
	if ( ! empty( $GLOBALS['freescout_test_cache_down'] ) || isset( $GLOBALS['freescout_test_cache'][ $group ][ $key ] ) ) {
		return false;
	}

	$GLOBALS['freescout_test_cache'][ $group ][ $key ] = $data;

	return true;
}

/**
 * Reads from the in-memory cache, like WordPress's wp_cache_get().
 *
 * @param string    $key   Cache key.
 * @param string    $group Group.
 * @param bool      $force Ignored.
 * @param bool|null $found Whether the key was found.
 * @return mixed The value, or false.
 */
function wp_cache_get( string $key, string $group = '', bool $force = false, ?bool &$found = null ): mixed { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- WordPress's signature.
	$found = empty( $GLOBALS['freescout_test_cache_down'] ) && isset( $GLOBALS['freescout_test_cache'][ $group ][ $key ] );

	return $found ? $GLOBALS['freescout_test_cache'][ $group ][ $key ] : false;
}

require_once dirname( __DIR__ ) . '/common.php';
