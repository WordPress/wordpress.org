<?php

namespace Wporg\TranslationEvents\Routes\User;

use Wporg\TranslationEvents\Attendee\Attendee;
use Wporg\TranslationEvents\Attendee\Attendee_Repository;
use Wporg\TranslationEvents\Event\Event_Repository_Interface;
use Wporg\TranslationEvents\Routes\Route;
use Wporg\TranslationEvents\Translation_Events;

/**
 * Toggle whether the current user is attending an event.
 * If the user is not currently marked as attending, they will be marked as attending.
 * If the user is currently marked as attending, they will be marked as not attending.
 */
class Attend_Event_Route extends Route {
	private Event_Repository_Interface $event_repository;
	private Attendee_Repository $attendee_repository;

	public function __construct() {
		parent::__construct();
		$this->event_repository    = Translation_Events::get_event_repository();
		$this->attendee_repository = Translation_Events::get_attendee_repository();
	}

	public function handle( int $event_id ): void {
		$user = wp_get_current_user();
		if ( ! $user ) {
			$this->die_with_error( esc_html__( 'Only logged-in users can attend events', 'gp-translation-events' ), 403 );
		}

		$event = $this->event_repository->get_event( $event_id );
		if ( ! $event ) {
			$this->die_with_404();
		}

		$attendee = $this->attendee_repository->get_attendee( $event->id(), $user->ID );
		if ( $attendee instanceof Attendee && $attendee->is_host() && ( 1 === count( $this->attendee_repository->get_hosts( $event_id ) ) ) ) {
			$this->die_with_error( esc_html__( 'The event needs a host. Add a new host before stopping to attend the event.', 'gp-translation-events' ), 403 );
		}
		if ( $attendee instanceof Attendee ) {
			$this->attendee_repository->remove_attendee( $event->id(), $user->ID );
		} else {
			$attendee = new Attendee( $event->id(), $user->ID );
			$this->attendee_repository->insert_attendee( $attendee );
		}

		wp_safe_redirect( gp_url( "/events/{$event->slug()}" ) );
		exit;
	}
}
