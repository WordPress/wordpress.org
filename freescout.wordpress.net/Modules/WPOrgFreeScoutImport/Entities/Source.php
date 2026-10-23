<?php
/**
 * A FreeScout that mailboxes are imported from.
 *
 * @package WordPressdotorg\FreeScout\WPOrgFreeScoutImport
 */

declare( strict_types = 1 );

namespace Modules\WPOrgFreeScoutImport\Entities;

use Illuminate\Database\Eloquent\Model;

/**
 * Row in wporgfreescoutimport_sources.
 *
 * @property int         $id
 * @property string      $name    What the import pages call it.
 * @property string      $url     Its https address, without a trailing slash.
 * @property string|null $api_key Its global API key, encrypted; null once forgotten.
 */
final class Source extends Model {

	/**
	 * Table name.
	 *
	 * @var string
	 */
	protected $table = 'wporgfreescoutimport_sources';

	/**
	 * Attributes kept out of arrays and JSON.
	 *
	 * @var array
	 */
	protected $hidden = array( 'api_key' );

	/**
	 * Its API key, decrypted.
	 *
	 * @return string Empty if none is set, or it can't be decrypted, like after the app key changed.
	 */
	public function key(): string {
		if ( null === $this->api_key || '' === $this->api_key ) {
			return '';
		}

		try {
			return (string) decrypt( $this->api_key );
		} catch ( \Throwable $e ) {
			return '';
		}
	}

	/**
	 * Whether an API key is set.
	 *
	 * @return bool
	 */
	public function has_key(): bool {
		return '' !== $this->key();
	}

	/**
	 * Sets its address and name, and its key unless it's empty, so the saved one stays.
	 *
	 * The key goes in every request, so the address has to be https, and a saved key is forgotten when the address
	 * names another host: it would go to that one. Once something was imported from it, its host stays: what was
	 * imported is matched up by it, and another FreeScout's IDs would match the wrong conversations and users.
	 *
	 * @param string $url     Address, with https.
	 * @param string $name    Name, or empty for the address's host.
	 * @param string $api_key API key, or empty to keep the saved one.
	 * @return string Why it can't take the address, or '' once it's set.
	 */
	public function configure( string $url, string $name, string $api_key ): string {
		$url  = rtrim( trim( $url ), '/' );
		$host = strtolower( (string) parse_url( $url, PHP_URL_HOST ) );
		if ( 'https' !== strtolower( (string) parse_url( $url, PHP_URL_SCHEME ) ) || '' === $host || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return __( 'Enter the FreeScout’s https address, like https://scout.wc-us.org.' );
		}

		$other_host = $this->exists && strtolower( (string) parse_url( (string) $this->url, PHP_URL_HOST ) ) !== $host;
		if ( $other_host && $this->has_imports() ) {
			return __( ':name’s address can’t move to another host: what was imported from it is matched up by it. Add the other FreeScout as a source of its own.', array( 'name' => $this->name ) );
		}

		$api_key = trim( $api_key );
		if ( '' !== $api_key ) {
			$this->api_key = encrypt( $api_key );
		} elseif ( $other_host ) {
			$this->api_key = null;
		}

		$name       = trim( $name );
		$this->url  = $url;
		$this->name = mb_substr( '' !== $name ? $name : $host, 0, 191 );

		return '';
	}

	/**
	 * Whether anything was imported from it; then it can't be deleted, or move to another host, as its mappings would
	 * go or match another FreeScout's IDs.
	 *
	 * @return bool
	 */
	public function has_imports(): bool {
		return Run::query()->where( 'source_id', $this->id )->exists()
			|| ImportedConversation::query()->where( 'source_id', $this->id )->exists();
	}
}
