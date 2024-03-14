<?php

namespace Wporg\TranslationEvents;

use Exception;

class Active_Events_Cache {
	public const CACHE_DURATION = 60 * 60 * 24; // 24 hours.
	private const KEY           = 'translation-events-active-events';

	/**
	 * Cache active events.
	 *
	 * @param Event[] $events Events to cache.
	 *
	 * @throws Exception When it fails to cache events.
	 */
	public function cache( array $events ): void {
		if ( ! wp_cache_set( self::KEY, $events, '', self::CACHE_DURATION ) ) {
			throw new Exception( 'Failed to cache active events' );
		}
	}

	/**
	 * Returns the cached events, or null if nothing is cached.
	 *
	 * @return Event[]|null
	 * @throws Exception When it fails to retrieve cached events.
	 */
	public function get(): ?array {
		$result = wp_cache_get( self::KEY, '', false, $found );
		if ( ! $found ) {
			return null;
		}

		if ( ! is_array( $result ) ) {
			throw new Exception( 'Cached events is not an array, something is wrong' );
		}

		return $result;
	}

	/**
	 * Invalidates the active events cache.
	 *
	 * @throws Exception When it fails to invalidate the cache.
	 */
	public static function invalidate(): void {
		if ( ! wp_cache_delete( self::KEY ) ) {
			throw new Exception( 'Failed to invalidate cached events' );
		}
	}
}
