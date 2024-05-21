<?php

namespace Wporg\TranslationEvents\Routes\Event;

use Wporg\TranslationEvents\Event\Event_Repository_Interface;
use Wporg\TranslationEvents\Routes\Route;
use Wporg\TranslationEvents\Translation_Events;
use Wporg\TranslationEvents\Urls;

/**
 * Toggle whether the event is trashed.
 * If the event is not currently trashed, it will be trashed.
 * If the event is currently trashed, it will be un-trashed.
 */
class Trash_Route extends Route {
	private Event_Repository_Interface $event_repository;

	public function __construct() {
		parent::__construct();
		$this->event_repository = Translation_Events::get_event_repository();
	}

	public function handle( int $event_id ): void {
		if ( ! is_user_logged_in() ) {
			global $wp;
			wp_safe_redirect( wp_login_url( home_url( $wp->request ) ) );
			exit;
		}

		$event = $this->event_repository->get_event( $event_id );
		if ( ! $event ) {
			$this->die_with_404();
		}

		if ( ! current_user_can( 'trash_translation_event', $event->id() ) ) {
			$this->die_with_error( esc_html__( 'You do not have permission to delete or restore this event.', 'gp-translation-events' ), 403 );
		}

		if ( ! $event->is_trashed() ) {
			// Trash.
			$this->event_repository->trash_event( $event );
			wp_safe_redirect( Urls::events_home() );
		} else {
			// Restore.
			$event->set_status( 'draft' );
			$this->event_repository->update_event( $event );
			wp_safe_redirect( Urls::event_edit( $event->id() ) );
		}

		exit;
	}
}
