<?php
/**
 * Static in-memory state backing the wp_cache_get/set polyfills in
 * tests/bootstrap.php. Tests read $store directly for assertions and call
 * reset() in setUp() to start each test from a clean cache.
 *
 * @package WordPressdotorg\Trac
 */

/**
 * Test-scoped wp_cache backing store. All state is class-static.
 */
class Test_Cache {

	/**
	 * Stored values, keyed by "group:key".
	 *
	 * @var array<string, mixed>
	 */
	public static $store = array();

	/**
	 * Log of get() requests, keyed by "group:key".
	 *
	 * @var array<int, string>
	 */
	public static $get_calls = array();

	/**
	 * Log of set() requests as { key, ttl }.
	 *
	 * @var array<int, array{key: string, ttl: int}>
	 */
	public static $set_calls = array();

	/**
	 * Clear all state. Call from test setUp() so each test starts clean.
	 */
	public static function reset() {
		self::$store     = array();
		self::$get_calls = array();
		self::$set_calls = array();
	}

	/**
	 * Build the composite key the polyfill stores under.
	 *
	 * @param string $key   Cache key.
	 * @param string $group Cache group.
	 * @return string
	 */
	public static function key( $key, $group ) {
		return $group . ':' . $key;
	}

	/**
	 * Polyfill implementation of wp_cache_get(): miss returns false.
	 *
	 * @param string $key   Cache key.
	 * @param string $group Cache group.
	 * @return mixed
	 */
	public static function get( $key, $group ) {
		$k                 = self::key( $key, $group );
		self::$get_calls[] = $k;
		return array_key_exists( $k, self::$store ) ? self::$store[ $k ] : false;
	}

	/**
	 * Polyfill implementation of wp_cache_set(): TTL is recorded but not enforced.
	 *
	 * @param string $key   Cache key.
	 * @param mixed  $value Value.
	 * @param string $group Cache group.
	 * @param int    $ttl   TTL in seconds.
	 * @return bool
	 */
	public static function set( $key, $value, $group, $ttl ) {
		$k                 = self::key( $key, $group );
		self::$set_calls[] = array(
			'key' => $k,
			'ttl' => $ttl,
		);
		self::$store[ $k ] = $value;
		return true;
	}

	/**
	 * Force-expire a key (test helper).
	 *
	 * @param string $key   Cache key.
	 * @param string $group Cache group.
	 */
	public static function expire( $key, $group ) {
		unset( self::$store[ self::key( $key, $group ) ] );
	}
}
