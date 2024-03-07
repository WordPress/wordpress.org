<?php

namespace Wporg\TranslationEvents\Routes\Event;

use DateTime;
use DateTimeZone;
use Exception;
use Wporg\TranslationEvents\Routes\Route;
use Wporg\TranslationEvents\Stats_Calculator;
use Wporg\TranslationEvents\Translation_Events;

/**
 * Displays the event edit page.
 */
class Edit_Route extends Route {
	public function handle( int $event_id ): void {
		global $wp;
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( home_url( $wp->request ) ) );
			exit;
		}
		$event = get_post( $event_id );
		if ( ! $event || Translation_Events::CPT !== $event->post_type || ! ( current_user_can( 'edit_post', $event->ID ) || intval( $event->post_author ) === get_current_user_id() ) ) {
			$this->die_with_error( esc_html__( 'Event does not exist, or you do not have permission to edit it.', 'gp-translation-events' ), 403 );
		}
		if ( 'trash' === $event->post_status ) {
			$this->die_with_error( esc_html__( 'You cannot edit a trashed event', 'gp-translation-events' ), 403 );
		}

		include ABSPATH . 'wp-admin/includes/post.php';
		$event_form_title              = 'Edit Event';
		$event_form_name               = 'edit_event';
		$css_show_url                  = '';
		$event_title                   = $event->post_title;
		$event_description             = $event->post_content;
		$event_status                  = $event->post_status;
		list( $permalink, $post_name ) = get_sample_permalink( $event_id );
		$permalink                     = str_replace( '%pagename%', $post_name, $permalink );
		$event_url                     = get_site_url() . gp_url( wp_make_link_relative( $permalink ) );
		$event_timezone                = get_post_meta( $event_id, '_event_timezone', true ) ?: '';
		$create_delete_button          = false;
		$visibility_delete_button      = 'inline-flex';

		$stats_calculator = new Stats_Calculator();
		if ( ! $stats_calculator->event_has_stats( $event ) ) {
			$current_user = wp_get_current_user();
			if ( $current_user->ID === $event->post_author || current_user_can( 'manage_options' ) ) {
				$create_delete_button = true;
			}
		}

		try {
			$event_start = self::convertToTimezone( get_post_meta( $event_id, '_event_start', true ), $event_timezone );
			$event_end   = self::convertToTimezone( get_post_meta( $event_id, '_event_end', true ), $event_timezone );
		} catch ( Exception $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( $e );
			$this->die_with_error( esc_html__( 'Something is wrong.', 'gp-translation-events' ) );
		}

		$this->tmpl( 'events-form', get_defined_vars() );
	}

	/**
	 * Convert date time stored in UTC to a date time in a time zone.
	 *
	 * @param string $date_time The date time in UTC.
	 * @param string $time_zone The time zone.
	 *
	 * @return string The date time in the time zone.
	 * @throws Exception When date is invalid.
	 */
	private static function convertToTimezone( string $date_time, string $time_zone ): string {
		return ( new DateTime( $date_time, new DateTimeZone( 'UTC' ) ) )->setTimezone( new DateTimeZone( $time_zone ) )->format( 'Y-m-d H:i:s' );
	}
}
