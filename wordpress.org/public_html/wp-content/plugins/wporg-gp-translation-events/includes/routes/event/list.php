<?php

namespace Wporg\TranslationEvents\Routes\Event;

use DateTime;
use DateTimeZone;
use Exception;
use WP_Query;
use Wporg\TranslationEvents\Routes\Route;
use Wporg\TranslationEvents\Translation_Events;

/**
 * Displays the event list page.
 */
class List_Route extends Route {
	public function handle(): void {
		$current_datetime_utc = null;
		try {
			$current_datetime_utc = ( new DateTime( 'now', new DateTimeZone( 'UTC' ) ) )->format( 'Y-m-d H:i:s' );
		} catch ( Exception $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( $e );
			$this->die_with_error( esc_html__( 'Something is wrong.', 'gp-translation-events' ) );
		}

		$_current_events_paged        = 1;
		$_upcoming_events_paged       = 1;
		$_past_events_paged           = 1;
		$_user_attending_events_paged = 1;

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['current_events_paged'] ) ) {
			$value = sanitize_text_field( wp_unslash( $_GET['current_events_paged'] ) );
			if ( is_numeric( $value ) ) {
				$_current_events_paged = (int) $value;
			}
		}
		if ( isset( $_GET['upcoming_events_paged'] ) ) {
			$value = sanitize_text_field( wp_unslash( $_GET['upcoming_events_paged'] ) );
			if ( is_numeric( $value ) ) {
				$_upcoming_events_paged = (int) $value;
			}
		}
		if ( isset( $_GET['past_events_paged'] ) ) {
			$value = sanitize_text_field( wp_unslash( $_GET['past_events_paged'] ) );
			if ( is_numeric( $value ) ) {
				$_past_events_paged = (int) $value;
			}
		}
		if ( isset( $_GET['user_attending_events_paged'] ) ) {
			$value = sanitize_text_field( wp_unslash( $_GET['user_attending_events_paged'] ) );
			if ( is_numeric( $value ) ) {
				$_user_attending_events_paged = (int) $value;
			}
		}
		// phpcs:enable

		$current_events_args  = array(
			'post_type'      => Translation_Events::CPT,
			'posts_per_page' => 10,
			'paged'          => $_current_events_paged,
			'post_status'    => 'publish',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'     => array(
				array(
					'key'     => '_event_start',
					'value'   => $current_datetime_utc,
					'compare' => '<=',
					'type'    => 'DATETIME',
				),
				array(
					'key'     => '_event_end',
					'value'   => $current_datetime_utc,
					'compare' => '>=',
					'type'    => 'DATETIME',
				),
			),
			'orderby'        => 'meta_value',
			'order'          => 'ASC',
		);
		$current_events_query = new WP_Query( $current_events_args );

		$upcoming_events_args  = array(
			'post_type'      => Translation_Events::CPT,
			'posts_per_page' => 10,
			'paged'          => $_upcoming_events_paged,
			'post_status'    => 'publish',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'     => array(
				array(
					'key'     => '_event_start',
					'value'   => $current_datetime_utc,
					'compare' => '>=',
					'type'    => 'DATETIME',
				),
			),
			'orderby'        => 'meta_value',
			'order'          => 'ASC',
		);
		$upcoming_events_query = new WP_Query( $upcoming_events_args );

		$past_events_args  = array(
			'post_type'      => Translation_Events::CPT,
			'posts_per_page' => 10,
			'paged'          => $_past_events_paged,
			'post_status'    => 'publish',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'     => array(
				array(
					'key'     => '_event_end',
					'value'   => $current_datetime_utc,
					'compare' => '<',
					'type'    => 'DATETIME',
				),
			),
			'orderby'        => 'meta_value',
			'order'          => 'ASC',
		);
		$past_events_query = new WP_Query( $past_events_args );

		$user_attending_events      = get_user_meta( get_current_user_id(), Translation_Events::USER_META_KEY_ATTENDING, true ) ?: array( 0 );
		$user_attending_events_args = array(
			'post_type'      => Translation_Events::CPT,
			'post__in'       => array_keys( $user_attending_events ),
			'posts_per_page' => 10,
			'paged'          => $_user_attending_events_paged,
			'post_status'    => 'publish',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'     => array(
				array(
					'key'     => '_event_end',
					'value'   => $current_datetime_utc,
					'compare' => '>',
					'type'    => 'DATETIME',
				),
			),
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_key'       => '_event_start',
			'orderby'        => 'meta_value',
			'order'          => 'ASC',
		);
		$user_attending_events_query = new WP_Query( $user_attending_events_args );

		$this->tmpl( 'events-list', get_defined_vars() );
	}
}
