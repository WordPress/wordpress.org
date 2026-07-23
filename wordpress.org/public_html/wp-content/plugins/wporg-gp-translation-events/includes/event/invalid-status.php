<?php

namespace Wporg\TranslationEvents\Event;

use Exception;
use Throwable;

class InvalidStatus extends Exception {
	public function __construct( ?Throwable $previous = null ) {
		parent::__construct( 'Event status is invalid', 0, $previous );
	}
}
