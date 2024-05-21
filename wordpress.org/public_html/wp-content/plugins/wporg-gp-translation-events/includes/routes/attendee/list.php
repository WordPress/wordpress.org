<?php

namespace Wporg\TranslationEvents\Routes\Attendee;

use Wporg\TranslationEvents\Attendee\Attendee_Repository;
use Wporg\TranslationEvents\Routes\Route;
use Wporg\TranslationEvents\Translation_Events;
use Wporg\TranslationEvents\Event\Event_Repository_Interface;


/**
 * Displays the attendees list page.
 */
class List_Route extends Route {
	private Attendee_Repository $attendee_repository;
	private Event_Repository_Interface $event_repository;



	public function __construct() {
		parent::__construct();
		$this->attendee_repository = new Attendee_Repository();
		$this->event_repository    = Translation_Events::get_event_repository();
	}

	public function handle( string $event_slug ): void {
		global $wp;
		$user             = wp_get_current_user();
		$is_active_filter = false;
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( home_url( $wp->request ) ) );
			exit;
		}

		$event = get_page_by_path( $event_slug, OBJECT, Translation_Events::CPT );
		if ( ! $event ) {
			$this->die_with_404();
		}
		$event = $this->event_repository->get_event( $event->ID );
		if ( ! $event ) {
			$this->die_with_404();
		}
		if ( ! current_user_can( 'edit_translation_event_attendees', $event->id() ) ) {
			$this->die_with_error( esc_html__( 'You do not have permission to edit this event\'s attendees.', 'gp-translation-events' ), 403 );
		}
		if ( gp_get( 'filter' ) && 'hosts' === gp_get( 'filter' ) ) {
			$is_active_filter = true;
			$attendees        = $this->attendee_repository->get_hosts( $event->id() );
		} else {
			$attendees = $this->attendee_repository->get_attendees( $event->id() );
		}

		$this->tmpl(
			'events-attendees',
			array(
				'event'            => $event,
				'attendees'        => $attendees,
				'is_active_filter' => $is_active_filter,
			),
		);
	}
}
