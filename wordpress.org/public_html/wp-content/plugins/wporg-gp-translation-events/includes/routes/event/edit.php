<?php

namespace Wporg\TranslationEvents\Routes\Event;

use Wporg\TranslationEvents\Event\Event_Repository_Interface;
use Wporg\TranslationEvents\Routes\Route;
use Wporg\TranslationEvents\Translation_Events;
use Wporg\TranslationEvents\Urls;

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
		$event_page_title        = 'Edit Event';
		$event_form_name         = 'edit_event';
		$css_show_url            = '';
		$event_title             = $event->title();
		$event_description       = $event->description();
		$event_status            = $event->status();
		$event_url               = Urls::event_details_absolute( $event_id );
		$event_timezone          = $event->timezone();
		$event_start             = $event->start();
		$event_end               = $event->end();
		$event_slug              = $event->slug();
		$create_trash_button     = current_user_can( 'trash_translation_event', $event->id() );
		$visibility_trash_button = 'inline-flex';

		$this->tmpl( 'events-form', get_defined_vars() );
	}
}
