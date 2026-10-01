<?php
/**
 * HelpScout's API refused or failed a request.
 *
 * @package WordPressdotorg\FreeScout\WPOrgHelpScoutImport
 */

declare( strict_types = 1 );

namespace Modules\WPOrgHelpScoutImport\Exceptions;

use RuntimeException;

/**
 * Carries the HTTP status, so callers can tell a missing resource from rejected credentials.
 */
class ApiError extends RuntimeException {

	/**
	 * HTTP status, or 0 if HelpScout couldn't be reached.
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
	 * Whether HelpScout rejected the app's credentials or their permissions, which retrying won't fix.
	 *
	 * @return bool
	 */
	public function is_denied(): bool {
		return in_array( $this->status, array( 401, 403 ), true );
	}
}
