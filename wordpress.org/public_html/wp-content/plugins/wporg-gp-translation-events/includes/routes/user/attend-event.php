<?php

namespace Wporg\TranslationEvents\Routes\User;

use Wporg\TranslationEvents\Attendee\Attendee;
use Wporg\TranslationEvents\Attendee\Attendee_Repository;
use Wporg\TranslationEvents\Event\Event_Repository_Interface;
use Wporg\TranslationEvents\Routes\Route;
use Wporg\TranslationEvents\Stats\Stats_Importer;
use Wporg\TranslationEvents\Translation_Events;
use Wporg\TranslationEvents\Urls;

/**
 * Toggle whether the current user is attending an event.
 * If the user is not currently marked as attending, they will be marked as attending.
 * If the user is currently marked as attending, they will be marked as not attending.
 *
 * If the user is marked as attending, and the event is active at that moment, stats for the translations the user
 * created since the event started are imported.
 */
class Attend_Event_Route extends Route {
	private Event_Repository_Interface $event_repository;
	private Attendee_Repository $attendee_repository;
	private Stats_Importer $stats_importer;

	public function __construct() {
		parent::__construct();
		$this->event_repository    = Translation_Events::get_event_repository();
		$this->attendee_repository = Translation_Events::get_attendee_repository();
		$this->stats_importer      = new Stats_Importer();
	}

	public function handle( int $event_id ): void {
		$user = wp_get_current_user();
		if ( ! $user ) {
			$this->die_with_error( esc_html__( 'Only logged-in users can attend events', 'gp-translation-events' ), 403 );
		}
		$user_id = $user->ID;

		$event = $this->event_repository->get_event( $event_id );
		if ( ! $event ) {
			$this->die_with_404();
		}

		if ( $event->is_past() ) {
			$this->die_with_error( esc_html__( 'Cannot attend or un-attend a past event', 'gp-translation-events' ), 403 );
		}

		$attendee = $this->attendee_repository->get_attendee( $event->id(), $user_id );
		if ( $attendee instanceof Attendee ) {
			if ( $attendee->is_contributor() ) {
				$this->die_with_error( esc_html__( 'Contributors cannot un-attend the event', 'gp-translation-events' ), 403 );
			}
			$this->attendee_repository->remove_attendee( $event->id(), $user_id );
		} else {
			$attendee = new Attendee( $event->id(), $user_id );
			$this->attendee_repository->insert_attendee( $attendee );

			// If the event is active right now,
			// import stats for translations the user created since the event started.
			if ( $event->is_active() ) {
				$this->stats_importer->import_for_user_and_event( $user_id, $event );
			}
		}

		wp_safe_redirect( Urls::event_details( $event->id() ) );
		exit;
	}
}
