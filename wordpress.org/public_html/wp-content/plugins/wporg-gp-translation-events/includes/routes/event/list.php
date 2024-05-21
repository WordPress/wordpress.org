<?php

namespace Wporg\TranslationEvents\Routes\Event;

use DateTime;
use DateTimeZone;
use Exception;
use WP_Query;
use Wporg\TranslationEvents\Event\Event_Repository_Interface;
use Wporg\TranslationEvents\Routes\Route;
use Wporg\TranslationEvents\Translation_Events;

/**
 * Displays the event list page.
 */
class List_Route extends Route {
	private Event_Repository_Interface $event_repository;

	public function __construct() {
		parent::__construct();
		$this->event_repository = Translation_Events::get_event_repository();
	}

	public function handle(): void {
		$current_datetime_utc = null;
		try {
			$current_datetime_utc = ( new DateTime( 'now', new DateTimeZone( 'UTC' ) ) )->format( 'Y-m-d H:i:s' );
		} catch ( Exception $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( $e );
			$this->die_with_error( esc_html__( 'Something is wrong.', 'gp-translation-events' ) );
		}

		$_current_events_paged        = 1;
		$_upcoming_events_paged       = 1;
		$_past_events_paged           = 1;
		$_user_attending_events_paged = 1;

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['current_events_paged'] ) ) {
			$value = sanitize_text_field( wp_unslash( $_GET['current_events_paged'] ) );
			if ( is_numeric( $value ) ) {
				$_current_events_paged = (int) $value;
			}
		}
		if ( isset( $_GET['upcoming_events_paged'] ) ) {
			$value = sanitize_text_field( wp_unslash( $_GET['upcoming_events_paged'] ) );
			if ( is_numeric( $value ) ) {
				$_upcoming_events_paged = (int) $value;
			}
		}
		if ( isset( $_GET['past_events_paged'] ) ) {
			$value = sanitize_text_field( wp_unslash( $_GET['past_events_paged'] ) );
			if ( is_numeric( $value ) ) {
				$_past_events_paged = (int) $value;
			}
		}
		if ( isset( $_GET['user_attending_events_paged'] ) ) {
			$value = sanitize_text_field( wp_unslash( $_GET['user_attending_events_paged'] ) );
			if ( is_numeric( $value ) ) {
				$_user_attending_events_paged = (int) $value;
			}
		}
		// phpcs:enable

		$current_events_query        = $this->event_repository->get_current_events( $_current_events_paged, 10 );
		$upcoming_events_query       = $this->event_repository->get_upcoming_events( $_upcoming_events_paged, 10 );
		$past_events_query           = $this->event_repository->get_past_events( $_past_events_paged, 10 );
		$user_attending_events_query = $this->event_repository->get_current_and_upcoming_events_for_user( get_current_user_id(), $_user_attending_events_paged, 10 );

		$this->tmpl(
			'events-list',
			array(
				'current_events_query'        => $current_events_query,
				'upcoming_events_query'       => $upcoming_events_query,
				'past_events_query'           => $past_events_query,
				'user_attending_events_query' => $user_attending_events_query,
			),
		);
	}
}
