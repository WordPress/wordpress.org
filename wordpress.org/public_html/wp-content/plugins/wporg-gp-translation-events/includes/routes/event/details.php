<?php

namespace Wporg\TranslationEvents\Routes\Event;

use Exception;
use GP;
use Wporg\TranslationEvents\Routes\Route;
use Wporg\TranslationEvents\Stats_Calculator;
use Wporg\TranslationEvents\Translation_Events;

/**
 * Displays the event details page.
 */
class Details_Route extends Route {
	public function handle( string $event_slug ): void {
		$user  = wp_get_current_user();
		$event = get_page_by_path( $event_slug, OBJECT, Translation_Events::CPT );
		if ( ! $event ) {
			$this->die_with_404();
		}

		/**
		 * Filter the ability to create, edit, or delete an event.
		 *
		 * @param bool $can_crud_event Whether the user can create, edit, or delete an event.
		 */
		$can_crud_event = apply_filters( 'gp_translation_events_can_crud_event', GP::$permission->current_user_can( 'admin' ) );
		if ( 'publish' !== $event->post_status && ! $can_crud_event ) {
			$this->die_with_error( esc_html__( 'You are not authorized to view this page.', 'gp-translation-events' ), 403 );
		}

		$event_id            = $event->ID;
		$event_title         = $event->post_title;
		$event_description   = $event->post_content;
		$event_start         = get_post_meta( $event->ID, '_event_start', true ) ?: '';
		$event_end           = get_post_meta( $event->ID, '_event_end', true ) ?: '';
		$attending_event_ids = get_user_meta( $user->ID, Translation_Events::USER_META_KEY_ATTENDING, true ) ?: array();
		$user_is_attending   = isset( $attending_event_ids[ $event_id ] );

		$stats_calculator = new Stats_Calculator();
		try {
			$event_stats  = $stats_calculator->for_event( $event );
			$contributors = $stats_calculator->get_contributors( $event );
		} catch ( Exception $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( $e );
			$this->die_with_error( esc_html__( 'Failed to calculate event stats', 'gp-translation-events' ) );
		}

		$this->tmpl( 'event', get_defined_vars() );
	}
}
