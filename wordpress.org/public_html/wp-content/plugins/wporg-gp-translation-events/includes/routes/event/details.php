<?php

namespace Wporg\TranslationEvents\Routes\Event;

use Exception;
use Wporg\TranslationEvents\Attendee\Attendee;
use Wporg\TranslationEvents\Attendee\Attendee_Repository;
use Wporg\TranslationEvents\Event\Event_Repository_Interface;
use Wporg\TranslationEvents\Project\Project_Repository;
use Wporg\TranslationEvents\Routes\Route;
use Wporg\TranslationEvents\Stats\Stats_Calculator;
use Wporg\TranslationEvents\Translation\Translation_Repository;
use Wporg\TranslationEvents\Translation_Events;

/**
 * Displays the event details page.
 */
class Details_Route extends Route {
	private Event_Repository_Interface $event_repository;
	private Attendee_Repository $attendee_repository;
	private Translation_Repository $translation_repository;
	private Project_Repository $project_repository;
	private Stats_Calculator $stats_calculator;

	public function __construct() {
		parent::__construct();
		$this->event_repository       = Translation_Events::get_event_repository();
		$this->attendee_repository    = Translation_Events::get_attendee_repository();
		$this->translation_repository = new Translation_Repository();
		$this->project_repository     = new Project_Repository();
		$this->stats_calculator       = new Stats_Calculator();
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

		if ( ! current_user_can( 'view_translation_event', $event->id() ) ) {
			$this->die_with_error( esc_html__( 'You are not authorized to view this page.', 'gp-translation-events' ), 403 );
		}

		$event_id          = $event->id();
		$event_title       = $event->title();
		$event_description = $event->description();
		$event_start       = $event->start();
		$event_end         = $event->end();

		$projects              = $this->project_repository->get_for_event( $event->id() );
		$attendees             = $this->attendee_repository->get_attendees( $event->id() );
		$current_user_attendee = $attendees[ $user->ID ] ?? null;
		$user_is_attending     = $current_user_attendee instanceof Attendee;
		$user_is_contributor   = $user_is_attending && $current_user_attendee->is_contributor();

		$hosts = array_filter(
			$attendees,
			function ( Attendee $attendee ) {
				return $attendee->is_host();
			}
		);

		$contributors = array_filter(
			$attendees,
			function ( Attendee $attendee ) {
				return $attendee->is_contributor();
			}
		);

		$attendees_not_contributing = array_filter(
			$attendees,
			function ( Attendee $attendee ) {
				return ! $attendee->is_contributor();
			}
		);

		$contributor_ids = array_map(
			function ( Attendee $contributor ) {
				return $contributor->user_id();
			},
			$contributors
		);

		$new_contributor_ids = array();
		$translations_counts = $this->translation_repository->count_translations_before( $contributor_ids, $event->start() );
		foreach ( $translations_counts as $user_id => $count ) {
			if ( $count <= 10 ) {
				$new_contributor_ids[ $user_id ] = true;
			}
		}

		try {
			$event_stats = $this->stats_calculator->for_event( $event->id() );
		} catch ( Exception $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( $e );
			$this->die_with_error( esc_html__( 'Failed to calculate event stats', 'gp-translation-events' ) );
		}

		$this->tmpl( 'event', get_defined_vars() );
	}
}
