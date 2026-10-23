<?php
/**
 * The other FreeScout's API refused or failed a request.
 *
 * @package WordPressdotorg\FreeScout\WPOrgFreeScoutImport
 */

declare( strict_types = 1 );

namespace Modules\WPOrgFreeScoutImport\Exceptions;

use RuntimeException;

/**
 * Carries the HTTP status, so callers can tell a missing resource from a rejected key.
 */
final class ApiError extends RuntimeException {

	/**
	 * HTTP status, or 0 if it couldn't be reached; a 2xx status means what it sent couldn't be read.
	 *
	 * @var int
	 */
	public $status;

	/**
	 * Constructor.
	 *
	 * @param string          $message  Message.
	 * @param int             $status   HTTP status, or 0.
	 * @param \Throwable|null $previous Cause.
	 */
	public function __construct( string $message, int $status = 0, ?\Throwable $previous = null ) {
		parent::__construct( $message, $status, $previous );

		$this->status = $status;
	}

	/**
	 * Whether the API key was refused, which retrying won't fix.
	 *
	 * @return bool
	 */
	public function is_denied(): bool {
		return in_array( $this->status, array( 401, 403 ), true );
	}
}
