<?php
/**
 * An object cache that remembers what it could not find.
 *
 * @package wporg-sso
 */

declare( strict_types = 1 );

/**
 * Stands in for a drop-in that keeps a miss and then refuses to add over it.
 *
 * Several persistent caches note a miss locally so a repeated read costs
 * nothing, and have `add()` bail on any key the request has already seen.
 * Reading a key before claiming it therefore loses the claim, quietly, and
 * only against that kind of cache.
 *
 * Only the SSO's own group is affected; everything else has to keep working
 * or the request never reaches the claim.
 */
class WPOrg_SSO_Negative_Caching_Cache {

	/**
	 * The cache this stands in front of.
	 *
	 * @var object
	 */
	protected object $inner;

	/**
	 * Keys this request has already failed to find.
	 *
	 * @var array<string, bool>
	 */
	protected array $missed = array();

	/**
	 * Wraps the cache the rest of the request keeps using.
	 *
	 * @param object $inner The real object cache.
	 */
	public function __construct( object $inner ) {
		$this->inner = $inner;
	}

	/**
	 * Reads through, remembering anything the SSO's group did not hold.
	 *
	 * @param int|string $key   The key to read.
	 * @param string     $group The group to read from.
	 * @param bool       $force Whether to bypass the runtime cache.
	 * @param bool|null  $found Whether the key was found.
	 * @return mixed
	 */
	public function get( int|string $key, string $group = '', bool $force = false, ?bool &$found = null ): mixed {
		$value = $this->inner->get( $key, $group, $force, $found );

		if ( WPOrg_SSO::REMOTE_TOKEN_CACHE_GROUP === $group && ! $found ) {
			$this->missed[ (string) $key ] = true;
		}

		return $value;
	}

	/**
	 * Refuses to add over a key this request has already missed.
	 *
	 * @param int|string $key    The key to add.
	 * @param mixed      $data   The value to store.
	 * @param string     $group  The group to store it in.
	 * @param int        $expire When the value lapses.
	 * @return bool
	 */
	public function add( int|string $key, mixed $data, string $group = '', int $expire = 0 ): bool {
		if ( isset( $this->missed[ (string) $key ] ) ) {
			return false;
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
