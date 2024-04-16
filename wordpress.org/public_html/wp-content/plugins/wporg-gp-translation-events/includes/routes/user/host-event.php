<?php

namespace Wporg\TranslationEvents\Routes\User;

use Wporg\TranslationEvents\Attendee\Attendee;
use Wporg\TranslationEvents\Attendee\Attendee_Repository;
use Wporg\TranslationEvents\Event\Event_Repository_Interface;
use Wporg\TranslationEvents\Routes\Route;
use Wporg\TranslationEvents\Translation_Events;

/**
 * Toggle whether the current user is hosting an event.
 * If the user is not currently marked as host, they will be marked as host.
 * If the user is currently marked as host, they will be marked as not host.
 */
class Host_Event_Route extends Route {
	private Event_Repository_Interface $event_repository;
	private Attendee_Repository $attendee_repository;

	/**
	 * Host_Event_Route constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->event_repository    = Translation_Events::get_event_repository();
		$this->attendee_repository = Translation_Events::get_attendee_repository();
	}

	/**
	 * Handle the request to toggle whether the current user is hosting an event.
	 *
	 * @param int $event_id The event ID.
	 * @param int $user_id  The user ID.
	 * @return void
	 */
	public function handle( int $event_id, int $user_id ): void {
		$current_user = wp_get_current_user();
		if ( ! $current_user->exists() ) {
			$this->die_with_error( esc_html__( "Only logged-in users can manage event's hosts.", 'gp-translation-events' ), 403 );
		}

		$current_user_attendee = $this->attendee_repository->get_attendee( $event_id, $current_user->ID );
		if ( ! current_user_can( 'manage_options' ) && ! $current_user_attendee->is_host() ) {
			$this->die_with_error( esc_html__( "This user does not have permissions to manage event's hosts.", 'gp-translation-events' ), 403 );
		}

		$event = $this->event_repository->get_event( $event_id );
		if ( ! $event ) {
			$this->die_with_404();
		}

		$affected_attendee = $this->attendee_repository->get_attendee( $event_id, $user_id );
		// The user is attending to the event, so if I don't find the attendee, I won't create it.
		if ( $affected_attendee instanceof Attendee ) {
			if ( $affected_attendee->is_host() ) {
				$affected_attendee->mark_as_non_host();
			} else {
				$affected_attendee->mark_as_host();
			}

			$this->attendee_repository->update_attendee( $affected_attendee );
		}

		wp_safe_redirect( gp_url( "/events/{$event->slug()}" ) );
		exit;
	}
}
