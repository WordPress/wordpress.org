<?php
namespace WordPressdotorg\Plugin_Directory\API;

class Scoped_API_Key {
	/**
	 * Scopes for the API key.
	 */
	const SCOPES = [
		'plugin-review',
	];

	const RATE_LIMIT_PER_WEEK = 2000;

	const META_KEY = '_wporg_plugin_scoped_api_keys';

	/**
	 * Generate a new scoped API key for a user.
	 *
	 * @param int    $user_id The user ID.
	 * @param string $scope   The scope this key is valid for (e.g. 'plugin-review:assign').
	 * @return string The plain-text key (show once to the user, never store it).
	 */
	public static function generate( int $user_id, string $scope ): string {
		$raw_key    = wp_generate_password( 48, false );
		$hashed_key = wp_hash_password( $raw_key );

		$keys   = get_user_meta( $user_id, self::META_KEY, true ) ?: [];
		$keys[] = [
			'hash'       => $hashed_key,
			'scope'      => $scope,
			'created_at' => time(),
		];

		update_user_meta( $user_id, self::META_KEY, $keys );

		// Return the plain-text key — this is the only time it's available.
		return $raw_key;
	}

	/**
	 * Validate a raw key against a user + scope.
	 *
	 * @param int    $user_id The user ID.
	 * @param string $raw_key The plain-text key from the request.
	 * @param string $scope   The required scope.
	 * @return bool|WP_Error True if valid, WP_Error if rate limited or invalid.
	 */
	public static function validate( int $user_id, string $raw_key, string $scope ) {
		$keys    = get_user_meta( $user_id, self::META_KEY, true ) ?: [];
		$validated = false;

		$user = get_user_by( 'id', $user_id );
		$username = $user ? $user->user_login : '';

		foreach ( $keys as $index => $entry ) {
			if ( $entry['scope'] !== $scope || ! wp_check_password( $raw_key, $entry['hash'] ) ) {
				continue;
			}

			// Reset usage counter if the current week window has elapsed.
			$week_start = $entry['rate_limit_reset'] ?? 0;
			if ( time() >= $week_start ) {
				$keys[ $index ]['usage_count']      = 0;
				$keys[ $index ]['rate_limit_reset']  = strtotime( 'next monday midnight' );
			}

			// Check rate limit per week.
			$usage = $keys[ $index ]['usage_count'] ?? 0;
			if ( $usage >= self::RATE_LIMIT_PER_WEEK ) {
				return new WP_Error(
					'rate_limit_exceeded',
					'API key rate limit exceeded ('.self::RATE_LIMIT_PER_WEEK.' requests/week).',
					[ 'status' => 429 ]
				);
			}

			// Increment usage and store the latest IP.
			$keys[ $index ]['usage_count'] = $usage + 1;
			$keys[ $index ]['last_ip']     = self::get_client_ip();
			$keys[ $index ]['last_used']   = time();
			$validated                       = true;

			break;
		}

		if ( ! $validated ) {
			if(!empty($username)) {
				do_action( 'wp_login_failed', $username, new \WP_Error(
					'invalid_api_key',
					'Invalid scoped API key.'
				) );
			}
			return false;
		} else {
			update_user_meta( $user_id, self::META_KEY, $keys );
			return true;
		}
	}

	/**
	 * Get the client IP address.
	 *
	 * @return string
	 */
	private static function get_client_ip(): string {
		$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
	}

	/**
	 * Revoke all scoped keys for a user (or just a specific scope).
	 *
	 * @param int         $user_id The user ID.
	 * @param string|null $scope   Optional scope to revoke. Null revokes all.
	 */
	public static function revoke( int $user_id, ?string $scope = null ): void {
		if ( null === $scope ) {
			delete_user_meta( $user_id, self::META_KEY );
			return;
		}

		$keys = get_user_meta( $user_id, self::META_KEY, true ) ?: [];
		$keys = array_filter( $keys, fn( $entry ) => $entry['scope'] !== $scope );
		update_user_meta( $user_id, self::META_KEY, $keys );
	}
}