<?php
/**
 * Where the other FreeScout is, and its API key, as administrators set them on the import page.
 *
 * @package WordPressdotorg\FreeScout\WPOrgFreeScoutImport
 */

declare( strict_types = 1 );

namespace Modules\WPOrgFreeScoutImport\Services;

/**
 * Keeps them in FreeScout's options; the key encrypted with the app key, since it reads everything there.
 */
final class Settings {

	/**
	 * Option for the other FreeScout's address.
	 *
	 * @var string
	 */
	public const URL_OPTION = 'wporgfreescoutimport_url';

	/**
	 * Option for its API & Webhooks module's global API key, encrypted.
	 *
	 * @var string
	 */
	public const KEY_OPTION = 'wporgfreescoutimport_api_key';

	/**
	 * The other FreeScout's address, like https://scout.wc-us.org.
	 *
	 * @return string Empty if none is set.
	 */
	public static function url(): string {
		return (string) \Option::get( self::URL_OPTION, '', true, false );
	}

	/**
	 * Its API key.
	 *
	 * @return string Empty if none is set, or it can't be decrypted, like after the app key changed.
	 */
	public static function api_key(): string {
		$encrypted = (string) \Option::get( self::KEY_OPTION, '', true, false );
		if ( '' === $encrypted ) {
			return '';
		}

		try {
			return (string) decrypt( $encrypted );
		} catch ( \Throwable $e ) {
			return '';
		}
	}

	/**
	 * Whether an API key is set.
	 *
	 * @return bool
	 */
	public static function has_api_key(): bool {
		return '' !== self::api_key();
	}

	/**
	 * Sets the address, and the key unless it's empty, so the saved one stays.
	 *
	 * @param string $url     Address, with http or https.
	 * @param string $api_key API key, or empty to keep the saved one.
	 * @return bool Whether the address is one.
	 */
	public static function save( string $url, string $api_key ): bool {
		$url    = rtrim( trim( $url ), '/' );
		$scheme = strtolower( (string) parse_url( $url, PHP_URL_SCHEME ) );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) || ! parse_url( $url, PHP_URL_HOST ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return false;
		}

		\Option::set( self::URL_OPTION, $url );

		$api_key = trim( $api_key );
		if ( '' !== $api_key ) {
			\Option::set( self::KEY_OPTION, encrypt( $api_key ) );
		}

		return true;
	}

	/**
	 * Forgets the API key, like after the last import.
	 *
	 * @return void
	 */
	public static function forget_api_key(): void {
		\Option::remove( self::KEY_OPTION );
	}
}
