<?php
/**
 * Factory for creating translation events in tests.
 *
 * @package wporg-gp-translation-events
 */

namespace Wporg\TranslationEvents\Tests;

use DateTimeImmutable;
use DateTimeZone;
use WP_UnitTest_Factory_For_Post;
use WP_UnitTest_Generator_Sequence;
use Wporg\TranslationEvents\Attendee\Attendee;
use Wporg\TranslationEvents\Attendee\Attendee_Repository;
use Wporg\TranslationEvents\Translation_Events;

/**
 * Creates translation event posts with sensible defaults for tests.
 */
class Event_Factory extends WP_UnitTest_Factory_For_Post {
	/**
	 * Sets up the default generation definitions for event posts.
	 *
	 * @param WP_UnitTest_Factory|null $factory The parent factory, if any.
	 */
	public function __construct( $factory = null ) {
		parent::__construct( $factory );
		$this->default_generation_definitions = array(
			'post_status'  => 'publish',
			'post_title'   => new WP_UnitTest_Generator_Sequence( 'Event title %s' ),
			'post_content' => new WP_UnitTest_Generator_Sequence( 'Event content %s' ),
			'post_excerpt' => new WP_UnitTest_Generator_Sequence( 'Event excerpt %s' ),
			'post_type'    => Translation_Events::CPT,
		);
	}

	/**
	 * Creates an active event and switches it to draft status.
	 *
	 * @param DateTimeImmutable $now The reference time used to place the event.
	 *
	 * @return int The created event's post ID.
	 */
	public function create_draft( DateTimeImmutable $now ): int {
		$timezone = new DateTimeZone( 'Europe/Lisbon' );

		$event_id = $this->create_event(
			$now->modify( '-1 hours' ),
			$now->modify( '+1 hours' ),
			$timezone,
			array(),
		);

		$event              = get_post( $event_id );
		$event->post_status = 'draft';
		wp_update_post( $event );

		return $event_id;
	}

	/**
	 * Creates an event that is currently active, starting at the given time.
	 *
	 * @param DateTimeImmutable $now          The event's start time.
	 * @param int[]             $attendee_ids IDs of users to attend the event.
	 *
	 * @return int The created event's post ID.
	 */
	public function create_active( DateTimeImmutable $now, array $attendee_ids = array() ): int {
		$timezone = new DateTimeZone( 'Europe/Lisbon' );

		return $this->create_event(
			$now,
			$now->modify( '+1 hour' ),
			$timezone,
			$attendee_ids,
		);
	}

	/**
	 * Creates an event that has already ended in the past.
	 *
	 * @param DateTimeImmutable $now          The reference time used to place the event.
	 * @param int[]             $attendee_ids IDs of users to attend the event.
	 *
	 * @return int The created event's post ID.
	 */
	public function create_inactive_past( DateTimeImmutable $now, array $attendee_ids = array() ): int {
		$timezone = new DateTimeZone( 'Europe/Lisbon' );

		return $this->create_event(
			$now->modify( '-2 month' ),
			$now->modify( '-1 month' ),
			$timezone,
			$attendee_ids,
		);
	}

	/**
	 * Creates an event that starts in the future.
	 *
	 * @param DateTimeImmutable $now          The reference time used to place the event.
	 * @param int[]             $attendee_ids IDs of users to attend the event.
	 *
	 * @return int The created event's post ID.
	 */
	public function create_inactive_future( DateTimeImmutable $now, array $attendee_ids = array() ): int {
		$timezone = new DateTimeZone( 'Europe/Lisbon' );

		return $this->create_event(
			$now->modify( '+1 month' ),
			$now->modify( '+2 month' ),
			$timezone,
			$attendee_ids,
		);
	}

	/**
	 * Creates an event with the given start, end, timezone, and attendees.
	 *
	 * @param DateTimeImmutable $start        The event's start time.
	 * @param DateTimeImmutable $end          The event's end time.
	 * @param DateTimeZone      $timezone     The event's timezone.
	 * @param int[]             $attendee_ids IDs of users to attend the event.
	 *
	 * @return int The created event's post ID.
	 */
	public function create_event( DateTimeImmutable $start, DateTimeImmutable $end, DateTimeZone $timezone, array $attendee_ids ): int {
		$attendee_repository = new Attendee_Repository();
		$event_id            = $this->create();

		$user_id = get_current_user_id();
		if ( ! in_array( $user_id, $attendee_ids, true ) ) {
			// The current user will have been added as attending the event, but it was not specified as an attendee by
			// the caller of this function. So we remove the current user as attendee.
			$attendee_repository->remove_attendee( $event_id, $user_id );
		}

		update_post_meta( $event_id, '_event_start', $start->format( 'Y-m-d H:i:s' ) );
		update_post_meta( $event_id, '_event_end', $end->format( 'Y-m-d H:i:s' ) );
		update_post_meta( $event_id, '_event_timezone', $timezone->getName() );

		foreach ( $attendee_ids as $attendee_id ) {
			$attendee_repository->insert_attendee( new Attendee( $event_id, $attendee_id ) );
		}

		return $event_id;
	}
}
