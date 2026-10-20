<?php
/**
 * A stand-in for the GitHub API, served through the `https` stream wrapper.
 *
 * @package trac-pr
 */

declare( strict_types = 1 );

namespace WordPressdotorg\API\Trac\GithubPRs;

/**
 * Answers each GitHub API request with the body registered for its URL.
 */
class Fake_GitHub_Stream {

	/**
	 * Response bodies, keyed by a pattern matching the request URL.
	 *
	 * @var array<string, string>
	 */
	public static array $responses = array();

	/**
	 * The stream context, set by PHP.
	 *
	 * @var resource|null
	 */
	public mixed $context = null;

	/**
	 * The body of the open request.
	 *
	 * @var string
	 */
	private string $body = '';

	/**
	 * How much of the body has been read.
	 *
	 * @var int
	 */
	private int $position = 0;

	/**
	 * Routes every `https` request to this class.
	 *
	 * @return void
	 */
	public static function register(): void {
		stream_wrapper_unregister( 'https' );
		stream_wrapper_register( 'https', self::class );
	}

	/**
	 * Gives `https` back to PHP and forgets the responses.
	 *
	 * @return void
	 */
	public static function unregister(): void {
		stream_wrapper_restore( 'https' );
		self::$responses = array();
	}

	/**
	 * Opens a request, failing for a URL no response was registered for.
	 *
	 * @param string $path The requested URL.
	 * @return bool Whether a response was found.
	 */
	public function stream_open( string $path ): bool {
		foreach ( self::$responses as $pattern => $body ) {
			if ( preg_match( $pattern, $path ) ) {
				$this->body = $body;

				return true;
			}
		}

		return false;
	}

	/**
	 * Reads the next part of the body.
	 *
	 * @param int $count How many bytes to read.
	 * @return string The bytes read.
	 */
	public function stream_read( int $count ): string {
		$chunk           = substr( $this->body, $this->position, $count );
		$this->position += strlen( $chunk );

		return $chunk;
	}

	/**
	 * Whether the whole body has been read.
	 *
	 * @return bool
	 */
	public function stream_eof(): bool {
		return $this->position >= strlen( $this->body );
	}

	/**
	 * Describes the stream, which has nothing to report.
	 *
	 * @return array
	 */
	public function stream_stat(): array {
		return array();
	}
}
