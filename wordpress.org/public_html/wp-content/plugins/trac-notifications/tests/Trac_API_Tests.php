<?php
/**
 * Unit tests for the Trac_API caching wrapper. A scriptable client + the
 * wp_cache_* polyfill in tests/bootstrap.php let us exercise the cache read
 * path, circuit breaker, negative caching, and ticket normalization without
 * touching memcached or a real Trac box.
 *
 * @package WordPressdotorg\Trac
 */

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Trac_API behaviour tests.
 */
#[Group( 'trac-notifications' )]
class Trac_API_Tests extends TestCase {

	/**
	 * Map of per-trac scriptable HTTP clients keyed by trac slug.
	 *
	 * @var array<string, Fake_Client>
	 */
	protected $clients;

	/**
	 * System under test.
	 *
	 * @var Trac_API
	 */
	protected $api;

	/**
	 * Reset the cache polyfill, build per-trac clients, and an api instance.
	 */
	public function setUp(): void {
		Test_Cache::reset();

		$this->clients = array(
			'core' => new Fake_Client(),
			'meta' => new Fake_Client(),
		);

		$clients = $this->clients;
		$factory = static function ( $trac ) use ( $clients ) {
			return $clients[ $trac ] ?? null;
		};

		$this->api = new Trac_API( $factory );
	}

	/**
	 * Build a representative DAO-shaped ticket payload, with overrides merged on top.
	 *
	 * @param array $overrides Fields to override.
	 * @return array
	 */
	protected function ticket_row( $overrides = array() ) {
		return array_merge(
			array(
				'id'             => '42',
				'summary'        => 'Test ticket',
				'status'         => 'new',
				'time'           => '1640000000000000',
				'changetime'     => '1641000000000000',
				'custom_fields'  => array(),
				'participants'   => array(),
				'comments'       => array(),
				'comments_total' => 0,
				'changelog'      => array(),
				'attachments'    => array(),
			),
			$overrides
		);
	}

	/**
	 * First request hits the client and writes both fresh and stale entries.
	 */
	public function test_cold_cache_calls_client_and_writes_both_layers() {
		$this->clients['core']->next_response = $this->ticket_row();

		$result = $this->api->get_ticket( 'core', 42 );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $this->clients['core']->calls );

		$keys = array_column( Test_Cache::$set_calls, 'key' );
		$this->assertCount( 2, Test_Cache::$set_calls, 'fresh + stale written' );
		$this->assertStringContainsString( ':fresh', $keys[0] );
		$this->assertStringContainsString( ':stale', $keys[1] );
		$this->assertFalse( $this->api->is_last_stale() );
	}

	/**
	 * A warm fresh cache returns the previously-cached value without touching the client.
	 */
	public function test_warm_cache_skips_client_and_returns_same_value() {
		$this->clients['core']->next_response = $this->ticket_row();
		$first                                = $this->api->get_ticket( 'core', 42 );

		$this->clients['core']->calls = array();

		$second = $this->api->get_ticket( 'core', 42 );

		$this->assertSame( $first, $second );
		$this->assertCount( 0, $this->clients['core']->calls, 'client not called when fresh cache hits' );
		$this->assertFalse( $this->api->is_last_stale() );
	}

	/**
	 * A client failure trips the breaker and falls back to stale data.
	 */
	public function test_client_failure_trips_breaker_and_serves_stale() {
		$this->clients['core']->next_response = $this->ticket_row();
		$this->api->get_ticket( 'core', 42 );

		foreach ( array_keys( Test_Cache::$store ) as $key ) {
			if ( str_contains( $key, ':fresh' ) ) {
				unset( Test_Cache::$store[ $key ] );
			}
		}

		$this->clients['core']->next_response = false;
		$this->clients['core']->calls         = array();

		$result = $this->api->get_ticket( 'core', 42 );

		$this->assertIsArray( $result, 'stale served when live call fails' );
		$this->assertTrue( $this->api->is_last_stale() );
		$this->assertCount( 1, $this->clients['core']->calls, 'one live attempt was made' );

		$breaker_set = false;
		foreach ( Test_Cache::$set_calls as $set ) {
			if ( str_ends_with( $set['key'], ':breaker' ) ) {
				$breaker_set = true;
			}
		}
		$this->assertTrue( $breaker_set, 'breaker tripped after live failure' );
	}

	/**
	 * When the breaker is already open, the client is not contacted and stale wins.
	 */
	public function test_breaker_open_skips_client_and_serves_stale() {
		$this->clients['core']->next_response = $this->ticket_row();
		$this->api->get_ticket( 'core', 42 );

		foreach ( array_keys( Test_Cache::$store ) as $key ) {
			if ( str_contains( $key, ':fresh' ) ) {
				unset( Test_Cache::$store[ $key ] );
			}
		}
		Test_Cache::$store[ Test_Cache::key( 'core:breaker', Trac_API::CACHE_GROUP ) ] = 1;

		$this->clients['core']->calls = array();

		$result = $this->api->get_ticket( 'core', 42 );

		$this->assertIsArray( $result );
		$this->assertTrue( $this->api->is_last_stale() );
		$this->assertCount( 0, $this->clients['core']->calls, 'client never called when breaker is open' );
	}

	/**
	 * Breaker open and no stale data: returns false without calling the client.
	 */
	public function test_breaker_open_and_no_stale_returns_false() {
		Test_Cache::$store[ Test_Cache::key( 'core:breaker', Trac_API::CACHE_GROUP ) ] = 1;

		$result = $this->api->get_ticket( 'core', 999 );

		$this->assertFalse( $result );
		$this->assertFalse( $this->api->is_last_stale() );
		$this->assertCount( 0, $this->clients['core']->calls );
	}

	/**
	 * Live failure with no stale entry returns false, last_stale stays false.
	 */
	public function test_failure_with_no_stale_returns_false() {
		$this->clients['core']->next_response = false;

		$result = $this->api->get_ticket( 'core', 999 );

		$this->assertFalse( $result );
		$this->assertFalse( $this->api->is_last_stale(), 'flag stays false because no stale data was actually served' );
	}

	/**
	 * Null response is negatively cached so subsequent calls do not re-hit Trac.
	 */
	public function test_null_response_is_cached_and_returned_as_null() {
		$this->clients['core']->next_response = null;

		$first = $this->api->get_ticket( 'core', 999 );
		$this->assertNull( $first );

		$this->clients['core']->calls         = array();
		$this->clients['core']->next_response = null;

		$second = $this->api->get_ticket( 'core', 999 );
		$this->assertNull( $second );
		$this->assertCount( 0, $this->clients['core']->calls, 'negative cache prevents re-call' );
	}

	/**
	 * A NULL_SENTINEL value seeded in fresh cache is read back as null.
	 */
	public function test_null_sentinel_in_fresh_cache_is_returned_as_null() {
		$key                                                                = 'ticket:42:' . md5( wp_json_encode( array() ) );
		$fresh_key                                                          = "core/{$key}:fresh";
		Test_Cache::$store[ Test_Cache::key( $fresh_key, Trac_API::CACHE_GROUP ) ] = Trac_API::NULL_SENTINEL;

		$result = $this->api->get_ticket( 'core', 42 );

		$this->assertNull( $result );
		$this->assertCount( 0, $this->clients['core']->calls );
	}

	/**
	 * Unsupported trac short-circuits to false without touching cache or client.
	 */
	public function test_unsupported_trac_returns_false_without_touching_client_or_cache() {
		$result = $this->api->get_ticket( 'plugins', 42 );

		$this->assertFalse( $result );
		$this->assertCount( 0, Test_Cache::$get_calls );
		$this->assertCount( 0, $this->clients['core']->calls );
		$this->assertCount( 0, $this->clients['meta']->calls );
	}

	/**
	 * Non-positive ticket ids are rejected up-front.
	 */
	public function test_invalid_ticket_id_returns_false() {
		$this->assertFalse( $this->api->get_ticket( 'core', 0 ) );
		$this->assertFalse( $this->api->get_ticket( 'core', -1 ) );
		$this->assertCount( 0, $this->clients['core']->calls );
	}

	/**
	 * A core.trac breaker does not block meta.trac calls.
	 */
	public function test_core_breaker_does_not_block_meta() {
		Test_Cache::$store[ Test_Cache::key( 'core:breaker', Trac_API::CACHE_GROUP ) ] = 1;
		$this->clients['meta']->next_response                                          = $this->ticket_row();

		$result = $this->api->get_ticket( 'meta', 42 );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $this->clients['meta']->calls );
		$this->assertCount( 0, $this->clients['core']->calls );
	}

	/**
	 * Microsecond timestamps on the ticket root are converted to ISO-8601.
	 */
	public function test_normalisation_converts_microseconds_to_iso8601() {
		$this->clients['core']->next_response = $this->ticket_row(
			array(
				'time'       => '1640995200000000',
				'changetime' => '1672531200000000',
			)
		);

		$result = $this->api->get_ticket( 'core', 42 );

		$this->assertSame( '2022-01-01T00:00:00+00:00', $result['time'] );
		$this->assertSame( '2023-01-01T00:00:00+00:00', $result['changetime'] );
	}

	/**
	 * Microsecond timestamps inside comments, changelog, and attachments are also converted.
	 */
	public function test_normalisation_converts_nested_times() {
		$this->clients['core']->next_response = $this->ticket_row(
			array(
				'comments'    => array(
					array(
						'id'     => '1',
						'time'   => '1640995200000000',
						'author' => 'alice',
						'body'   => 'hi',
					),
				),
				'changelog'   => array(
					array(
						'time'     => '1641081600000000',
						'author'   => 'bob',
						'field'    => 'status',
						'oldvalue' => 'new',
						'newvalue' => 'assigned',
					),
				),
				'attachments' => array(
					array(
						'filename'    => 'patch.diff',
						'time'        => '1641168000000000',
						'size'        => '100',
						'author'      => 'carol',
						'description' => '',
					),
				),
			)
		);

		$result = $this->api->get_ticket( 'core', 42 );

		$this->assertSame( '2022-01-01T00:00:00+00:00', $result['comments'][0]['time'] );
		$this->assertSame( '2022-01-02T00:00:00+00:00', $result['changelog'][0]['time'] );
		$this->assertSame( '2022-01-03T00:00:00+00:00', $result['attachments'][0]['time'] );
	}

	/**
	 * Non-numeric time fields are left untouched (already-formatted strings survive).
	 */
	public function test_normalisation_leaves_non_numeric_time_alone() {
		$this->clients['core']->next_response = $this->ticket_row(
			array( 'time' => 'already-iso' )
		);

		$result = $this->api->get_ticket( 'core', 42 );

		$this->assertSame( 'already-iso', $result['time'] );
	}

	/**
	 * Ticket id is cast to int and a canonical Trac URL is injected.
	 */
	public function test_normalisation_casts_id_and_injects_url() {
		$this->clients['core']->next_response = $this->ticket_row( array( 'id' => '42' ) );

		$result = $this->api->get_ticket( 'core', 42 );

		$this->assertSame( 42, $result['id'] );
		$this->assertSame( 'https://core.trac.wordpress.org/ticket/42', $result['url'] );
	}

	/**
	 * URL injection uses the trac slug provided to get_ticket.
	 */
	public function test_meta_trac_url_normalisation() {
		$this->clients['meta']->next_response = $this->ticket_row( array( 'id' => '99' ) );

		$result = $this->api->get_ticket( 'meta', 99 );

		$this->assertSame( 'https://meta.trac.wordpress.org/ticket/99', $result['url'] );
	}

	/**
	 * Different opts payloads resolve to different cache keys, so each is fetched independently.
	 */
	public function test_different_opts_get_separate_cache_keys() {
		$this->clients['core']->next_response = $this->ticket_row();
		$this->api->get_ticket( 'core', 42, array( 'comments' => 25 ) );

		$this->clients['core']->next_response = $this->ticket_row( array( 'summary' => 'Different opts' ) );
		$this->api->get_ticket( 'core', 42, array( 'comments' => false ) );

		$this->assertCount( 2, $this->clients['core']->calls, 'distinct opts produce distinct cache keys' );
	}

	/**
	 * Opt insertion order does not affect the cache key, thanks to ksort().
	 */
	public function test_opts_in_different_order_resolve_to_same_cache_key() {
		$this->clients['core']->next_response = $this->ticket_row();
		$this->api->get_ticket(
			'core',
			42,
			array(
				'comments'  => 25,
				'changelog' => true,
			)
		);

		$this->clients['core']->calls = array();

		$this->api->get_ticket(
			'core',
			42,
			array(
				'changelog' => true,
				'comments'  => 25,
			)
		);

		$this->assertCount( 0, $this->clients['core']->calls, 'ksort makes opt ordering irrelevant' );
	}
}
