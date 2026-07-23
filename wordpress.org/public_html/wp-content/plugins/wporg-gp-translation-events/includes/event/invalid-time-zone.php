<?php

namespace Wporg\TranslationEvents\Event;

use Exception;
use Throwable;

class InvalidTimeZone extends Exception {
	public function __construct( ?Throwable $previous = null ) {
		parent::__construct( 'Event time zone is invalid', 0, $previous );
	}
}
