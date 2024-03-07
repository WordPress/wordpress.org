<?php

namespace Wporg\TranslationEvents\Routes\Event;

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
		$event_form_title         = 'Create Event';
		$event_form_name          = 'create_event';
		$css_show_url             = 'hide-event-url';
		$event_id                 = null;
		$event_title              = '';
		$event_description        = '';
		$event_timezone           = '';
		$event_start              = '';
		$event_end                = '';
		$event_url                = '';
		$create_delete_button     = true;
		$visibility_delete_button = 'none';

		$this->tmpl( 'events-form', get_defined_vars() );
	}
}
