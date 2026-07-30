<?php
/**
 * Exception thrown when an event has an invalid time zone.
 *
 * @package wporg-gp-translation-events
 */

namespace Wporg\TranslationEvents\Event;

use Exception;
use Throwable;

/**
 * Thrown when an event has an invalid time zone.
 */
class Invalid_Time_Zone extends Exception {
	/**
	 * Invalid_Time_Zone constructor.
	 *
	 * @param Throwable|null $previous Optional previous exception, for chaining.
	 */
	public function __construct( ?Throwable $previous = null ) {
		parent::__construct( 'Event time zone is invalid', 0, $previous );
	}
}
