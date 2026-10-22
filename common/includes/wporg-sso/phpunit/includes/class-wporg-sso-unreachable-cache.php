<?php
/**
 * An object cache that is down.
 *
 * @package wporg-sso
 */

declare( strict_types = 1 );

/**
 * Stands in for the object cache when it cannot be reached.
 *
 * `wp_cache_add()` returning false means either "already there" or "cache is
 * unreachable", and the SSO tells them apart by reading the key back. Nothing
 * is stored here, so `add()` and `get()` both fail, which is the shape of an
 * outage rather than a spent token.
 */
class WPOrg_SSO_Unreachable_Cache {

	/**
	 * Refuses to store anything.
	 *
	 * @return bool
	 */
	public function add(): bool {
		return false;
	}

	/**
	 * Reports nothing stored.
	 *
	 * @param string $key   The key to read. Unused.
	 * @param string $group The group to read from. Unused.
	 * @param bool   $force Whether to bypass the runtime cache. Unused.
	 * @param bool   $found Set to false, as nothing was found.
	 * @return bool
	 */
	public function get( $key = '', $group = '', $force = false, &$found = null ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Matches WP_Object_Cache::get().
		$found = false;

		return false;
	}

	/**
	 * Swallows every other cache call an outage would also drop.
	 *
	 * @param string $name      The method called.
	 * @param array  $arguments Its arguments.
	 * @return bool
	 */
	public function __call( $name, $arguments ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Catch-all for the rest of the cache API.
		return false;
	}
}
