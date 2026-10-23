<?php
/**
 * Read-only client for the other FreeScout's API, from its API & Webhooks module.
 *
 * @package WordPressdotorg\FreeScout\WPOrgFreeScoutImport
 */

declare( strict_types = 1 );

namespace Modules\WPOrgFreeScoutImport\Services;

use Carbon\Carbon;
use GuzzleHttp\ClientInterface;
use Modules\WPOrgFreeScoutImport\Exceptions\ApiError;
use Psr\Http\Message\ResponseInterface;

/**
 * Reads mailboxes, users, tags, conversations with their threads, and attachments.
 *
 * @see https://api-docs.freescout.net/
 */
final class Source {

	/**
	 * Conversations per page of the list: a page is one job, and FreeScout's single worker takes email first.
	 *
	 * @var int
	 */
	public const PAGE_SIZE = 25;

	/**
	 * Largest image copied from an email's body, in bytes.
	 *
	 * @var int
	 */
	private const MAX_IMAGE_BYTES = 20 * 1024 * 1024;

	/**
	 * Base URL of the other FreeScout, with a trailing slash.
	 *
	 * @var string
	 */
	private $url;

	/**
	 * Global API key of its API & Webhooks module.
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * HTTP client.
	 *
	 * @var ClientInterface
	 */
	private $http;

	/**
	 * Constructor.
	 *
	 * @param string          $url     Base URL of the other FreeScout.
	 * @param string          $api_key Its global API key.
	 * @param ClientInterface $http    HTTP client.
	 */
	public function __construct( string $url, string $api_key, ClientInterface $http ) {
		$this->url     = '' !== $url ? rtrim( $url, '/' ) . '/' : '';
		$this->api_key = $api_key;
		$this->http    = $http;
	}

	/**
	 * Creates a client from the settings on the import page.
	 *
	 * @return self
	 */
	public static function from_settings(): self {
		return new self(
			Settings::url(),
			Settings::api_key(),
			new \GuzzleHttp\Client(
				\Helper::setGuzzleDefaultOptions(
					array(
						'timeout'         => 60,
						'connect_timeout' => 10,
						'allow_redirects' => false,
						'http_errors'     => false,
						'headers'         => array( 'User-Agent' => 'FreeScout/' . config( 'app.version' ) . ' | WPOrgFreeScoutImport/1.0.0' ),
					)
				)
			)
		);
	}

	/**
	 * Whether the other FreeScout and its API key are configured.
	 *
	 * @return bool
	 */
	public function is_configured(): bool {
		return '' !== $this->url && '' !== $this->api_key;
	}

	/**
	 * The other FreeScout's host name, which names it on the import pages.
	 *
	 * @return string
	 */
	public function name(): string {
		return (string) parse_url( $this->url, PHP_URL_HOST );
	}

	/**
	 * Lists its mailboxes.
	 *
	 * @return array[] Mailboxes, with `id`, `name` and `email`.
	 */
	public function mailboxes(): array {
		return $this->all( 'api/mailboxes', array(), 'mailboxes' );
	}

	/**
	 * Lists its users.
	 *
	 * @return array[] Users, with `id`, `firstName`, `lastName`, and `email`.
	 */
	public function users(): array {
		return $this->all( 'api/users', array(), 'users' );
	}

	/**
	 * Lists its tags' colors; conversations list tags without them.
	 *
	 * @return int[] The Tags module's color number, by tag name; empty if the module is off there.
	 *
	 * @throws ApiError If they couldn't be read.
	 */
	public function tag_colors(): array {
		try {
			$tags = $this->all( 'api/tags', array(), 'tags' );
		} catch ( ApiError $e ) {
			// The API refuses the list without the Tags module.
			if ( 400 === $e->status ) {
				return array();
			}

			throw $e;
		}

		$colors = array();
		foreach ( $tags as $tag ) {
			$colors[ (string) ( $tag['name'] ?? '' ) ] = (int) ( $tag['color'] ?? 0 );
		}

		return $colors;
	}

	/**
	 * Gets a page of a mailbox's conversations, with their threads and tags, in the order they were numbered.
	 *
	 * New conversations get the next number, so they're added at the end of the list, rather than moving what's on
	 * the pages read already. Every status and state is listed, so conversations don't drop out of the list when
	 * they're closed, marked spam, or deleted during a run.
	 *
	 * @param int         $mailbox_id Mailbox ID there.
	 * @param int         $page       Page, from 1.
	 * @param Carbon|null $since      Only conversations changed since then.
	 * @return array `conversations`, `pages`, and `total`.
	 */
	public function conversations( int $mailbox_id, int $page, ?Carbon $since ): array {
		$query = array(
			'mailboxId' => $mailbox_id,
			'embed'     => 'threads,tags',
			'sortField' => 'number',
			'sortOrder' => 'asc',
			'page'      => $page,
			'pageSize'  => self::PAGE_SIZE,
		);
		if ( $since ) {
			$query['updatedSince'] = $since->copy()->setTimezone( 'UTC' )->format( 'Y-m-d\TH:i:s\Z' );
		}

		$body = $this->get( 'api/conversations', $query );

		return array(
			'conversations' => array_values( (array) ( $body['_embedded']['conversations'] ?? array() ) ),
			'pages'         => (int) ( $body['page']['totalPages'] ?? 0 ),
			'total'         => (int) ( $body['page']['totalElements'] ?? 0 ),
		);
	}

	/**
	 * Gets one conversation, with its threads and tags.
	 *
	 * @param int $conversation_id Conversation ID there.
	 * @return array|null Null if it's gone.
	 *
	 * @throws ApiError If it couldn't be read.
	 */
	public function conversation( int $conversation_id ): ?array {
		try {
			return $this->get( 'api/conversations/' . $conversation_id, array( 'embed' => 'threads,tags' ) );
		} catch ( ApiError $e ) {
			if ( 404 === $e->status ) {
				return null;
			}

			throw $e;
		}
	}

	/**
	 * Whether a URL is on the other FreeScout: only its files are downloaded.
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	public function is_own( string $url ): bool {
		$own   = parse_url( $this->url );
		$other = parse_url( $url );

		return is_array( $own ) && is_array( $other )
			&& strtolower( (string) ( $own['scheme'] ?? '' ) ) === strtolower( (string) ( $other['scheme'] ?? '' ) )
			&& strtolower( (string) ( $own['host'] ?? '' ) ) === strtolower( (string) ( $other['host'] ?? '' ) )
			&& ( $own['port'] ?? null ) === ( $other['port'] ?? null );
	}

	/**
	 * Downloads an attachment into a temporary file.
	 *
	 * Attachment links carry their own token, so they need no API key.
	 *
	 * @param string $url The attachment's `fileUrl`.
	 * @return resource|null Null if it's gone, or isn't the other FreeScout's.
	 *
	 * @throws ApiError          If it couldn't be downloaded otherwise.
	 * @throws \RuntimeException If it couldn't be saved.
	 */
	public function attachment( string $url ) {
		if ( ! $this->is_own( $url ) ) {
			return null;
		}

		$response = $this->send( 'GET', $url, array( 'stream' => true ) );
		$status   = $response->getStatusCode();
		if ( 404 === $status ) {
			return null;
		}

		if ( 200 !== $status ) {
			throw new ApiError( 'The other FreeScout answered ' . $status . ' for an attachment.', $status );
		}

		$file = self::to_file( $response, PHP_INT_MAX, true );
		if ( ! $file ) {
			throw new \RuntimeException( 'Could not save an attachment to a temporary file.' );
		}

		return $file;
	}

	/**
	 * Downloads an image from an email's body into a temporary file.
	 *
	 * @param string $url Image URL, on the other FreeScout.
	 * @return resource|null Null if it couldn't be downloaded, or is too large.
	 */
	public function image( string $url ) {
		if ( ! $this->is_own( $url ) ) {
			return null;
		}

		try {
			$response = $this->send( 'GET', $url, array( 'stream' => true ) );
		} catch ( ApiError $e ) {
			return null;
		}

		return 200 === $response->getStatusCode() ? self::to_file( $response, self::MAX_IMAGE_BYTES ) : null;
	}

	/**
	 * Copies a response's body into a temporary file, up to a size.
	 *
	 * @param ResponseInterface $response Response.
	 * @param int               $max      Largest size, in bytes.
	 * @param bool              $empty_ok Whether an empty body is fine.
	 * @return resource|null Null if it's larger, empty, or couldn't be written.
	 */
	private static function to_file( ResponseInterface $response, int $max, bool $empty_ok = false ) {
		$body    = $response->getBody();
		$file    = tmpfile();
		$size    = 0;
		$written = true;

		while ( $file && $written && ! $body->eof() && $size <= $max ) {
			$chunk = $body->read( 1024 * 1024 );
			if ( '' === $chunk ) {
				break;
			}
			// A short write, like on a full disk, would leave the file cut off.
			$written = fwrite( $file, $chunk ) === strlen( $chunk );
			$size   += strlen( $chunk );
		}
		$body->close();

		if ( ! $file || ! $written || ( 0 === $size && ! $empty_ok ) || $size > $max ) {
			if ( $file ) {
				fclose( $file );
			}

			return null;
		}

		rewind( $file );

		return $file;
	}

	/**
	 * Gets every page of a list.
	 *
	 * @param string $path  Path.
	 * @param array  $query Query.
	 * @param string $key   Key of the items under `_embedded`.
	 * @return array[]
	 */
	private function all( string $path, array $query, string $key ): array {
		$items = array();
		$page  = 1;

		do {
			$body  = $this->get( $path, $query + array( 'page' => $page ) );
			$items = array_merge( $items, (array) ( $body['_embedded'][ $key ] ?? array() ) );
			$pages = (int) ( $body['page']['totalPages'] ?? 1 );
			++$page;
		} while ( $page <= $pages );

		return $items;
	}

	/**
	 * Gets JSON from the API.
	 *
	 * @param string $path  Path.
	 * @param array  $query Query.
	 * @return array
	 *
	 * @throws ApiError If it failed, or its answer isn't JSON.
	 */
	private function get( string $path, array $query = array() ): array {
		if ( ! $this->is_configured() ) {
			throw new ApiError( 'The other FreeScout’s address and API key aren’t set.' );
		}

		$response = $this->send(
			'GET',
			$this->url . $path,
			array(
				'query'   => $query,
				'headers' => array(
					'X-FreeScout-API-Key' => $this->api_key,
					'Accept'              => 'application/json',
				),
			)
		);
		$status   = $response->getStatusCode();

		if ( $status < 200 || $status >= 300 ) {
			throw new ApiError( 'The other FreeScout answered ' . $status . ' for ' . $path . '.', $status );
		}

		$body = json_decode( (string) $response->getBody(), true );
		if ( ! is_array( $body ) ) {
			throw new ApiError( 'The other FreeScout’s answer for ' . $path . ' isn’t JSON.', $status );
		}

		return $body;
	}

	/**
	 * Sends a request.
	 *
	 * @param string $method  HTTP method.
	 * @param string $url     URL.
	 * @param array  $options Guzzle options.
	 * @return ResponseInterface
	 *
	 * @throws ApiError If it couldn't be reached.
	 */
	private function send( string $method, string $url, array $options ): ResponseInterface {
		try {
			return $this->http->request( $method, $url, $options + array( 'http_errors' => false ) );
		} catch ( \Throwable $e ) {
			throw new ApiError( 'The other FreeScout could not be reached: ' . $e->getMessage(), 0, $e );
		}
	}
}
