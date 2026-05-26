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
	 * @param mixed $data Data to encode.
	 * @return string
	 */
	function wp_json_encode( $data ) {
		return json_encode( $data );
	}
}

require_once __DIR__ . '/../trac-notifications-api.php';
require_once __DIR__ . '/../trac-notifications-db.php';
require_once __DIR__ . '/../class-trac-api.php';

require_once __DIR__ . '/class-fake-db.php';
require_once __DIR__ . '/class-memory-cache.php';
require_once __DIR__ . '/class-fake-client.php';
