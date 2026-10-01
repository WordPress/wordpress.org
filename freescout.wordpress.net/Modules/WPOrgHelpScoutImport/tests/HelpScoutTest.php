<?php
/**
 * Tests for the HelpScout API client.
 *
 * @package WordPressdotorg\FreeScout\WPOrgHelpScoutImport
 */

declare( strict_types = 1 );

namespace Modules\WPOrgHelpScoutImport\Tests;

use Carbon\Carbon;
use GuzzleHttp\Psr7\Response;
use Modules\WPOrgHelpScoutImport\Exceptions\ApiError;
use Modules\WPOrgHelpScoutImport\Exceptions\RateLimited;
use Modules\WPOrgHelpScoutImport\Services\HelpScout;
use Modules\WPOrgHelpScoutImport\Tests\Support\FakeHelpScout;

require_once __DIR__ . '/ImportTestCase.php';

/**
 * Covers tokens, the rate limit, and how lists are read.
 */
final class HelpScoutTest extends ImportTestCase {

	/**
	 * The access token is fetched once, and sent with every request.
	 *
	 * @return void
	 */
	public function test_token_is_fetched_once(): void {
		$this->helpscout->on( 'GET', 'v2/mailboxes', self::page( 'mailboxes', array( array( 'id' => 1 ) ) ) );
		$client = $this->helpscout->client();

		$client->mailboxes();
		$client->mailboxes();

		$this->assertCount( 1, $this->helpscout->requests_to( 'v2/oauth2/token' ) );
		$this->assertStringContainsString( 'client_secret=app-secret', (string) $this->helpscout->requests_to( 'v2/oauth2/token' )[0]->getBody() );
		foreach ( $this->helpscout->requests_to( 'v2/mailboxes' ) as $request ) {
			$this->assertSame( 'Bearer token-1', $request->getHeaderLine( 'Authorization' ) );
		}
	}

	/**
	 * A rejected token is replaced once, and the request sent again.
	 *
	 * @return void
	 */
	public function test_rejected_token_is_renewed(): void {
		$this->helpscout->only(
			'POST',
			'v2/oauth2/token',
			array(
				'access_token' => 'token-1',
				'expires_in'   => 172800,
			)
		)->on(
			'POST',
			'v2/oauth2/token',
			array(
				'access_token' => 'token-2',
				'expires_in'   => 172800,
			)
		);
		$this->helpscout->on( 'GET', 'v2/mailboxes', FakeHelpScout::json( array(), 401 ) )
			->on( 'GET', 'v2/mailboxes', self::page( 'mailboxes', array( array( 'id' => 1 ) ) ) );

		$this->assertSame( array( array( 'id' => 1 ) ), $this->helpscout->client()->mailboxes() );
		$this->assertSame( 'Bearer token-2', $this->helpscout->requests_to( 'v2/mailboxes' )[1]->getHeaderLine( 'Authorization' ) );
	}

	/**
	 * Credentials HelpScout refuses are reported as denied.
	 *
	 * @return void
	 */
	public function test_refused_credentials_are_denied(): void {
		$this->helpscout->only( 'POST', 'v2/oauth2/token', FakeHelpScout::json( array( 'error' => 'invalid_client' ), 401 ) );

		try {
			$this->helpscout->client()->mailboxes();
			$this->fail( 'Expected an error.' );
		} catch ( ApiError $e ) {
			$this->assertTrue( $e->is_denied() );
		}
	}

	/**
	 * HelpScout's 429 says how long to wait.
	 *
	 * @return void
	 */
	public function test_rate_limit_says_how_long_to_wait(): void {
		$this->helpscout->on( 'GET', 'v2/mailboxes', FakeHelpScout::json( array(), 429, array( 'X-RateLimit-Retry-After' => '17' ) ) );

		try {
			$this->helpscout->client()->mailboxes();
			$this->fail( 'Expected the rate limit.' );
		} catch ( RateLimited $e ) {
			$this->assertSame( 17, $e->retry_after );
		}
	}

	/**
	 * Once only the reserve is left, no more requests are sent this minute.
	 *
	 * @return void
	 */
	public function test_reserve_is_left_to_others(): void {
		$this->helpscout->on( 'GET', 'v2/mailboxes', self::page( 'mailboxes', array(), array( 'X-RateLimit-Remaining-Minute' => '100' ) ) );
		$client = $this->helpscout->client( 100 );

		$client->mailboxes();

		$this->expectException( RateLimited::class );
		try {
			$client->mailboxes();
		} finally {
			$this->assertCount( 1, $this->helpscout->requests_to( 'v2/mailboxes' ) );
		}
	}

	/**
	 * A whole import reads oldest first; an import of changes reads what changed, since when, in UTC.
	 *
	 * @return void
	 */
	public function test_conversations_are_listed_in_order(): void {
		$this->helpscout->on( 'GET', 'v2/conversations', self::page( 'conversations', array( array( 'id' => 1 ) ), array(), 3, 60 ) );
		$client = $this->helpscout->client();

		$page = $client->conversations( 77, 2, null );
		$client->conversations( 77, 1, Carbon::parse( '2026-09-10 12:00:00', 'Europe/Berlin' ) );

		$this->assertSame( array( array( 'id' => 1 ) ), $page['conversations'] );
		$this->assertSame( 3, $page['pages'] );
		$this->assertSame( 60, $page['total'] );

		list( $all, $changes ) = $this->helpscout->requests_to( 'v2/conversations' );
		parse_str( $all->getUri()->getQuery(), $query );
		$this->assertSame(
			array(
				'mailbox'   => '77',
				'status'    => 'all',
				'sortField' => 'createdAt',
				'sortOrder' => 'asc',
				'page'      => '2',
			),
			$query
		);
		parse_str( $changes->getUri()->getQuery(), $query );
		$this->assertSame( 'modifiedAt', $query['sortField'] );
		$this->assertSame( '2026-09-10T10:00:00Z', $query['modifiedSince'] );
	}

	/**
	 * Threads come from every page, oldest first.
	 *
	 * @return void
	 */
	public function test_threads_come_from_every_page_oldest_first(): void {
		$this->helpscout->only(
			'GET',
			'v2/conversations/5/threads',
			self::page(
				'threads',
				array(
					array(
						'id'        => 3,
						'createdAt' => '2026-09-03T00:00:00Z',
					),
				),
				array(),
				2
			)
		)->on(
			'GET',
			'v2/conversations/5/threads',
			self::page(
				'threads',
				array(
					array(
						'id'        => 1,
						'createdAt' => '2026-09-01T00:00:00Z',
					),
				),
				array(),
				2
			)
		);

		$this->assertSame( array( 1, 3 ), array_column( $this->helpscout->client()->threads( 5 ), 'id' ) );
	}

	/**
	 * A thread without an original email has none, rather than failing.
	 *
	 * @return void
	 */
	public function test_missing_original_source_is_null(): void {
		$this->assertNull( $this->helpscout->client()->original_source( 5, 6 ) );
		$this->assertSame( 'message/rfc822', $this->helpscout->requests_to( 'v2/conversations/5/threads/6/original-source' )[0]->getHeaderLine( 'Accept' ) );
	}

	/**
	 * Without credentials, nothing is sent.
	 *
	 * @return void
	 */
	public function test_nothing_is_sent_without_credentials(): void {
		$client = new HelpScout( '', '', 'https://helpscout.test/', 0, new \GuzzleHttp\Client() );

		$this->assertFalse( $client->is_configured() );
		$this->expectException( ApiError::class );
		$client->mailboxes();
	}

	/**
	 * A page of a list, as HelpScout answers it.
	 *
	 * @param string  $key     Key in `_embedded`.
	 * @param array[] $items   Items.
	 * @param array   $headers Headers.
	 * @param int     $pages   Number of pages.
	 * @param int     $total   Number of items.
	 * @return Response
	 */
	private static function page( string $key, array $items, array $headers = array(), int $pages = 1, int $total = 0 ): Response {
		return FakeHelpScout::json(
			array(
				'_embedded' => array( $key => $items ),
				'page'      => array(
					'totalPages'    => $pages,
					'totalElements' => $total,
				),
			),
			200,
			$headers
		);
	}
}
