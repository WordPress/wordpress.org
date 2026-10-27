<?php
/**
 * Exception thrown when an event has an invalid start date.
 *
 * @package wporg-gp-translation-events
 */

namespace Wporg\TranslationEvents\Event;

use Exception;
use Throwable;

/**
 * Thrown when an event has an invalid start date.
 */
class Invalid_Start extends Exception {
	/**
	 * Invalid_Start constructor.
	 *
	 * @param Throwable|null $previous Optional previous exception, for chaining.
	 */
	public function __construct( ?Throwable $previous = null ) {
		parent::__construct( 'Event start is invalid', 0, $previous );
	}
}
