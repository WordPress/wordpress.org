<?php

namespace Wporg\TranslationEvents\Attendee;

use Exception;

class Attendee {
	private int $event_id;
	private int $user_id;
	private bool $is_host;

	/**
	 * @throws Exception
	 */
	public function __construct( int $event_id, int $user_id ) {
		if ( $event_id < 1 ) {
			throw new Exception( 'invalid event id' );
		}
		if ( $user_id < 1 ) {
			throw new Exception( 'invalid user id' );
		}

		$this->event_id = $event_id;
		$this->user_id  = $user_id;
		$this->is_host  = false;
	}

	public function event_id(): int {
		return $this->event_id;
	}

	public function user_id(): int {
		return $this->user_id;
	}

	public function is_host(): bool {
		return $this->is_host;
	}

	public function mark_as_host(): void {
		$this->is_host = true;
	}

	public function mark_as_non_host(): void {
		$this->is_host = false;
	}
}
