<?php

namespace Wporg\TranslationEvents\Routes\Attendee;

use Wporg\TranslationEvents\Attendee\Attendee;
use Wporg\TranslationEvents\Attendee\Attendee_Repository;
use Wporg\TranslationEvents\Event\Event_Repository_Interface;
use Wporg\TranslationEvents\Routes\Route;
use Wporg\TranslationEvents\Translation_Events;
use Wporg\TranslationEvents\Urls;

/**
 * Remove an attendee from an event.
 */
class Remove_Attendee_Route extends Route {
	private Event_Repository_Interface $event_repository;
	private Attendee_Repository $attendee_repository;

	/**
	 * Remove_Attendee_Route constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->event_repository    = Translation_Events::get_event_repository();
		$this->attendee_repository = Translation_Events::get_attendee_repository();
	}

	/**
	 * Handle the request to remove an attendee from an event.
	 *
	 * @param int $event_id The event slug.
	 * @param int $user_id  The user ID.
	 * @return void
	 */
	public function handle( int $event_id, int $user_id ): void {
		global $wp;
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( home_url( $wp->request ) ) );
			exit;
		}

		$event = $this->event_repository->get_event( $event_id );
		if ( ! $event ) {
			$this->die_with_404();
		}
		if ( ! current_user_can( 'edit_translation_event_attendees', $event->id() ) ) {
			$this->die_with_error( esc_html__( 'You do not have permission to edit this event.', 'gp-translation-events' ), 403 );
		}

		$attendee = $this->attendee_repository->get_attendee_for_event_for_user( $event->id(), $user_id );
		if ( $attendee instanceof Attendee ) {
			if ( ! current_user_can( 'edit_translation_event_attendees', $event->id() ) ) {
				$this->die_with_error( esc_html__( 'You do not have permission to remove this attendee.', 'gp-translation-events' ), 403 );
			}
			$this->attendee_repository->remove_attendee( $event->id(), $user_id );
		}

		wp_safe_redirect( Urls::event_attendees( $event->id() ) );
		exit;
	}
}
