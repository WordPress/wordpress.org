<?php

namespace Wporg\TranslationEvents\Routes\User;

use Wporg\TranslationEvents\Attendee\Attendee;
use Wporg\TranslationEvents\Attendee\Attendee_Repository;
use Wporg\TranslationEvents\Event\Event_Repository_Interface;
use Wporg\TranslationEvents\Routes\Route;
use Wporg\TranslationEvents\Translation_Events;
use Wporg\TranslationEvents\Urls;

/**
 * Toggle whether the current user is attending an event onsite or remotely.
 * If the user is not currently marked as remote attendee, they will be marked as remote attendee.
 * If the user is currently marked as as remote attendee, they will be marked as not remote attendee.
 */
class Attendance_Mode_Route extends Route {
	private Event_Repository_Interface $event_repository;
	private Attendee_Repository $attendee_repository;

	/**
	 * Attendance_Mode_Route constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->event_repository    = Translation_Events::get_event_repository();
		$this->attendee_repository = Translation_Events::get_attendee_repository();
	}

	/**
	 * Handle the request to toggle whether the current user is attending an event onsite or remotely.
	 *
	 * @param int $event_id The event ID.
	 * @param int $user_id  The user ID.
	 * @return void
	 */
	public function handle( int $event_id, int $user_id ): void {

		$current_user = wp_get_current_user();
		if ( ! $current_user->exists() ) {
			$this->die_with_error( esc_html__( 'Only logged-in users can manage the attendance mode of an attendee', 'gp-translation-events' ), 403 );
			return; // die_with_*() doesn't die under GP_Route::$fake_request.
		}

		$nonce_action = "toggle_translation_event_attendance_mode_{$event_id}_{$user_id}";
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), $nonce_action ) ) {
			$this->die_with_error( esc_html__( 'Your link has expired or is invalid. Please go back and try again.', 'gp-translation-events' ), 403 );
			return; // die_with_*() doesn't die under GP_Route::$fake_request.
		}

		if ( ! current_user_can( 'edit_translation_event', $event_id ) ) {
			$this->die_with_error( esc_html__( 'You do not have permissions to manage the attendance mode of an attendee', 'gp-translation-events' ), 403 );
			return; // die_with_*() doesn't die under GP_Route::$fake_request.
		}
		$event = $this->event_repository->get_event( $event_id );
		if ( ! $event ) {
			$this->die_with_404();
			return; // die_with_*() doesn't die under GP_Route::$fake_request.
		}

		$affected_attendee = $this->attendee_repository->get_attendee_for_event_for_user( $event_id, $user_id );
		if ( $affected_attendee instanceof Attendee ) {
			if ( $affected_attendee->is_remote() ) {
				$affected_attendee->mark_as_in_person_attendee();
			} else {
				$affected_attendee->mark_as_remote_attendee();
			}
			$this->attendee_repository->update_attendee( $affected_attendee );
		}
		wp_safe_redirect( Urls::event_attendees( $event->id() ) );
		$this->exit_();
	}
}
