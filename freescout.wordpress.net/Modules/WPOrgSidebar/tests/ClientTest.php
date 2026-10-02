<?php
/**
 * Tests for the signed API client.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSidebar
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSidebar\Tests;

use Modules\WPOrgSidebar\Services\Client;
use RuntimeException;
use WordPressdotorg\FreeScout\Tests\TestCase;

/**
 * Covers the request format api.wordpress.org verifies.
 */
final class ClientTest extends TestCase {

	/**
	 * The signature is the hex HMAC-SHA256 of the exact body, as is_from_freescout() computes it.
	 *
	 * @return void
	 */
	public function test_signature_is_hmac_sha256_of_body(): void {
		$this->assertSame( hash_hmac( 'sha256', '{"a":1}', 'secret' ), Client::sign( '{"a":1}', 'secret' ) );
	}

	/**
	 * Payloads are stamped for replay protection and survive invalid UTF-8 in email bodies.
	 *
	 * @return void
	 */
	public function test_encode_stamps_time_and_tolerates_invalid_utf8(): void {
		$decoded = json_decode( Client::encode( array( 'body' => "caf\xE9" ), '/profile.php' ), true );

		$this->assertIsArray( $decoded );
		$this->assertSame( "caf\u{FFFD}", $decoded['body'] );
		$this->assertEqualsWithDelta( time(), $decoded['sent_at'], 5 );
		$this->assertSame( 'profile.php', $decoded['endpoint'] );
		$this->assertMatchesRegularExpression( '/^[0-9a-f]{32}$/', $decoded['nonce'] );
	}

	/**
	 * Every request gets its own nonce, so none can be sent twice.
	 *
	 * @return void
	 */
	public function test_encode_uses_a_new_nonce_each_time(): void {
		$first  = json_decode( Client::encode( array(), 'profile.php' ), true );
		$second = json_decode( Client::encode( array(), 'profile.php' ), true );

		$this->assertNotSame( $first['nonce'], $second['nonce'] );
	}

	/**
	 * Without a secret nothing is sent.
	 *
	 * @return void
	 */
	public function test_post_requires_secret(): void {
		$this->expectException( RuntimeException::class );

		( new Client( 'http://127.0.0.1:9/', '' ) )->post( 'profile.php', array() );
	}

	/**
	 * Network failures surface as RuntimeException, which callers catch.
	 *
	 * @return void
	 */
	public function test_post_wraps_connection_errors(): void {
		$this->expectException( RuntimeException::class );

		( new Client( 'http://127.0.0.1:9/', 'secret', 2 ) )->post( 'profile.php', array() );
	}
}
