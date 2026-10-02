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
	 * Largest image copied from an email's body, in bytes.
	 *
	 * @var int
	 */
	private const MAX_IMAGE_BYTES = 20 * 1024 * 1024;

	/**
	 * Most of an email read for its headers, in bytes; the rest is its body and attachments.
	 *
	 * @var int
	 */
	private const MAX_HEADER_BYTES = 256 * 1024;

	/**
	 * How many times a patient request waits for the rate limit before giving up.
	 *
	 * @var int
	 */
	private const MAX_WAITS = 5;

	/**
	 * Longest a patient request waits for the rate limit at once, in seconds.
	 *
	 * @var int
	 */
	private const MAX_WAIT_SECONDS = 60;

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
	 * Whether to wait for the rate limit rather than throw RateLimited.
	 *
	 * @var bool
	 */
	private $patient = false;

	/**
	 * Waits a number of seconds.
	 *
	 * @var callable
	 */
	private $sleep;

	/**
	 * Says whether a patient request may wait right now; always, if null.
	 *
	 * @var callable|null
	 */
	private $may_wait;

	/**
	 * Constructor.
	 *
	 * @param string          $app_id     App ID.
	 * @param string          $app_secret App secret.
	 * @param string          $api_url    Base URL of the API.
	 * @param int             $reserve    Requests per minute left to HelpScout's other users.
	 * @param ClientInterface $http       HTTP client.
	 * @param callable|null   $sleep      Waits a number of seconds; sleep() if null.
	 */
	public function __construct( string $app_id, string $app_secret, string $api_url, int $reserve, ClientInterface $http, ?callable $sleep = null ) {
		$this->app_id     = $app_id;
		$this->app_secret = $app_secret;
		$this->api_url    = rtrim( $api_url, '/' ) . '/';
		$this->reserve    = max( 0, $reserve );
		$this->http       = $http;
		$this->sleep      = $sleep ?? 'sleep';
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
	 * Sets whether requests wait for the rate limit, rather than throw RateLimited.
	 *
	 * Waiting holds up FreeScout's queue, so it's only for what can't finish otherwise: a conversation that needs more
	 * requests than one minute's share of the limit. Even then, it only waits while nothing else needs the queue.
	 *
	 * @param bool          $patient  Whether to wait.
	 * @param callable|null $may_wait Says whether waiting is fine right now; always, if null.
	 * @return void
	 */
	public function set_patient( bool $patient, ?callable $may_wait = null ): void {
		$this->patient  = $patient;
		$this->may_wait = $may_wait;
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
	 * Lists the account's users, or those who can see a mailbox.
	 *
	 * @param int $mailbox_id HelpScout mailbox ID, or 0 for all users.
	 * @return array[] Users, with `id`, `firstName`, `lastName` and `email`.
	 */
	public function users( int $mailbox_id = 0 ): array {
		return $this->all( 'v2/users', $mailbox_id ? array( 'mailbox' => $mailbox_id ) : array(), 'users' );
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
	 * Gets one conversation.
	 *
	 * @param int $conversation_id HelpScout conversation ID.
	 * @return array|null The conversation, or null if HelpScout no longer has it, or merged it into another.
	 *
	 * @throws ApiError If the request failed otherwise.
	 */
	public function conversation( int $conversation_id ): ?array {
		try {
			return $this->get( 'v2/conversations/' . $conversation_id );
		} catch ( ApiError $e ) {
			if ( in_array( $e->status, array( 301, 404 ), true ) ) {
				return null;
			}

			throw $e;
		}
	}

	/**
	 * The highest conversation number HelpScout has given out, in any mailbox.
	 *
	 * @return int
	 */
	public function highest_number(): int {
		$body = $this->get(
			'v2/conversations',
			array(
				'status'    => 'all',
				'sortField' => 'number',
				'sortOrder' => 'desc',
			)
		);

		return (int) ( $body['_embedded']['conversations'][0]['number'] ?? 0 );
	}

	/**
	 * Lists all of a conversation's threads, oldest first.
	 *
	 * @param int $conversation_id HelpScout conversation ID.
	 * @return array[] Threads.
	 */
	public function threads( int $conversation_id ): array {
		// A thread added between pages shows up on two of them: once is enough.
		$threads = array_values( array_column( $this->all( 'v2/conversations/' . $conversation_id . '/threads', array(), 'threads' ), null, 'id' ) );

		usort(
			$threads,
			static function ( array $a, array $b ): int {
				return array( (string) ( $a['createdAt'] ?? '' ), (int) ( $a['id'] ?? 0 ) ) <=> array( (string) ( $b['createdAt'] ?? '' ), (int) ( $b['id'] ?? 0 ) );
			}
		);

		return $threads;
	}

	/**
	 * Downloads an attachment into a temporary file, so large ones aren't held in memory.
	 *
	 * @param int $conversation_id HelpScout conversation ID.
	 * @param int $attachment_id   HelpScout attachment ID.
	 * @return resource The file, at its start; it's deleted once closed.
	 *
	 * @throws \RuntimeException If HelpScout didn't return it (an ApiError), or there's no room for it.
	 */
	public function attachment( int $conversation_id, int $attachment_id ) {
		$response = $this->request(
			'v2/conversations/' . $conversation_id . '/attachments/' . $attachment_id . '/file',
			array(),
			'*/*',
			array( 'stream' => true )
		);
		$file     = self::to_file( $response, PHP_INT_MAX );

		if ( ! $file ) {
			throw new \RuntimeException( 'Could not save attachment ' . $attachment_id . ' to a temporary file.' );
		}

		return $file;
	}

	/**
	 * Gets the headers of the email a thread was made from, or that HelpScout sent for it.
	 *
	 * Only the headers are read: the rest of the email can be large, and isn't needed.
	 *
	 * @param int $conversation_id HelpScout conversation ID.
	 * @param int $thread_id       HelpScout thread ID.
	 * @return string|null The email's headers, or null if HelpScout has none, or won't give them.
	 *
	 * @throws ApiError If HelpScout is unavailable, or refuses the app.
	 */
	public function original_headers( int $conversation_id, int $thread_id ): ?string {
		try {
			$response = $this->request(
				'v2/conversations/' . $conversation_id . '/threads/' . $thread_id . '/original-source',
				array(),
				'message/rfc822',
				array( 'stream' => true )
			);
		} catch ( ApiError $e ) {
			// Only the Message-ID is wanted from it: a thread without one is still imported.
			if ( $e->status >= 400 && $e->status < 500 && ! $e instanceof RateLimited && ! $e->is_denied() ) {
				return null;
			}

			throw $e;
		}

		$body    = $response->getBody();
		$headers = '';
		$read    = 0;

		while ( ! $body->eof() && $read < self::MAX_HEADER_BYTES ) {
			$chunk    = $body->read( 8192 );
			$headers .= $chunk;
			$read    += strlen( $chunk );
			if ( '' === $chunk || preg_match( '/\r?\n\r?\n/', $headers, $match, PREG_OFFSET_CAPTURE ) ) {
				$headers = isset( $match[0][1] ) ? substr( $headers, 0, $match[0][1] ) : $headers;
				break;
			}
		}
		$body->close();

		return $headers;
	}

	/**
	 * Downloads an image HelpScout hosts for an email's body; it needs no access token.
	 *
	 * @param string $url Image URL.
	 * @return resource|null The image in a temporary file, deleted once closed; null if it couldn't be downloaded.
	 */
	public function image( string $url ) {
		try {
			$response = $this->send( 'GET', $url, array( 'stream' => true ) );
		} catch ( ApiError $e ) {
			return null;
		}

		return 200 === $response->getStatusCode() ? self::to_file( $response, self::MAX_IMAGE_BYTES ) : null;
	}

	/**
	 * Lists the account's teams, as users of type `team`; HelpScout may list them with its users too.
	 *
	 * @return array[] Teams, with `id`, `type`, and their name as `firstName`.
	 */
	public function teams(): array {
		return array_map(
			static function ( array $team ): array {
				return array(
					'id'        => (int) ( $team['id'] ?? 0 ),
					'type'      => 'team',
					'firstName' => (string) ( $team['name'] ?? '' ),
					'lastName'  => '',
				);
			},
			$this->all( 'v2/teams', array(), 'teams' )
		);
	}

	/**
	 * Saves a response's body into a temporary file, without holding it in memory.
	 *
	 * @param ResponseInterface $response Response.
	 * @param int               $max      Most bytes it can have.
	 * @return resource|null The file, at its start, deleted once closed; null if it's empty, larger, or couldn't be saved whole.
	 */
	private static function to_file( ResponseInterface $response, int $max ) {
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

		if ( ! $file || ! $written || 0 === $size || $size > $max ) {
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
		$response = $this->request( $path, $query, 'application/json' );
		$body     = json_decode( (string) $response->getBody(), true );

		if ( ! is_array( $body ) ) {
			throw new ApiError( 'HelpScout returned no JSON for ' . $path . '.', $response->getStatusCode() );
		}

		return $body;
	}

	/**
	 * Sends a GET request with the access token, getting a new token once if HelpScout rejects it.
	 *
	 * @param string $path    API path.
	 * @param array  $query   Query parameters.
	 * @param string $accept  Accept header.
	 * @param array  $options More Guzzle options.
	 * @return ResponseInterface Successful response.
	 *
	 * @throws RateLimited If the import has to wait for the rate limit.
	 * @throws ApiError    If the request failed.
	 */
	private function request( string $path, array $query, string $accept, array $options = array() ): ResponseInterface {
		if ( ! $this->is_configured() ) {
			throw new ApiError( 'WPORG_HELPSCOUT_APP_ID and WPORG_HELPSCOUT_APP_SECRET are not configured.' );
		}

		$renew   = false;
		$renewed = false;
		$waits   = 0;

		while ( true ) {
			if ( $this->wait_for_rate_limit( $waits ) ) {
				++$waits;
			}

			$token = $this->token( $renew );
			$renew = false;

			$response = $this->send(
				'GET',
				$this->api_url . $path,
				$options + array(
					'query'   => $query,
					'headers' => array(
						'Authorization' => 'Bearer ' . $token,
						'Accept'        => $accept,
					),
				)
			);
			$status   = $response->getStatusCode();

			$this->note_rate_limit( $response );

			if ( 401 === $status && ! $renewed ) {
				$renew   = true;
				$renewed = true;
				continue;
			}

			if ( 429 === $status ) {
				$retry_after = max( 1, (int) $response->getHeaderLine( 'X-RateLimit-Retry-After' ) );
				if ( ! $this->patient ) {
					throw new RateLimited( $retry_after );
				}

				$this->wait_until = time() + $retry_after;
				continue;
			}

			if ( $status < 200 || $status >= 300 ) {
				// A 401 again, with a new token, means HelpScout refuses the app.
				throw new ApiError( 401 === $status ? 'HelpScout rejected the access token for ' . $path . '.' : 'HelpScout answered ' . $status . ' for ' . $path . '.', $status );
			}

			return $response;
		}
	}

	/**
	 * Waits for the rate limit when only the reserve is left: throws, or sleeps while patient.
	 *
	 * @param int $waits How many times this request waited already.
	 * @return bool Whether it waited.
	 *
	 * @throws RateLimited If the import has to wait, and isn't patient, or waited too often already.
	 */
	private function wait_for_rate_limit( int $waits ): bool {
		$seconds = $this->wait_until - time();
		if ( $seconds <= 0 ) {
			return false;
		}

		// Waiting holds up FreeScout's queue: no longer than a minute's limit takes to reset.
		if ( ! $this->patient || $waits >= self::MAX_WAITS || $seconds > self::MAX_WAIT_SECONDS || ( $this->may_wait && ! ( $this->may_wait )() ) ) {
			throw new RateLimited( $seconds );
		}

		( $this->sleep )( $seconds );
		$this->wait_until = 0;

		return true;
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
	 * @throws RateLimited If HelpScout's rate limit was reached.
	 * @throws ApiError    If HelpScout gave none.
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

		$status = $response->getStatusCode();
		if ( 429 === $status ) {
			throw new RateLimited( (int) $response->getHeaderLine( 'X-RateLimit-Retry-After' ) );
		}

		// HelpScout being down isn't refused credentials: that's retried, rather than stopping the import.
		if ( 0 === $status || $status >= 500 ) {
			throw new ApiError( 'HelpScout answered ' . $status . ' for an access token.', $status );
		}

		if ( 200 !== $status || '' === $token ) {
			throw new ApiError( 'HelpScout gave no access token; check the app ID and secret.', 401 );
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
