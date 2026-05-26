<?php
/**
 * Default cache backend for Trac_API: thin wrapper over wp_cache_* against
 * the trac_api group. Tests inject their own in-memory implementation.
 *
 * @package WordPressdotorg\Trac
 */

/**
 * Wraps wp_cache_* to expose the get/set surface Trac_API relies on.
 */
class Trac_API_WPCache {

	/**
	 * Get a value from the trac_api cache group.
	 *
	 * @param string $key Cache key.
	 * @return mixed False on miss.
	 */
	public function get( $key ) {
		return wp_cache_get( $key, Trac_API::CACHE_GROUP );
	}

	/**
	 * Set a value in the trac_api cache group.
	 *
	 * @param string $key   Cache key.
	 * @param mixed  $value Cache value.
	 * @param int    $ttl   TTL in seconds.
	 * @return bool
	 */
	public function set( $key, $value, $ttl ) {
		return wp_cache_set( $key, $value, Trac_API::CACHE_GROUP, $ttl );
	}
}
