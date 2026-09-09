<?php
/**
 * Exception thrown when an event has an invalid attendance mode.
 *
 * @package wporg-gp-translation-events
 */

namespace Wporg\TranslationEvents\Event;

use Exception;
use Throwable;

/**
 * Thrown when an event has an invalid attendance mode.
 */
class Invalid_Attendance_Mode extends Exception {
	/**
	 * Invalid_Attendance_Mode constructor.
	 *
	 * @param Throwable|null $previous Optional previous exception, for chaining.
	 */
	public function __construct( ?Throwable $previous = null ) {
		parent::__construct( 'Event attendance mode is invalid', 0, $previous );
	}
}
