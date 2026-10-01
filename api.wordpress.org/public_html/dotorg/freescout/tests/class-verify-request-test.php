<?php
/**
 * Tests for which requests the endpoints accept.
 *
 * A request is only good for the endpoint it was signed for, while it's fresh, and once.
 *
 * @package WordPressdotorg\API\FreeScout
 */

declare( strict_types = 1 );

namespace WordPressdotorg\API\FreeScout;

use PHPUnit\Framework\TestCase;

/**
 * Tests for verify_request().
 */
class Verify_Request_Test extends TestCase {

	/**
	 * Clears the nonce cache.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		unset( $GLOBALS['freescout_test_cache'] );

		parent::tearDown();
	}

	/**
	 * A signed, fresh request for this endpoint is accepted.
	 *
	 * @return void
	 */
	public function test_accepts_a_signed_request(): void {
		$body = self::body();

		$this->assertSame( 'rita', verify_request( $body, self::sign( $body ), 'account.php' )->username );
	}

	/**
	 * The same request isn't accepted twice.
	 *
	 * @return void
	 */
	public function test_refuses_a_replay(): void {
		$body = self::body();

		$this->assertNotNull( verify_request( $body, self::sign( $body ), 'account.php' ) );
		$this->assertNull( verify_request( $body, self::sign( $body ), 'account.php' ) );
	}

	/**
	 * Requests that aren't signed for this endpoint, now, aren't accepted.
	 *
	 * @dataProvider data_refused
	 *
	 * @param array  $changes   Payload fields to change.
	 * @param string $endpoint  Endpoint the request arrives at.
	 * @param bool   $signed    Whether to sign the body correctly.
	 * @return void
	 */
	public function test_refuses( array $changes, string $endpoint, bool $signed ): void {
		$body = self::body( $changes );

		$this->assertNull( verify_request( $body, $signed ? self::sign( $body ) : str_repeat( '0', 64 ), $endpoint ) );
	}

	/**
	 * Supplies requests to refuse.
	 *
	 * @return array
	 */
	public function data_refused(): array {
		return array(
			'wrong signature'  => array( array(), 'account.php', false ),
			'another endpoint' => array( array(), 'profile.php', true ),
			'no endpoint'      => array( array( 'endpoint' => null ), 'account.php', true ),
			'too old'          => array( array( 'sent_at' => time() - MAX_REQUEST_AGE - 1 ), 'account.php', true ),
			'from the future'  => array( array( 'sent_at' => time() + MAX_CLOCK_SKEW + 60 ), 'account.php', true ),
			'no nonce'         => array( array( 'nonce' => null ), 'account.php', true ),
			'malformed nonce'  => array( array( 'nonce' => 'abc' ), 'account.php', true ),
		);
	}

	/**
	 * A clock a little ahead is fine.
	 *
	 * @return void
	 */
	public function test_allows_clock_skew(): void {
		$body = self::body( array( 'sent_at' => time() + MAX_CLOCK_SKEW - 5 ) );

		$this->assertNotNull( verify_request( $body, self::sign( $body ), 'account.php' ) );
	}

	/**
	 * Builds a request body like the modules' clients do.
	 *
	 * @param array $changes Payload fields to change; null removes one.
	 * @return string
	 */
	private static function body( array $changes = array() ): string {
		$payload = array_merge(
			array(
				'username' => 'rita',
				'sent_at'  => time(),
				'endpoint' => 'account.php',
				'nonce'    => bin2hex( random_bytes( 16 ) ),
			),
			$changes
		);

		return (string) wp_json_encode( array_filter( $payload, static fn ( $value ): bool => null !== $value ) );
	}

	/**
	 * Signs a body with the test secret.
	 *
	 * @param string $body Request body.
	 * @return string
	 */
	private static function sign( string $body ): string {
		return hash_hmac( 'sha256', $body, FREESCOUT_SECRET );
	}
}
