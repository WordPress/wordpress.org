<?php

namespace Wporg\TranslationEvents\Event;

use Exception;
use Throwable;

class InvalidEnd extends Exception {
	public function __construct( ?Throwable $previous = null ) {
		parent::__construct( 'Event end is invalid', 0, $previous );
	}
}
