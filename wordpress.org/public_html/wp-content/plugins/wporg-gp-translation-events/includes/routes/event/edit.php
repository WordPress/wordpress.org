<?php

namespace Wporg\TranslationEvents\Routes\Event;

use Wporg\TranslationEvents\Event\Event_Repository_Interface;
use Wporg\TranslationEvents\Routes\Route;
use Wporg\TranslationEvents\Translation_Events;

/**
 * Displays the event edit page.
 */
class Edit_Route extends Route {
	private Event_Repository_Interface $event_repository;

	public function __construct() {
		parent::__construct();
		$this->event_repository = Translation_Events::get_event_repository();
	}

	public function handle( int $event_id ): void {
		global $wp;
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( home_url( $wp->request ) ) );
			exit;
		}

		$event = $this->event_repository->get_event( $event_id );
		if ( ! $event ) {
			$this->die_with_404();
		}

		if ( ! current_user_can( 'edit_translation_event', $event->id() ) ) {
			$this->die_with_error( esc_html__( 'You do not have permission to edit this event.', 'gp-translation-events' ), 403 );
		}

		include ABSPATH . 'wp-admin/includes/post.php';

		$this->tmpl(
			'events-form',
			array(
				'is_create_form' => false,
				'event'          => $event,
			),
		);
	}
}
