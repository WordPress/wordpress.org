<?php

namespace Wporg\TranslationEvents\Routes\Event;

use Exception;
use GP;
use Wporg\TranslationEvents\Attendee\Attendee;
use Wporg\TranslationEvents\Attendee\Attendee_Repository;
use Wporg\TranslationEvents\Event\Event_Repository_Interface;
use Wporg\TranslationEvents\Routes\Route;
use Wporg\TranslationEvents\Stats_Calculator;
use Wporg\TranslationEvents\Translation_Events;

/**
 * Displays the event details page.
 */
class Details_Route extends Route {
	private Event_Repository_Interface $event_repository;
	private Attendee_Repository $attendee_repository;

	public function __construct() {
		parent::__construct();
		$this->event_repository    = Translation_Events::get_event_repository();
		$this->attendee_repository = Translation_Events::get_attendee_repository();
	}

	public function handle( string $event_slug ): void {
		$user  = wp_get_current_user();
		$event = get_page_by_path( $event_slug, OBJECT, Translation_Events::CPT );
		if ( ! $event ) {
			$this->die_with_404();
		}
		$event = $this->event_repository->get_event( $event->ID );
		if ( ! $event ) {
			$this->die_with_404();
		}

		/**
		 * Filter the ability to create, edit, or delete an event.
		 *
		 * @param bool $can_crud_event Whether the user can create, edit, or delete an event.
		 */
		$can_crud_event = apply_filters( 'gp_translation_events_can_crud_event', GP::$permission->current_user_can( 'admin' ) );
		if ( 'publish' !== $event->status() && ! $can_crud_event ) {
			$this->die_with_error( esc_html__( 'You are not authorized to view this page.', 'gp-translation-events' ), 403 );
		}

		$event_id          = $event->id();
		$event_title       = $event->title();
		$event_description = $event->description();
		$event_start       = $event->start();
		$event_end         = $event->end();

		$attendee          = $this->attendee_repository->get_attendee( $event->id(), $user->ID );
		$user_is_attending = $attendee instanceof Attendee;

		$stats_calculator = new Stats_Calculator();
		try {
			$event_stats   = $stats_calculator->for_event( $event->id() );
			$contributors  = $stats_calculator->get_contributors( $event->id() );
			$attendees     = $stats_calculator->get_attendees_not_contributing( $event->id() );
			$attendee_repo = $this->attendee_repository;
			$hosts         = $this->attendee_repository->get_hosts( $event->id() );
			$projects      = $stats_calculator->get_projects( $event->id() );
		} catch ( Exception $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( $e );
			$this->die_with_error( esc_html__( 'Failed to calculate event stats', 'gp-translation-events' ) );
		}

		$is_editable_event = true;
		if ( $event_end->is_in_the_past() || $stats_calculator->event_has_stats( $event->id() ) ) {
			$is_editable_event = false;
		}

		$this->tmpl( 'event', get_defined_vars() );
	}
}
