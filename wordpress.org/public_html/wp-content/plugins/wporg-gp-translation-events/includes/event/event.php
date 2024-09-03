<?php

namespace Wporg\TranslationEvents\Event;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Throwable;
use Wporg\TranslationEvents\Translation_Events;

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
	private DateTimeImmutable $updated_at;
	private string $attendance_mode;

	/**
	 * @throws InvalidStart
	 * @throws InvalidEnd
	 * @throws InvalidStatus
	 */
	public function __construct(
		int $author_id,
		Event_Start_Date $start,
		Event_End_Date $end,
		DateTimeZone $timezone,
		string $status,
		string $title,
		string $description,
		DateTimeImmutable $updated_at = null,
		string $attendance_mode = 'onsite'
	) {
		$this->author_id = $author_id;
		$this->validate_times( $start, $end );
		$this->set_start( $start );
		$this->set_end( $end );
		$this->set_timezone( $timezone );
		$this->set_status( $status );
		$this->set_title( $title );
		$this->set_description( $description );
		$this->set_updated_at( $updated_at );
		$this->set_attendance_mode( $attendance_mode );
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

	public function is_published(): bool {
		return 'publish' === $this->status;
	}

	public function is_draft(): bool {
		return 'draft' === $this->status;
	}

	public function is_trashed(): bool {
		return 'trash' === $this->status;
	}

	public function is_active(): bool {
		$now = Translation_Events::now();
		return $now >= $this->start->utc() && $now < $this->end->utc();
	}

	public function is_past(): bool {
		return $this->end->is_in_the_past();
	}

	public function is_remote(): bool {
		return 'remote' === $this->attendance_mode;
	}

	public function is_hybrid(): bool {
		return 'hybrid' === $this->attendance_mode;
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

	public function updated_at(): DateTimeImmutable {
		return $this->updated_at;
	}

	public function set_id( int $id ): void {
		$this->id = $id;
	}

	public function set_slug( string $slug ): void {
		$this->slug = $slug;
	}

	public function set_start( Event_Start_Date $start ): void {
		$this->start = $start;
	}

	public function set_end( Event_End_Date $end ): void {
		$this->end = $end;
	}

	public function set_timezone( DateTimeZone $timezone ): void {
		$this->timezone = $timezone;
	}

	public function attendance_mode(): string {
		return $this->attendance_mode;
	}

	/**
	 * @throws InvalidStatus
	 */
	public function set_status( string $status ): void {
		if ( ! in_array( $status, array( 'draft', 'publish', 'trash' ), true ) ) {
			throw new InvalidStatus();
		}
		$this->status = $status;
	}

	public function set_title( string $title ): void {
		$this->title = $title;
	}

	public function set_description( string $description ): void {
		$this->description = $description;
	}

	public function set_updated_at( DateTimeImmutable $updated_at = null ): void {
		$this->updated_at = $updated_at ?? Translation_Events::now();
	}

	public function set_attendance_mode( string $attendance_mode ): void {
		$this->attendance_mode = $attendance_mode;
	}

	/**
	 * @throws InvalidStart
	 * @throws InvalidEnd
	 */
	public function validate_times( Event_Start_Date $start, Event_End_Date $end ) {
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
