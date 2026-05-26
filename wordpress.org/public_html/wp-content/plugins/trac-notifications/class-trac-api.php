<?php
/**
 * Trac read API with caching and circuit breaker.
 *
 * Wraps Trac_Notifications_HTTP_Client so wp.org-side consumers (HTTP gateway,
 * MCP abilities, internal scripts) share an in-process API that:
 *
 *   - caches Trac responses in memcached with separate fresh and stale TTLs;
 *   - serves stale data when Trac is unreachable; and
 *   - trips a per-trac circuit breaker that keeps a degraded Trac from being
 *     hammered by retries.
 *
 * Per-user state stays on Trac_Notifications_HTTP_Client; only shared,
 * cacheable reads go through this class.
 *
 * @package WordPressdotorg\Trac
 */

/**
 * Trac read API with caching and per-trac circuit breaker.
 */
class Trac_API {

	const SUPPORTED = array( 'core', 'meta' );

	const CACHE_GROUP  = 'trac_api';
	const FRESH_TTL    = 5 * MINUTE_IN_SECONDS;
	const STALE_TTL    = DAY_IN_SECONDS;
	const BREAKER_TTL  = 5 * MINUTE_IN_SECONDS;
	const NEGATIVE_TTL = MINUTE_IN_SECONDS;

	/**
	 * Sentinel stored in cache to represent a known-missing record. Chosen so
	 * the cache backend's "miss" sentinel (typically `false`) is distinct from
	 * "we asked Trac and got null". A bare string is impossible to confuse
	 * with a real Trac payload (always an associative array).
	 */
	const NULL_SENTINEL = '__trac_api_null__';

	/**
	 * Factory callable: fn( string $trac ): object|null. Receives the Trac
	 * slug ('core' / 'meta') and returns a client whose methods match
	 * Trac_Notifications_DB. Returns null when the client cannot be
	 * constructed (e.g. missing credentials).
	 *
	 * @var callable
	 */
	protected $client_factory;

	/**
	 * Whether the most recent cached_call() served data from the stale layer.
	 *
	 * @var bool
	 */
	protected $last_stale = false;

	/**
	 * Constructor.
	 *
	 * @param callable|null $client_factory Factory returning a Trac HTTP client per trac. Default: live HTTP client.
	 */
	public function __construct( $client_factory = null ) {
		if ( null === $client_factory ) {
			$client_factory = array( $this, 'default_client_factory' );
		}
		$this->client_factory = $client_factory;
	}

	/**
	 * Fetch a ticket with comments, changelog, attachments, and custom fields.
	 *
	 * The mixed return is intentional — callers should treat `null` and `false`
	 * the same when deciding whether to serve data and only differentiate
	 * when generating an HTTP status (404 vs 503):
	 *
	 *     $result = $api->get_ticket( 'core', 42 );
	 *     if ( ! is_array( $result ) ) {
	 *         // No data. $result === null → ticket missing (404).
	 *         //          $result === false → trac unreachable or bad input (503/400).
	 *     }
	 *
	 * @param string $trac      Trac slug ('core' or 'meta').
	 * @param int    $ticket_id Ticket id.
	 * @param array  $opts      Forwarded to Trac_Notifications_DB::get_trac_ticket_full().
	 * @return array|null|false Array on success; null when the ticket does not exist
	 *                          (negatively cached); false when the input is invalid or
	 *                          Trac is unreachable with no stale cache.
	 */
	public function get_ticket( $trac, $ticket_id, $opts = array() ) {
		if ( ! in_array( $trac, self::SUPPORTED, true ) ) {
			$this->last_stale = false;
			return false;
		}

		$ticket_id = (int) $ticket_id;
		if ( $ticket_id <= 0 ) {
			$this->last_stale = false;
			return false;
		}

		$key            = $this->ticket_cache_key( $ticket_id, $opts );
		$client_factory = $this->client_factory;

		$result = $this->cached_call(
			$trac,
			$key,
			static function () use ( $client_factory, $trac, $ticket_id, $opts ) {
				$client = call_user_func( $client_factory, $trac );
				if ( ! $client ) {
					return false;
				}
				return $client->get_trac_ticket_full( $ticket_id, $opts );
			}
		);

		if ( is_array( $result ) ) {
			return $this->normalize_ticket( $result, $trac );
		}

		return $result;
	}

	/**
	 * Whether the most recent get_*() call returned data from the stale cache
	 * because Trac was unreachable. Callers wrapping responses in HTTP may
	 * want to surface this as `_stale: true` for client-side messaging.
	 *
	 * @return bool
	 */
	public function is_last_stale() {
		return $this->last_stale;
	}

	/**
	 * Shared caching + circuit-breaker path.
	 *
	 * Lookup precedence:
	 *   1. Fresh cache (includes NULL_SENTINEL → returns null short-circuit).
	 *   2. Live callable, when the breaker is closed.
	 *      - Array result is written to fresh+stale, returned.
	 *      - Null result writes NULL_SENTINEL to both layers (preventing a
	 *        deleted ticket from resurfacing via stale) and returns null.
	 *      - Any other return trips the breaker and falls through.
	 *   3. Stale cache → returned (marks last_stale).
	 *   4. false.
	 *
	 * @param string   $trac     Trac slug.
	 * @param string   $key      Method+args cache-key suffix.
	 * @param callable $callback Live data fetcher returning array, null, or false on failure.
	 * @return array|null|false
	 */
	protected function cached_call( $trac, $key, callable $callback ) {
		$this->last_stale = false;

		$fresh_key   = "{$trac}/{$key}:fresh";
		$stale_key   = "{$trac}/{$key}:stale";
		$breaker_key = "{$trac}:breaker";

		$cached = wp_cache_get( $fresh_key, self::CACHE_GROUP );
		if ( false !== $cached ) {
			return ( self::NULL_SENTINEL === $cached ) ? null : $cached;
		}

		if ( ! wp_cache_get( $breaker_key, self::CACHE_GROUP ) ) {
			$live = $callback();

			if ( is_array( $live ) ) {
				wp_cache_set( $fresh_key, $live, self::CACHE_GROUP, self::FRESH_TTL );
				wp_cache_set( $stale_key, $live, self::CACHE_GROUP, self::STALE_TTL );
				return $live;
			}

			if ( null === $live ) {
				wp_cache_set( $fresh_key, self::NULL_SENTINEL, self::CACHE_GROUP, self::NEGATIVE_TTL );
				wp_cache_set( $stale_key, self::NULL_SENTINEL, self::CACHE_GROUP, self::STALE_TTL );
				return null;
			}

			wp_cache_set( $breaker_key, 1, self::CACHE_GROUP, self::BREAKER_TTL );
		}

		$stale = wp_cache_get( $stale_key, self::CACHE_GROUP );
		if ( false !== $stale ) {
			$this->last_stale = true;
			return ( self::NULL_SENTINEL === $stale ) ? null : $stale;
		}

		return false;
	}

	/**
	 * Stable cache-key suffix for ticket reads. `ksort` ensures opts in
	 * different insertion order resolve to the same key.
	 *
	 * @param int   $ticket_id Ticket id.
	 * @param array $opts      Get-ticket options.
	 * @return string
	 */
	protected function ticket_cache_key( $ticket_id, $opts ) {
		ksort( $opts );
		return 'ticket:' . $ticket_id . ':' . md5( wp_json_encode( $opts ) );
	}

	/**
	 * Default factory used when none is injected. Memoised per-trac. Logs
	 * (loudly) when TRAC_NOTIFICATIONS_API_KEY is undefined so a permanent
	 * misconfiguration is distinguishable from a transient Trac outage.
	 *
	 * @param string $trac Trac slug.
	 * @return Trac_Notifications_HTTP_Client|null Null when credentials are missing.
	 */
	protected function default_client_factory( $trac ) {
		static $clients = array();
		if ( isset( $clients[ $trac ] ) ) {
			return $clients[ $trac ];
		}

		if ( ! defined( 'TRAC_NOTIFICATIONS_API_KEY' ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional operational log: a missing API key is a misconfiguration, not a transient error.
			error_log( 'Trac_API: TRAC_NOTIFICATIONS_API_KEY is not defined; Trac requests will fail.' );
			return null;
		}

		require_once __DIR__ . '/autoload.php';
		$clients[ $trac ] = new Trac_Notifications_HTTP_Client(
			"https://{$trac}.trac.wordpress.org/wpapi",
			TRAC_NOTIFICATIONS_API_KEY
		);
		return $clients[ $trac ];
	}

	/**
	 * Convert microsecond timestamps to ISO-8601, cast integer-shaped fields,
	 * and inject the canonical Trac URL.
	 *
	 * @param array  $ticket Ticket payload from the DAO.
	 * @param string $trac   Trac slug, for URL generation.
	 * @return array
	 */
	protected function normalize_ticket( array $ticket, $trac ) {
		$ticket = $this->normalize_times( $ticket, array( 'time', 'changetime' ) );

		foreach ( array( 'comments', 'changelog', 'attachments' ) as $list ) {
			if ( ! isset( $ticket[ $list ] ) || ! is_array( $ticket[ $list ] ) ) {
				continue;
			}
			foreach ( $ticket[ $list ] as &$row ) {
				if ( is_array( $row ) ) {
					$row = $this->normalize_times( $row, array( 'time' ) );
				}
			}
			unset( $row );
		}

		if ( isset( $ticket['id'] ) ) {
			$ticket['id']  = (int) $ticket['id'];
			$ticket['url'] = sprintf( 'https://%s.trac.wordpress.org/ticket/%d', $trac, $ticket['id'] );
		}

		return $ticket;
	}

	/**
	 * Replace microsecond integer timestamps with ISO-8601 strings. Values
	 * that are not numeric are left untouched (already-normalised or absent).
	 *
	 * @param array $row    Associative row.
	 * @param array $fields Field names to normalise.
	 * @return array
	 */
	protected function normalize_times( array $row, array $fields ) {
		foreach ( $fields as $f ) {
			if ( isset( $row[ $f ] ) && is_numeric( $row[ $f ] ) ) {
				$row[ $f ] = gmdate( 'c', (int) ( $row[ $f ] / 1000000 ) );
			}
		}
		return $row;
	}
}
