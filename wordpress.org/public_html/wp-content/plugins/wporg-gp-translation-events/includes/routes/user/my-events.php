<?php

namespace Wporg\TranslationEvents\Routes\User;

use DateTime;
use DateTimeZone;
use WP_Query;
use Wporg\TranslationEvents\Routes\Route;
use Wporg\TranslationEvents\Translation_Events;

/**
 * Displays the My Events page for a user.
 */
class My_Events_Route extends Route {
	public function handle(): void {
		global $wp;
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( home_url( $wp->request ) ) );
			exit;
		}
		include ABSPATH . 'wp-admin/includes/post.php';

		$_events_i_created_paged  = 1;
		$_events_i_attended_paged = 1;

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['events_i_created_paged'] ) ) {
			$value = sanitize_text_field( wp_unslash( $_GET['events_i_created_paged'] ) );
			if ( is_numeric( $value ) ) {
				$_events_i_created_paged = (int) $value;
			}
		}
		if ( isset( $_GET['events_i_attended_paged'] ) ) {
			$value = sanitize_text_field( wp_unslash( $_GET['events_i_attended_paged'] ) );
			if ( is_numeric( $value ) ) {
				$_events_i_attended_paged = (int) $value;
			}
		}
		// phpcs:enable

		$user_id              = get_current_user_id();
		$events               = get_user_meta( $user_id, Translation_Events::USER_META_KEY_ATTENDING, true ) ?: array();
		$events               = array_keys( $events );
		$current_datetime_utc = ( new DateTime( 'now', new DateTimeZone( 'UTC' ) ) )->format( 'Y-m-d H:i:s' );
		$args                 = array(
			'post_type'              => Translation_Events::CPT,
			'posts_per_page'         => 10,
			'events_i_created_paged' => $_events_i_created_paged,
			'paged'                  => $_events_i_created_paged,
			'post_status'            => array( 'publish', 'draft' ),
			'author'                 => $user_id,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_key'               => '_event_start',
			'orderby'                => 'meta_value',
			'order'                  => 'DESC',
		);
		$events_i_created_query = new WP_Query( $args );

		$args = array(
			'post_type'               => Translation_Events::CPT,
			'posts_per_page'          => 10,
			'events_i_attended_paged' => $_events_i_attended_paged,
			'paged'                   => $_events_i_attended_paged,
			'post_status'             => 'publish',
			'post__in'                => $events,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'              => array(
				array(
					'key'     => '_event_end',
					'value'   => $current_datetime_utc,
					'compare' => '<',
					'type'    => 'DATETIME',
				),
			),
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_key'                => '_event_end',
			'orderby'                 => 'meta_value',
			'order'                   => 'DESC',
		);
		$events_i_attended_query = new WP_Query( $args );

		$this->tmpl( 'events-my-events', get_defined_vars() );
	}
}
