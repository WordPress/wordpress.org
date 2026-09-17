<?php
/**
 * An object cache that remembers what the SSO stored, and for how long.
 *
 * @package wporg-sso
 */

declare( strict_types = 1 );

/**
 * Stands in front of the real cache, noting the lifetime of each claim.
 *
 * The expiry the SSO passes is a security parameter, and `wp_cache_add()`
 * offers no way to read it back once stored.
 */
class WPOrg_SSO_Recording_Cache {

	/**
	 * The cache this stands in front of.
	 *
	 * @var object
	 */
	protected object $inner;

	/**
	 * Lifetimes the SSO asked for, keyed by cache key.
	 *
	 * @var int[]
	 */
	public array $expiries = array();

	/**
	 * Wraps the cache the rest of the request keeps using.
	 *
	 * @param object $inner The real object cache.
	 */
	public function __construct( object $inner ) {
		$this->inner = $inner;
	}

	/**
	 * Notes the lifetime of anything stored in the SSO's group.
	 *
	 * @param int|string $key    The key to add.
	 * @param mixed      $data   The value to store.
	 * @param string     $group  The group to store it in.
	 * @param int        $expire When the value lapses.
	 * @return bool
	 */
	public function add( int|string $key, mixed $data, string $group = '', int $expire = 0 ): bool {
		if ( WPOrg_SSO::REMOTE_TOKEN_CACHE_GROUP === $group ) {
			$this->expiries[ (string) $key ] = $expire;
		}

		return (bool) $this->inner->add( $key, $data, $group, $expire );
	}

	/**
	 * Passes the rest of the cache API straight through.
	 *
	 * @param string $name      The method called.
	 * @param array  $arguments Its arguments.
	 * @return mixed
	 */
	public function __call( string $name, array $arguments ): mixed {
		return $this->inner->$name( ...$arguments );
	}
}
