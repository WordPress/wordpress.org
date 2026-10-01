<?php
/**
 * Read-only client for HelpScout's Mailbox API.
 *
 * @package WordPressdotorg\FreeScout\WPOrgHelpScoutImport
 */

declare( strict_types = 1 );

namespace Modules\WPOrgHelpScoutImport\Services;

use Carbon\Carbon;
use GuzzleHttp\ClientInterface;
use Modules\WPOrgHelpScoutImport\Exceptions\ApiError;
use Modules\WPOrgHelpScoutImport\Exceptions\RateLimited;
use Psr\Http\Message\ResponseInterface;

/**
 * Reads mailboxes, users, conversations, threads, attachments, and emails' original source.
 *
 * @see https://developer.helpscout.com/mailbox-api/
 */
final class HelpScout {

	/**
	 * Cache key for the access token.
	 *
	 * @var string
	 */
	private const TOKEN_CACHE_KEY = 'wporghelpscoutimport.token';

	/**
	 * App ID.
	 *
	 * @var string
	 */
	private $app_id;

	/**
	 * App secret.
	 *
	 * @var string
	 */
	private $app_secret;

	/**
	 * Base URL of the API, with a trailing slash.
	 *
	 * @var string
	 */
	private $api_url;

	/**
	 * Requests per minute left to HelpScout's other users.
	 *
	 * @var int
	 */
	private $reserve;

	/**
	 * HTTP client.
	 *
	 * @var ClientInterface
	 */
	private $http;

	/**
	 * Unix time before which no request is sent, once only the reserve is left.
	 *
	 * @var int
	 */
	private $wait_until = 0;

	/**
	 * Constructor.
	 *
	 * @param string          $app_id     App ID.
	 * @param string          $app_secret App secret.
	 * @param string          $api_url    Base URL of the API.
	 * @param int             $reserve    Requests per minute left to HelpScout's other users.
	 * @param ClientInterface $http       HTTP client.
	 */
	public function __construct( string $app_id, string $app_secret, string $api_url, int $reserve, ClientInterface $http ) {
		$this->app_id     = $app_id;
		$this->app_secret = $app_secret;
		$this->api_url    = rtrim( $api_url, '/' ) . '/';
		$this->reserve    = max( 0, $reserve );
		$this->http       = $http;
	}

	/**
	 * Creates a client from the module configuration.
	 *
	 * @return self
	 */
	public static function from_config(): self {
		return new self(
			(string) config( 'wporghelpscoutimport.app_id' ),
			(string) config( 'wporghelpscoutimport.app_secret' ),
			(string) config( 'wporghelpscoutimport.api_url' ),
			(int) config( 'wporghelpscoutimport.reserve' ),
			new \GuzzleHttp\Client(
				\Helper::setGuzzleDefaultOptions(
					array(
						'timeout'         => 60,
						'connect_timeout' => 10,
						'allow_redirects' => false,
						'http_errors'     => false,
						'headers'         => array( 'User-Agent' => 'FreeScout/' . config( 'app.version' ) . ' | WPOrgHelpScoutImport/1.0.0' ),
					)
				)
			)
		);
	}

	/**
	 * Whether the app's credentials are configured.
	 *
	 * @return bool
	 */
	public function is_configured(): bool {
		return '' !== $this->app_id && '' !== $this->app_secret;
	}

	/**
	 * Lists the account's mailboxes.
	 *
	 * @return array[] Mailboxes, with `id`, `name` and `email`.
	 */
	public function mailboxes(): array {
		return $this->all( 'v2/mailboxes', array(), 'mailboxes' );
	}

	/**
	 * Lists the users who can see a mailbox.
	 *
	 * @param int $mailbox_id HelpScout mailbox ID.
	 * @return array[] Users, with `id`, `firstName`, `lastName` and `email`.
	 */
	public function users( int $mailbox_id ): array {
		return $this->all( 'v2/users', array( 'mailbox' => $mailbox_id ), 'users' );
	}

	/**
	 * Gets one page of a mailbox's conversations, oldest first.
	 *
	 * @param int         $mailbox_id HelpScout mailbox ID.
	 * @param int         $page       Page number, from 1.
	 * @param Carbon|null $since      Only conversations changed since then, in the order they changed.
	 * @return array {
	 *     @type array[] $conversations Conversations on the page.
	 *     @type int     $pages         Number of pages.
	 *     @type int     $total         Number of conversations.
	 * }
	 */
	public function conversations( int $mailbox_id, int $page, ?Carbon $since ): array {
		$query = array(
			'mailbox'   => $mailbox_id,
			'status'    => 'all',
			'sortField' => $since ? 'modifiedAt' : 'createdAt',
			'sortOrder' => 'asc',
			'page'      => $page,
		);
		if ( $since ) {
			$query['modifiedSince'] = $since->copy()->setTimezone( 'UTC' )->format( 'Y-m-d\TH:i:s\Z' );
		}

		$body = $this->get( 'v2/conversations', $query );

		return array(
			'conversations' => (array) ( $body['_embedded']['conversations'] ?? array() ),
			'pages'         => (int) ( $body['page']['totalPages'] ?? 0 ),
			'total'         => (int) ( $body['page']['totalElements'] ?? 0 ),
		);
	}

	/**
	 * Lists all of a conversation's threads, oldest first.
	 *
	 * @param int $conversation_id HelpScout conversation ID.
	 * @return array[] Threads.
	 */
	public function threads( int $conversation_id ): array {
		$threads = $this->all( 'v2/conversations/' . $conversation_id . '/threads', array(), 'threads' );

		usort(
			$threads,
			static function ( array $a, array $b ): int {
				return array( (string) ( $a['createdAt'] ?? '' ), (int) ( $a['id'] ?? 0 ) ) <=> array( (string) ( $b['createdAt'] ?? '' ), (int) ( $b['id'] ?? 0 ) );
			}
		);

		return $threads;
	}

	/**
	 * Gets an attachment's content.
	 *
	 * @param int $conversation_id HelpScout conversation ID.
	 * @param int $attachment_id   HelpScout attachment ID.
	 * @return string The file's bytes.
	 *
	 * @throws ApiError If HelpScout didn't return it.
	 */
	public function attachment( int $conversation_id, int $attachment_id ): string {
		$body = $this->get( 'v2/conversations/' . $conversation_id . '/attachments/' . $attachment_id . '/data' );
		$data = base64_decode( (string) ( $body['data'] ?? '' ), true );

		if ( false === $data ) {
			throw new ApiError( 'HelpScout returned no data for attachment ' . $attachment_id . '.' );
		}

		return $data;
	}

	/**
	 * Gets the email a thread was made from, or that HelpScout sent for it.
	 *
	 * @param int $conversation_id HelpScout conversation ID.
	 * @param int $thread_id       HelpScout thread ID.
	 * @return string|null The raw email, or null if HelpScout has none.
	 *
	 * @throws ApiError If the request failed otherwise.
	 */
	public function original_source( int $conversation_id, int $thread_id ): ?string {
		try {
			$response = $this->request(
				'v2/conversations/' . $conversation_id . '/threads/' . $thread_id . '/original-source',
				array(),
				'message/rfc822'
			);
		} catch ( ApiError $e ) {
			if ( 404 === $e->status ) {
				return null;
			}

			throw $e;
		}

		return (string) $response->getBody();
	}

	/**
	 * Gets every page of a list.
	 *
	 * @param string $path  API path.
	 * @param array  $query Query parameters.
	 * @param string $key   Key of the list in `_embedded`.
	 * @return array[] All items.
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
	 * Gets a JSON resource.
	 *
	 * @param string $path  API path.
	 * @param array  $query Query parameters.
	 * @return array Decoded body.
	 *
	 * @throws ApiError If the request failed, or the body isn't JSON.
	 */
	private function get( string $path, array $query = array() ): array {
		$body = json_decode( (string) $this->request( $path, $query, 'application/json' )->getBody(), true );

		if ( ! is_array( $body ) ) {
			throw new ApiError( 'HelpScout returned no JSON for ' . $path . '.' );
		}

		return $body;
	}

	/**
	 * Sends a GET request with the access token, getting a new token once if HelpScout rejects it.
	 *
	 * @param string $path   API path.
	 * @param array  $query  Query parameters.
	 * @param string $accept Accept header.
	 * @return ResponseInterface Successful response.
	 *
	 * @throws RateLimited If the import has to wait for the rate limit.
	 * @throws ApiError    If the request failed.
	 */
	private function request( string $path, array $query, string $accept ): ResponseInterface {
		if ( ! $this->is_configured() ) {
			throw new ApiError( 'WPORG_HELPSCOUT_APP_ID and WPORG_HELPSCOUT_APP_SECRET are not configured.' );
		}

		foreach ( array( false, true ) as $new_token ) {
			if ( $this->wait_until > time() ) {
				throw new RateLimited( $this->wait_until - time() );
			}

			$response = $this->send(
				'GET',
				$this->api_url . $path,
				array(
					'query'   => $query,
					'headers' => array(
						'Authorization' => 'Bearer ' . $this->token( $new_token ),
						'Accept'        => $accept,
					),
				)
			);
			$status   = $response->getStatusCode();

			$this->note_rate_limit( $response );

			if ( 401 === $status && ! $new_token ) {
				continue;
			}

			if ( 429 === $status ) {
				throw new RateLimited( (int) $response->getHeaderLine( 'X-RateLimit-Retry-After' ) );
			}

			if ( $status < 200 || $status >= 300 ) {
				throw new ApiError( 'HelpScout answered ' . $status . ' for ' . $path . '.', $status );
			}

			return $response;
		}

		throw new ApiError( 'HelpScout rejected the access token for ' . $path . '.', 401 );
	}

	/**
	 * Waits until the next minute once only the reserve is left of HelpScout's rate limit.
	 *
	 * @param ResponseInterface $response Response.
	 * @return void
	 */
	private function note_rate_limit( ResponseInterface $response ): void {
		$remaining = $response->getHeaderLine( 'X-RateLimit-Remaining-Minute' );

		if ( '' !== $remaining && (int) $remaining <= $this->reserve ) {
			$this->wait_until = time() + 60 - (int) gmdate( 's' );
		}
	}

	/**
	 * Gets an access token through the client credentials flow; it's cached for as long as it's valid.
	 *
	 * @param bool $renew Whether to get a new one, because HelpScout rejected the cached one.
	 * @return string Access token.
	 *
	 * @throws ApiError If HelpScout gave none.
	 */
	private function token( bool $renew ): string {
		$token = $renew ? '' : (string) \Cache::get( self::TOKEN_CACHE_KEY, '' );
		if ( '' !== $token ) {
			return $token;
		}

		$response = $this->send(
			'POST',
			$this->api_url . 'v2/oauth2/token',
			array(
				'form_params' => array(
					'grant_type'    => 'client_credentials',
					'client_id'     => $this->app_id,
					'client_secret' => $this->app_secret,
				),
			)
		);
		$body     = json_decode( (string) $response->getBody(), true );
		$token    = is_array( $body ) ? (string) ( $body['access_token'] ?? '' ) : '';

		if ( 200 !== $response->getStatusCode() || '' === $token ) {
			throw new ApiError( 'HelpScout gave no access token; check the app ID and secret.', 401 === $response->getStatusCode() ? 401 : 403 );
		}

		// Cache minutes, renewed a few minutes early.
		$minutes = max( 1, intdiv( (int) ( $body['expires_in'] ?? 0 ), 60 ) - 5 );
		\Cache::put( self::TOKEN_CACHE_KEY, $token, $minutes );

		return $token;
	}

	/**
	 * Sends a request, turning transport errors into ApiError.
	 *
	 * @param string $method  HTTP method.
	 * @param string $url     URL.
	 * @param array  $options Guzzle options.
	 * @return ResponseInterface
	 *
	 * @throws ApiError If HelpScout couldn't be reached.
	 */
	private function send( string $method, string $url, array $options ): ResponseInterface {
		try {
			return $this->http->request( $method, $url, $options + array( 'http_errors' => false ) );
		} catch ( \Throwable $e ) {
			throw new ApiError( 'HelpScout could not be reached: ' . $e->getMessage(), 0, $e );
		}
	}
}
