<?php

namespace Wporg\TranslationEvents\Routes\User;

use Wporg\TranslationEvents\Attendee\Attendee;
use Wporg\TranslationEvents\Attendee\Attendee_Adder;
use Wporg\TranslationEvents\Attendee\Attendee_Repository;
use Wporg\TranslationEvents\Event\Event_Repository;
use Wporg\TranslationEvents\Routes\Route;
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
	private Event_Repository $event_repository;
	private Attendee_Repository $attendee_repository;
	private Attendee_Adder $attendee_adder;

	public function __construct() {
		parent::__construct();
		$this->event_repository    = Translation_Events::get_event_repository();
		$this->attendee_repository = Translation_Events::get_attendee_repository();
		$this->attendee_adder      = Translation_Events::get_attendee_adder();
	}

	public function handle( int $event_id ): void {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			$this->die_with_error( esc_html__( 'Only logged-in users can attend events', 'gp-translation-events' ), 403 );
			return; // die_with_*() doesn't die under GP_Route::$fake_request.
		}

		$nonce_name = '_attendee_nonce';
		if ( ! isset( $_POST[ $nonce_name ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $nonce_name ] ) ), $nonce_name ) ) {
			$this->die_with_error( esc_html__( 'Your link has expired or is invalid. Please reload the page and try again.', 'gp-translation-events' ), 403 );
			return; // die_with_*() doesn't die under GP_Route::$fake_request.
		}

		$event = $this->event_repository->get_event( $event_id );
		if ( ! $event ) {
			$this->die_with_404();
			return; // die_with_*() doesn't die under GP_Route::$fake_request.
		}

		if ( $event->is_past() ) {
			$this->die_with_error( esc_html__( 'Cannot attend or un-attend a past event', 'gp-translation-events' ), 403 );
			return; // die_with_*() doesn't die under GP_Route::$fake_request.
		}

		$attendee           = $this->attendee_repository->get_attendee_for_event_for_user( $event->id(), $user_id );
		$is_remote_attendee = isset( $_POST['attend_remotely'] );

		if ( $attendee instanceof Attendee ) {
			if ( $attendee->is_contributor() ) {
				$this->die_with_error( esc_html__( 'Contributors cannot un-attend the event', 'gp-translation-events' ), 403 );
				return; // die_with_*() doesn't die under GP_Route::$fake_request.
			}
			$this->attendee_repository->remove_attendee( $event->id(), $user_id );
		} else {
			$attendee = new Attendee( $event->id(), $user_id, false, false, array(), $is_remote_attendee );
			$this->attendee_adder->add_to_event( $event, $attendee );
		}

		wp_safe_redirect( Urls::event_details( $event->id() ) );
		$this->exit_();
	}
}
