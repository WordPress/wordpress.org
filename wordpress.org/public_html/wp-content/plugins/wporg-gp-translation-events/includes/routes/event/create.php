<?php

namespace Wporg\TranslationEvents\Routes\Event;

use Wporg\TranslationEvents\Event\Event_End_Date;
use Wporg\TranslationEvents\Event\Event_Start_Date;
use Wporg\TranslationEvents\Routes\Route;

/**
 * Displays the event create page.
 */
class Create_Route extends Route {
	public function handle(): void {
		global $wp;
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( home_url( $wp->request ) ) );
			exit;
		}

		if ( ! current_user_can( 'create_translation_event' ) ) {
			$this->die_with_error( 'You do not have permission to create events.' );
		}

		$event_page_title        = 'Create Event';
		$event_form_name         = 'create_event';
		$css_show_url            = 'hide-event-url';
		$event_id                = null;
		$event_title             = '';
		$event_description       = '';
		$event_url               = '';
		$create_trash_button     = true;
		$visibility_trash_button = 'none';
		$event_timezone          = null;
		$event_start             = new Event_Start_Date( date_i18n( 'Y - m - d H:i' ) );
		$event_end               = new Event_End_Date( date_i18n( 'Y - m - d H:i' ) );
		$event_slug              = '';

		$this->tmpl( 'events-form', get_defined_vars() );
	}
}
