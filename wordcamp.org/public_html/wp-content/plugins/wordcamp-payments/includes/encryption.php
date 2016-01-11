<?php
/**
 * WordCamp Payments Encryption
 *
 * Usage:
 *
 * WCP_Encryption::encrypt() to encrypt a string
 * WCP_Encryption::decrypt() to decrypt a string
 * WCP_Encription::maybe_decrypt() to decrypt a string that may or may not be encrypted.
 */
class WCP_Encryption {
	public static $key = null;
	public static $hmac_key = null;

	/**
	 * Read some secrets.
	 */
	public static function init() {
		if ( is_null( self::$key ) ) {
			self::$key = '';
			self::$hmac_key = '';

			if ( defined( 'WORDCAMP_PAYMENTS_ENCRYPTION_KEY' ) && WORDCAMP_PAYMENTS_ENCRYPTION_KEY )
				self::$key = WORDCAMP_PAYMENTS_ENCRYPTION_KEY;

			if ( defined( 'WORDCAMP_PAYMENTS_HMAC_KEY' ) && WORDCAMP_PAYMENTS_HMAC_KEY )
				self::$hmac_key = WORDCAMP_PAYMENTS_HMAC_KEY;
		}

		return ( ! empty( self::$key ) && ! empty( self::$hmac_key ) );
	}

	/**
	 * Encrypt some data.
	 *
	 * @param string $raw_data The string to encrypt.
	 * @return string|object Encrypted string (encrypted:data:key:iv:hmac) or WP_Error.
	 */
	public static function encrypt( $raw_data ) {
		if ( ! is_string( $raw_data ) )
			return new WP_Error( 'encryption-error', 'Only strings can be encrypted.' );

		if ( ! self::init() )
			return new WP_Error( 'encryption-error', 'Could not init encryption keys.' );

		$iv = openssl_random_pseudo_bytes( 16, $is_iv_strong );

		if ( ! $is_iv_strong )
			return new WP_Error( 'encryption-error', 'Could not obtain a strong iv.' );

		$data = array();
		$data['data'] = openssl_encrypt( $raw_data, 'aes-256-ctr', self::$key, true, $iv );
		$data['hmac'] = hash_hmac( 'sha256', $data['data'], self::$hmac_key, true );
		$data['iv'] = $iv;

		if ( ! $data['data'] || ! $data['iv'] || ! $data['hmac'] )
			return new WP_Error( 'encryption-error', 'Could not encrypt the data.' );

		$data = array_map( 'base64_encode', $data );
		return sprintf( 'encrypted:%s:%s:%s', $data['data'], $data['iv'], $data['hmac'] );
	}

	/**
	 * Decrypt some data.
	 *
	 * @param string $data The data to decrypt.
	 * @return string|object The decrypted data or WP_Error.
	 */
	public static function decrypt( $data ) {
		if ( ! is_string( $data ) )
			return new WP_Error( 'encryption-error', 'Only strings can be decrypted.' );

		if ( ! self::init() )
			return new WP_Error( 'encryption-error', 'Could not init encryption keys.' );

		$data = explode( ':', $data );
		$data = array_map( 'base64_decode', $data );
		list( $null, $data, $iv, $hmac ) = $data;

		// Verify hmac.
		if ( ! hash_equals( hash_hmac( 'sha256', $data, self::$hmac_key, true ), $hmac ) )
			return new WP_Error( 'encryption-error', 'HMAC mismatch.' );

		$data = openssl_decrypt( $data, 'aes-256-ctr', self::$key, true, $iv );
		return $data;
	}

	/**
	 * Look for encrypted:... and run self::decrypt() if found.
	 *
	 * @param string $data Maybe some encrypted data.
	 * @param object $error Null or WP_Error on error (by reference).
	 * @return mixed The decrypted data, an empty string on decryption error, or anything else that's passed and isn't a string.
	 */
	public static function maybe_decrypt( $data, &$error = null ) {
		if ( ! is_string( $data ) )
			return $data;

		if ( strpos( $data, 'encrypted:' ) !== 0 )
			return $data;

		$decrypted = self::decrypt( $data );
		if ( is_wp_error( $decrypted ) ) {
			$error = $decrypted;
			return '';
		}

		return $decrypted;
	}
}
