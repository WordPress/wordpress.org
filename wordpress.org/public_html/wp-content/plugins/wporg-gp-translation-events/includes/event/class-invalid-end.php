<?php
/**
 * Exception thrown when an event has an invalid end date.
 *
 * @package wporg-gp-translation-events
 */

namespace Wporg\TranslationEvents\Event;

use Exception;
use Throwable;

/**
 * Thrown when an event has an invalid end date.
 */
class Invalid_End extends Exception {
	/**
	 * Invalid_End constructor.
	 *
	 * @param Throwable|null $previous Optional previous exception, for chaining.
	 */
	public function __construct( ?Throwable $previous = null ) {
		parent::__construct( 'Event end is invalid', 0, $previous );
	}
}
