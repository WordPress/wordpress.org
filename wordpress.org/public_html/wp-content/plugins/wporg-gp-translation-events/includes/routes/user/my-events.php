<?php

namespace Wporg\TranslationEvents\Routes\User;

use Wporg\TranslationEvents\Event\Event_Repository_Interface;
use Wporg\TranslationEvents\Routes\Route;
use Wporg\TranslationEvents\Translation_Events;

/**
 * Displays the My Events page for a user.
 */
class My_Events_Route extends Route {
	private Event_Repository_Interface $event_repository;

	public function __construct() {
		parent::__construct();
		$this->event_repository = Translation_Events::get_event_repository();
	}

	public function handle(): void {
		global $wp;
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( home_url( $wp->request ) ) );
			exit;
		}
		include ABSPATH . 'wp-admin/includes/post.php';

		$_events_i_am_or_will_attend_paged = 1;
		$_events_i_created_paged           = 1;
		$_events_i_hosted_paged            = 1;
		$_events_i_attended_paged          = 1;

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['events_i_am_or_will_attend_paged'] ) ) {
			$value = sanitize_text_field( wp_unslash( $_GET['events_i_am_or_will_attend_paged'] ) );
			if ( is_numeric( $value ) ) {
				$_events_i_am_or_will_attend_paged = (int) $value;
			}
		}
		if ( isset( $_GET['events_i_created_paged'] ) ) {
			$value = sanitize_text_field( wp_unslash( $_GET['events_i_created_paged'] ) );
			if ( is_numeric( $value ) ) {
				$_events_i_created_paged = (int) $value;
			}
		}
		if ( isset( $_GET['events_i_hosted_paged'] ) ) {
			$value = sanitize_text_field( wp_unslash( $_GET['events_i_hosted_paged'] ) );
			if ( is_numeric( $value ) ) {
				$_events_i_hosted_paged = (int) $value;
			}
		}
		if ( isset( $_GET['events_i_attended_paged'] ) ) {
			$value = sanitize_text_field( wp_unslash( $_GET['events_i_attended_paged'] ) );
			if ( is_numeric( $value ) ) {
				$_events_i_attended_paged = (int) $value;
			}
		}
		// phpcs:enable

		$events_i_am_or_will_attend_query = $this->event_repository->get_current_and_upcoming_events_for_user( get_current_user_id(), $_events_i_am_or_will_attend_paged, 10 );
		$events_i_created_query           = $this->event_repository->get_events_created_by_user( get_current_user_id(), $_events_i_created_paged, 10 );
		$events_i_host_query              = $this->event_repository->get_events_hosted_by_user( get_current_user_id(), $_events_i_hosted_paged, 10 );
		$events_i_attended_query          = $this->event_repository->get_past_events_for_user( get_current_user_id(), $_events_i_attended_paged, 10 );

		$this->tmpl(
			'events-my-events',
			array(
				'events_i_am_or_will_attend_query' => $events_i_am_or_will_attend_query,
				'events_i_created_query'           => $events_i_created_query,
				'events_i_host_query'              => $events_i_host_query,
				'events_i_attended_query'          => $events_i_attended_query,
			),
		);
	}
}
