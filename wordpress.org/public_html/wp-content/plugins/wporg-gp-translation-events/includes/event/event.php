<?php

namespace Wporg\TranslationEvents\Event;

use DateTimeZone;
use Exception;
use Throwable;

class InvalidTimeZone extends Exception {
	public function __construct( Throwable $previous = null ) {
		parent::__construct( 'Event time zone is invalid', 0, $previous );
	}
}

class InvalidStart extends Exception {
	public function __construct( Throwable $previous = null ) {
		parent::__construct( 'Event start is invalid', 0, $previous );
	}
}

class InvalidEnd extends Exception {
	public function __construct( Throwable $previous = null ) {
		parent::__construct( 'Event end is invalid', 0, $previous );
	}
}

class InvalidTitle extends Exception {
	public function __construct( Throwable $previous = null ) {
		parent::__construct( 'Event title is invalid', 0, $previous );
	}
}

class InvalidStatus extends Exception {
	public function __construct( Throwable $previous = null ) {
		parent::__construct( 'Event status is invalid', 0, $previous );
	}
}

class Event {
	private int $id = 0;
	private int $author_id;
	private Event_Start_Date $start;
	private Event_End_Date $end;
	private DateTimeZone $timezone;
	private string $slug = '';
	private string $status;
	private string $title;
	private string $description;

	/**
	 * @throws InvalidStart
	 * @throws InvalidEnd
	 * @throws InvalidStatus
	 * @throws InvalidTitle
	 */
	public function __construct(
		int $author_id,
		Event_Start_Date $start,
		Event_End_Date $end,
		DateTimeZone $timezone,
		string $status,
		string $title,
		string $description
	) {
		$this->author_id = $author_id;
		$this->set_times( $start, $end );
		$this->set_timezone( $timezone );
		$this->set_status( $status );
		$this->set_title( $title );
		$this->set_description( $description );
	}

	public function id(): int {
		return $this->id;
	}

	public function author_id(): int {
		return $this->author_id;
	}

	public function start(): Event_Start_Date {
		return $this->start;
	}

	public function end(): Event_End_Date {
		return $this->end;
	}

	public function timezone(): DateTimeZone {
		return $this->timezone;
	}

	public function slug(): string {
		return $this->slug;
	}

	public function status(): string {
		return $this->status;
	}

	public function title(): string {
		return $this->title;
	}

	public function description(): string {
		return $this->description;
	}

	public function set_id( int $id ): void {
		$this->id = $id;
	}

	public function set_slug( string $slug ): void {
		$this->slug = $slug;
	}

	/**
	 * @throws InvalidStart|InvalidEnd
	 */
	public function set_times( Event_Start_Date $start, Event_End_Date $end ): void {
		$this->validate_times( $start, $end );
		$this->start = $start;
		$this->end   = $end;
	}

	public function set_timezone( DateTimeZone $timezone ): void {
		$this->timezone = $timezone;
	}

	/**
	 * @throws InvalidStatus
	 */
	public function set_status( string $status ): void {
		if ( ! in_array( $status, array( 'draft', 'publish' ), true ) ) {
			throw new InvalidStatus();
		}
		$this->status = $status;
	}

	/**
	 * @throws InvalidTitle
	 */
	public function set_title( string $title ): void {
		if ( ! $title ) {
			throw new InvalidTitle();
		}
		$this->title = $title;
	}

	public function set_description( string $description ): void {
		$this->description = $description;
	}

	/**
	 * @throws InvalidStart
	 * @throws InvalidEnd
	 */
	private function validate_times( Event_Start_Date $start, Event_End_Date $end ) {
		if ( $end <= $start ) {
			throw new InvalidEnd();
		}
		if ( ! $start->getTimezone() || 'UTC' !== $start->getTimezone()->getName() ) {
			throw new InvalidStart();
		}
		if ( ! $end->getTimezone() || 'UTC' !== $end->getTimezone()->getName() ) {
			throw new InvalidEnd();
		}
	}
}
