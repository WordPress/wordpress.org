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
		$event_page_title         = 'Create Event';
		$event_form_name          = 'create_event';
		$css_show_url             = 'hide-event-url';
		$event_id                 = null;
		$event_title              = '';
		$event_description        = '';
		$event_url                = '';
		$create_delete_button     = true;
		$visibility_delete_button = 'none';
		$event_timezone           = null;
		$event_start              = new Event_Start_Date( date_i18n( 'Y - m - d H:i' ) );
		$event_end                = new Event_End_Date( date_i18n( 'Y - m - d H:i' ) );

		$this->tmpl( 'events-form', get_defined_vars() );
	}
}
