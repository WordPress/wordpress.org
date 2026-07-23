<?php

namespace Wporg\TranslationEvents\Event;

use Exception;
use Throwable;

class InvalidStart extends Exception {
	public function __construct( ?Throwable $previous = null ) {
		parent::__construct( 'Event start is invalid', 0, $previous );
	}
}
