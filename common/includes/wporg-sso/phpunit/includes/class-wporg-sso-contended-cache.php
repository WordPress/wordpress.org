<?php
/**
 * An object cache that another request has just written to.
 *
 * @package wporg-sso
 */

declare( strict_types = 1 );

/**
 * Stands in for the cache when a concurrent hand-off has taken the claim.
 *
 * `wp_cache_add()` failing means either "already there" or "cache is
 * unreachable", and the SSO tells them apart by reading the key back. Here the
 * read succeeds, which is the shape of a lost race rather than an outage.
 *
 * Only the ticket key is contended. Everything else, users and token claims
 * included, has to keep working or the request never reaches the claim.
 */
class WPOrg_SSO_Contended_Cache {

	/**
	 * The cache this stands in front of.
	 *
	 * @var object
	 */
	protected object $inner;

	/**
	 * Wraps the cache the rest of the request keeps using.
	 *
	 * @param object $inner The real object cache.
	 */
	public function __construct( object $inner ) {
		$this->inner = $inner;
	}

	/**
	 * Refuses to add the ticket key, the other request having taken it.
	 *
	 * @param int|string $key    The key to add.
	 * @param mixed      $data   The value to store.
	 * @param string     $group  The group to store it in.
	 * @param int        $expire When the value lapses.
	 * @return bool
	 */
	public function add( int|string $key, mixed $data, string $group = '', int $expire = 0 ): bool {
		if ( $this->is_contended( $key, $group ) ) {
			return false;
		}

		return (bool) $this->inner->add( $key, $data, $group, $expire );
	}

	/**
	 * Reports the ticket key as present, the other request having claimed it.
	 *
	 * @param int|string $key   The key to read.
	 * @param string     $group The group to read from.
	 * @param bool       $force Whether to bypass the runtime cache.
	 * @param bool|null  $found Whether the key was found.
	 * @return mixed
	 */
	public function get( int|string $key, string $group = '', bool $force = false, ?bool &$found = null ): mixed {
		if ( $this->is_contended( $key, $group ) ) {
			$found = true;

			return 1;
		}

		return $this->inner->get( $key, $group, $force, $found );
	}

	/**
	 * Whether a key is the one the other request is racing for.
	 *
	 * @param int|string $key   The key being read or written.
	 * @param string     $group The group it belongs to.
	 * @return bool
	 */
	protected function is_contended( int|string $key, string $group ): bool {
		return WPOrg_SSO::REMOTE_TOKEN_CACHE_GROUP === $group && str_starts_with( (string) $key, 'bounce_' );
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
