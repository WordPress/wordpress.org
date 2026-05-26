<?php
/**
 * Unit tests for the Trac_API caching wrapper. Injects an in-memory cache
 * and a scriptable client so the cache read path, circuit breaker, negative
 * caching, and ticket normalization can all be exercised without touching
 * memcached or a real Trac box.
 *
 * @package WordPressdotorg\Trac
 */

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group( 'trac-notifications' )]
class Trac_API_Tests extends TestCase {

	/**
	 * @var Memory_Cache
	 */
	protected $cache;

	/**
	 * @var array<string, Fake_Client>
	 */
	protected $clients;

	/**
	 * @var Trac_API
	 */
	protected $api;

	/**
	 * @return void
	 */
	public function setUp(): void {
		$this->cache   = new Memory_Cache();
		$this->clients = array(
			'core' => new Fake_Client(),
			'meta' => new Fake_Client(),
		);

		$clients = $this->clients;
		$factory = function ( $trac ) use ( $clients ) {
			return isset( $clients[ $trac ] ) ? $clients[ $trac ] : null;
		};

		$this->api = new Trac_API( $factory, $this->cache );
	}

	/**
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
	 * @return void
	 */
	public function test_cold_cache_calls_client_and_writes_both_layers() {
		$this->clients['core']->next_response = $this->ticket_row();

		$result = $this->api->get_ticket( 'core', 42 );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $this->clients['core']->calls );

		$keys = array_column( $this->cache->set_calls, 'key' );
		$this->assertCount( 2, $this->cache->set_calls, 'fresh + stale written' );
		$this->assertStringContainsString( ':fresh', $keys[0] );
		$this->assertStringContainsString( ':stale', $keys[1] );
		$this->assertFalse( $this->api->is_last_stale() );
	}

	/**
	 * @return void
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
	 * @return void
	 */
	public function test_client_failure_trips_breaker_and_serves_stale() {
		$this->clients['core']->next_response = $this->ticket_row();
		$this->api->get_ticket( 'core', 42 );

		foreach ( array_keys( $this->cache->store ) as $key ) {
			if ( str_contains( $key, ':fresh' ) ) {
				$this->cache->expire( $key );
			}
		}

		$this->clients['core']->next_response = false;
		$this->clients['core']->calls         = array();

		$result = $this->api->get_ticket( 'core', 42 );

		$this->assertIsArray( $result, 'stale served when live call fails' );
		$this->assertTrue( $this->api->is_last_stale() );
		$this->assertCount( 1, $this->clients['core']->calls, 'one live attempt was made' );

		$breaker_set = false;
		foreach ( $this->cache->set_calls as $set ) {
			if ( str_ends_with( $set['key'], ':breaker' ) ) {
				$breaker_set = true;
			}
		}
		$this->assertTrue( $breaker_set, 'breaker tripped after live failure' );
	}

	/**
	 * @return void
	 */
	public function test_breaker_open_skips_client_and_serves_stale() {
		$this->clients['core']->next_response = $this->ticket_row();
		$this->api->get_ticket( 'core', 42 );

		foreach ( array_keys( $this->cache->store ) as $key ) {
			if ( str_contains( $key, ':fresh' ) ) {
				$this->cache->expire( $key );
			}
		}
		$this->cache->store['core:breaker'] = 1;

		$this->clients['core']->calls = array();

		$result = $this->api->get_ticket( 'core', 42 );

		$this->assertIsArray( $result );
		$this->assertTrue( $this->api->is_last_stale() );
		$this->assertCount( 0, $this->clients['core']->calls, 'client never called when breaker is open' );
	}

	/**
	 * @return void
	 */
	public function test_breaker_open_and_no_stale_returns_false() {
		$this->cache->store['core:breaker'] = 1;

		$result = $this->api->get_ticket( 'core', 999 );

		$this->assertFalse( $result );
		$this->assertFalse( $this->api->is_last_stale() );
		$this->assertCount( 0, $this->clients['core']->calls );
	}

	/**
	 * @return void
	 */
	public function test_failure_with_no_stale_returns_false() {
		$this->clients['core']->next_response = false;

		$result = $this->api->get_ticket( 'core', 999 );

		$this->assertFalse( $result );
		$this->assertFalse( $this->api->is_last_stale(), 'flag stays false because no stale data was actually served' );
	}

	/**
	 * @return void
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
	 * @return void
	 */
	public function test_null_sentinel_in_fresh_cache_is_returned_as_null() {
		$key                                       = 'ticket:42:' . md5( wp_json_encode( array() ) );
		$this->cache->store[ "core/{$key}:fresh" ] = Trac_API::NULL_SENTINEL;

		$result = $this->api->get_ticket( 'core', 42 );

		$this->assertNull( $result );
		$this->assertCount( 0, $this->clients['core']->calls );
	}

	/**
	 * @return void
	 */
	public function test_unsupported_trac_returns_false_without_touching_client_or_cache() {
		$result = $this->api->get_ticket( 'plugins', 42 );

		$this->assertFalse( $result );
		$this->assertCount( 0, $this->cache->get_calls );
		$this->assertCount( 0, $this->clients['core']->calls );
		$this->assertCount( 0, $this->clients['meta']->calls );
	}

	/**
	 * @return void
	 */
	public function test_invalid_ticket_id_returns_false() {
		$this->assertFalse( $this->api->get_ticket( 'core', 0 ) );
		$this->assertFalse( $this->api->get_ticket( 'core', -1 ) );
		$this->assertCount( 0, $this->clients['core']->calls );
	}

	/**
	 * @return void
	 */
	public function test_core_breaker_does_not_block_meta() {
		$this->cache->store['core:breaker']   = 1;
		$this->clients['meta']->next_response = $this->ticket_row();

		$result = $this->api->get_ticket( 'meta', 42 );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $this->clients['meta']->calls );
		$this->assertCount( 0, $this->clients['core']->calls );
	}

	/**
	 * @return void
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
	 * @return void
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
	 * @return void
	 */
	public function test_normalisation_leaves_non_numeric_time_alone() {
		$this->clients['core']->next_response = $this->ticket_row(
			array( 'time' => 'already-iso' )
		);

		$result = $this->api->get_ticket( 'core', 42 );

		$this->assertSame( 'already-iso', $result['time'] );
	}

	/**
	 * @return void
	 */
	public function test_normalisation_casts_id_and_injects_url() {
		$this->clients['core']->next_response = $this->ticket_row( array( 'id' => '42' ) );

		$result = $this->api->get_ticket( 'core', 42 );

		$this->assertSame( 42, $result['id'] );
		$this->assertSame( 'https://core.trac.wordpress.org/ticket/42', $result['url'] );
	}

	/**
	 * @return void
	 */
	public function test_meta_trac_url_normalisation() {
		$this->clients['meta']->next_response = $this->ticket_row( array( 'id' => '99' ) );

		$result = $this->api->get_ticket( 'meta', 99 );

		$this->assertSame( 'https://meta.trac.wordpress.org/ticket/99', $result['url'] );
	}

	/**
	 * @return void
	 */
	public function test_different_opts_get_separate_cache_keys() {
		$this->clients['core']->next_response = $this->ticket_row();
		$this->api->get_ticket( 'core', 42, array( 'comments' => 25 ) );

		$this->clients['core']->next_response = $this->ticket_row( array( 'summary' => 'Different opts' ) );
		$this->api->get_ticket( 'core', 42, array( 'comments' => false ) );

		$this->assertCount( 2, $this->clients['core']->calls, 'distinct opts produce distinct cache keys' );
	}

	/**
	 * @return void
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
