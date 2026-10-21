<?php
/**
 * The import has to wait for HelpScout's rate limit.
 *
 * @package WordPressdotorg\FreeScout\WPOrgHelpScoutImport
 */

declare( strict_types = 1 );

namespace Modules\WPOrgHelpScoutImport\Exceptions;

/**
 * Says how long to wait before asking again.
 */
final class RateLimited extends ApiError {

	/**
	 * Seconds to wait.
	 *
	 * @var int
	 */
	public $retry_after;

	/**
	 * Constructor.
	 *
	 * @param int $retry_after Seconds to wait.
	 */
	public function __construct( int $retry_after ) {
		parent::__construct( 'HelpScout rate limit: waiting ' . $retry_after . ' seconds.', 429 );

		$this->retry_after = max( 1, $retry_after );
	}
}
