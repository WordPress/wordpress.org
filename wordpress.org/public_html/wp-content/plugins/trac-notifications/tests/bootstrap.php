<?php
/**
 * Tests bootstrap.
 *
 * The DAO and the caching wrapper are deliberately decoupled from WordPress
 * so they can run under PHPUnit without a full WP test install. We define the
 * minimum constants and helpers the production code expects, then load the
 * production classes and the shared test fakes.
 *
 * @package WordPressdotorg\Trac
 */

if ( ! defined( 'OBJECT' ) ) {
	define( 'OBJECT', 'OBJECT' );
}
if ( ! defined( 'OBJECT_K' ) ) {
	define( 'OBJECT_K', 'OBJECT_K' );
}
if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}
if ( ! defined( 'ARRAY_N' ) ) {
	define( 'ARRAY_N', 'ARRAY_N' );
}

if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 60 * MINUTE_IN_SECONDS );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 24 * HOUR_IN_SECONDS );
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * Minimal polyfill for wp_json_encode() used by Trac_API::ticket_cache_key.
	 *
	 * @param mixed $data Data to encode.
	 * @return string
	 */
	function wp_json_encode( $data ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- This is the wp_json_encode polyfill.
		return json_encode( $data );
	}
}

require_once __DIR__ . '/includes/class-test-cache.php';

if ( ! function_exists( 'wp_cache_get' ) ) {
	/**
	 * Test polyfill for wp_cache_get(). State lives in Test_Cache.
	 *
	 * @param string $key   Cache key.
	 * @param string $group Cache group.
	 * @return mixed False on miss.
	 */
	function wp_cache_get( $key, $group = '' ) {
		return Test_Cache::get( $key, $group );
	}
}

if ( ! function_exists( 'wp_cache_set' ) ) {
	/**
	 * Test polyfill for wp_cache_set(). State lives in Test_Cache.
	 *
	 * @param string $key   Cache key.
	 * @param mixed  $value Value.
	 * @param string $group Cache group.
	 * @param int    $ttl   TTL in seconds (recorded, not enforced).
	 * @return bool
	 */
	function wp_cache_set( $key, $value, $group = '', $ttl = 0 ) {
		return Test_Cache::set( $key, $value, $group, $ttl );
	}
}

require_once __DIR__ . '/../trac-notifications-api.php';
require_once __DIR__ . '/../trac-notifications-db.php';
require_once __DIR__ . '/../class-trac-api.php';

require_once __DIR__ . '/includes/class-fake-db.php';
require_once __DIR__ . '/includes/class-fake-client.php';
