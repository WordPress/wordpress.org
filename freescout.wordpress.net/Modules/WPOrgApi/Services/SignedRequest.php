<?php
/**
 * A request WordPress.org signed.
 *
 * @package WordPressdotorg\FreeScout\WPOrgApi
 */

declare( strict_types = 1 );

namespace Modules\WPOrgApi\Services;

use Illuminate\Http\Request;

/**
 * Reads a request's JSON body once its signature, freshness, and endpoint check out.
 *
 * WordPress.org signs the body with HMAC-SHA256 and the secret it shares with the modules, stamps it with when it was
 * sent, and names the endpoint, so a request is only good there, and not for long. Like the modules' requests to
 * api.wordpress.org, the other way around.
 */
final class SignedRequest {

	/**
	 * Header with the body's signature.
	 *
	 * @var string
	 */
	public const SIGNATURE_HEADER = 'X-WPOrg-Signature';

	/**
	 * Signs a body.
	 *
	 * @param string $body   Request body.
	 * @param string $secret Shared secret.
	 * @return string
	 */
	public static function sign( string $body, string $secret ): string {
		return hash_hmac( 'sha256', $body, $secret );
	}

	/**
	 * The request's payload, if it's WordPress.org's, for this endpoint, and fresh.
	 *
	 * @param Request $request  Request.
	 * @param string  $endpoint Endpoint the payload must name.
	 * @return array|null Null if it isn't.
	 */
	public static function payload( Request $request, string $endpoint ): ?array {
		$secret = (string) config( 'wporgapi.secret' );
		$body   = (string) $request->getContent();

		if ( '' === $secret || ! hash_equals( self::sign( $body, $secret ), (string) $request->header( self::SIGNATURE_HEADER ) ) ) {
			return null;
		}

		$payload = json_decode( $body, true );
		if ( ! is_array( $payload ) || ( $payload['endpoint'] ?? '' ) !== $endpoint ) {
			return null;
		}

		$age = time() - (int) ( $payload['sent_at'] ?? 0 );
		if ( $age < -10 || $age > (int) config( 'wporgapi.max_age' ) ) {
			return null;
		}

		return $payload;
	}
}
