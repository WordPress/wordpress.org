<?php

namespace Wporg\TranslationEvents\Routes\Event;

use Wporg\TranslationEvents\Event\Event_Repository_Interface;
use Wporg\TranslationEvents\Routes\Route;
use Wporg\TranslationEvents\Translation_Events;
use Wporg\TranslationEvents\Urls;

/**
 * Permanently delete an Event.
 */
class Delete_Route extends Route {
	private Event_Repository_Interface $event_repository;

	public function __construct() {
		parent::__construct();
		$this->event_repository = Translation_Events::get_event_repository();
	}

	public function handle( int $event_id ): void {
		if ( ! is_user_logged_in() ) {
			global $wp;
			wp_safe_redirect( wp_login_url( home_url( $wp->request ) ) );
			$this->exit_();
			return; // exit_() doesn't exit under GP_Route::$fake_request.
		}

		$nonce_action = 'delete_translation_event_' . $event_id;
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), $nonce_action ) ) {
			$this->die_with_error( esc_html__( 'Your link has expired or is invalid. Please go back and try again.', 'gp-translation-events' ), 403 );
			return; // die_with_*() doesn't die under GP_Route::$fake_request.
		}

		$event = $this->event_repository->get_event( $event_id );
		if ( ! $event ) {
			$this->die_with_404();
			return; // die_with_*() doesn't die under GP_Route::$fake_request.
		}

		if ( ! current_user_can( 'manage_translation_events', $event->id() ) ) {
			$this->die_with_error( esc_html__( 'You do not have permission to delete events.', 'gp-translation-events' ), 403 );
			return; // die_with_*() doesn't die under GP_Route::$fake_request.
		}

		if ( ! current_user_can( 'delete_translation_event', $event->id() ) ) {
			$this->die_with_error( esc_html__( 'You do not have permission to delete this event.', 'gp-translation-events' ), 403 );
			return; // die_with_*() doesn't die under GP_Route::$fake_request.
		}

		$this->event_repository->delete_event( $event );

		wp_safe_redirect( Urls::events_home() );
		$this->exit_();
	}
}
