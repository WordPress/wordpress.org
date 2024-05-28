<?php

namespace Wporg\TranslationEvents\Routes\Event;

use DateTimeImmutable;
use DateTimeZone;
use Wporg\TranslationEvents\Event\Event;
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
			$this->die_with_error( 'You do not have permission to create events.', 403 );
		}

		$now = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );

		$event = new Event(
			get_current_user_id(),
			new Event_Start_Date( $now->format( 'Y-m-d H:i:s' ) ),
			new Event_End_Date( $now->modify( '+1 hour' )->format( 'Y-m-d H:i:s' ) ),
			new DateTimeZone( 'UTC' ),
			'draft',
			'',
			'',
		);

		$this->tmpl(
			'events-form',
			array(
				'is_create_form' => true,
				'event'          => $event,
			),
		);
	}
}
