<?php
/**
 * Checks the DNS record authors add to show they own a domain.
 *
 * @package WordPressdotorg\FreeScout\WPOrgPluginReview
 */

declare( strict_types = 1 );

namespace Modules\WPOrgPluginReview\Services;

/**
 * Looks for the TXT record `wordpressorg-{username}-verification`, which the ownership emails ask authors to add to
 * their domain.
 *
 * Not final, so tests can answer for DNS.
 */
class Dns {

	/**
	 * How long an answer is reused, in minutes; an author who just added the record waits at most this long.
	 *
	 * @var int
	 */
	private const CACHE_MINUTES = 5;

	/**
	 * Whether a domain has the record for a WordPress.org account.
	 *
	 * @param string $domain   Domain.
	 * @param string $username The account's username.
	 * @return bool
	 */
	public function verifies( string $domain, string $username ): bool {
		if ( '' === $domain || '' === $username ) {
			return false;
		}

		$expected = strtolower( 'wordpressorg-' . $username . '-verification' );

		return (bool) \Cache::remember(
			'wporgpluginreview.dns.' . md5( $domain . '|' . $expected ),
			self::CACHE_MINUTES,
			function () use ( $domain, $expected ): bool {
				foreach ( $this->txt_records( $domain ) as $record ) {
					if ( str_contains( strtolower( $record ), $expected ) ) {
						return true;
					}
				}

				return false;
			}
		);
	}

	/**
	 * A domain's TXT records.
	 *
	 * @param string $domain Domain.
	 * @return string[] Empty if there are none, or the lookup failed.
	 */
	protected function txt_records( string $domain ): array {
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- A failed lookup warns; it only means no record.
		$records = @dns_get_record( $domain, DNS_TXT );

		return is_array( $records ) ? array_map( 'strval', array_column( $records, 'txt' ) ) : array();
	}
}
