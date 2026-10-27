<?php
/**
 * Exception thrown when an event has an invalid status.
 *
 * @package wporg-gp-translation-events
 */

namespace Wporg\TranslationEvents\Event;

use Exception;
use Throwable;

/**
 * Thrown when an event has an invalid status.
 */
class Invalid_Status extends Exception {
	/**
	 * Invalid_Status constructor.
	 *
	 * @param Throwable|null $previous Optional previous exception, for chaining.
	 */
	public function __construct( ?Throwable $previous = null ) {
		parent::__construct( 'Event status is invalid', 0, $previous );
	}
}
