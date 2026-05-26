<?php
/**
 * In-memory cache backend used by Trac_API tests. Mirrors the get/set surface
 * Trac_API_WPCache exposes. TTL is ignored; tests manipulate keys directly
 * via expire() when they care about expiry semantics.
 *
 * @package WordPressdotorg\Trac
 */

/**
 * Minimal cache for tests.
 */
class Memory_Cache {

	/**
	 * Stored key/value pairs.
	 *
	 * @var array<string, mixed>
	 */
	public $store = array();

	/**
	 * Log of keys passed to get().
	 *
	 * @var array<int, string>
	 */
	public $get_calls = array();

	/**
	 * Log of writes (key + ttl).
	 *
	 * @var array<int, array{key: string, ttl: int}>
	 */
	public $set_calls = array();

	/**
	 * Mimic the wp_cache_get() miss-as-false convention.
	 *
	 * @param string $key Cache key.
	 * @return mixed False on miss.
	 */
	public function get( $key ) {
		$this->get_calls[] = $key;
		return array_key_exists( $key, $this->store ) ? $this->store[ $key ] : false;
	}

	/**
	 * Store a value. TTL is recorded but not enforced.
	 *
	 * @param string $key   Cache key.
	 * @param mixed  $value Cache value.
	 * @param int    $ttl   TTL in seconds.
	 * @return bool
	 */
	public function set( $key, $value, $ttl ) {
		$this->set_calls[]   = array(
			'key' => $key,
			'ttl' => $ttl,
		);
		$this->store[ $key ] = $value;
		return true;
	}

	/**
	 * Force-expire a key (test helper).
	 *
	 * @param string $key Cache key.
	 */
	public function expire( $key ) {
		unset( $this->store[ $key ] );
	}
}
