<?php

namespace Wporg\TranslationEvents\Routes\User;

use Wporg\TranslationEvents\Attendee\Attendee_Repository;
use Wporg\TranslationEvents\Event\Event_Repository_Interface;
use Wporg\TranslationEvents\Routes\Route;
use Wporg\TranslationEvents\Translation_Events;

/**
 * Displays the My Events page for a user.
 */
class My_Events_Route extends Route {
	private Event_Repository_Interface $event_repository;
	private Attendee_Repository $attendee_repository;

	public function __construct() {
		parent::__construct();
		$this->event_repository    = Translation_Events::get_event_repository();
		$this->attendee_repository = Translation_Events::get_attendee_repository();
	}

	public function handle(): void {
		global $wp;
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( home_url( $wp->request ) ) );
			exit;
		}

		$user_id = get_current_user_id();

		$page = 1;
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['page'] ) ) {
			$value = sanitize_text_field( wp_unslash( $_GET['page'] ) );
			if ( is_numeric( $value ) ) {
				$page = (int) $value;
			}
		}
		// phpcs:enable

		$events = $this->event_repository->get_events_for_user( get_current_user_id(), $page, 10 );

		$event_ids = array_map(
			function ( $event ) {
				return $event->id();
			},
			$events->events,
		);

		$current_user_attendee_per_event = $this->attendee_repository->get_attendees_for_events_for_user( $event_ids, $user_id );

		$this->tmpl(
			'events-my-events',
			compact(
				'events',
				'current_user_attendee_per_event'
			),
		);
	}
}
