<?php

namespace Wporg\TranslationEvents;

use DateTime;
use DateTimeZone;
use Exception;
use GP_Route;
use WP_Query;
use GP;

class Route extends GP_Route {
	public const USER_META_KEY_ATTENDING = 'translation-events-attending';

	public function __construct() {
		parent::__construct();
		$this->template_path = __DIR__ . '/../templates/';
	}

	/**
	 * Loads the 'events_list' template.
	 *
	 * @return void
	 */
	public function events_list() {
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

		$user_attending_events      = get_user_meta( get_current_user_id(), self::USER_META_KEY_ATTENDING, true ) ?: array( 0 );
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

	/**
	 * Loads the 'events_create' template.
	 *
	 * @return void
	 */
	public function events_create() {
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

	/**
	 * Loads the 'events_edit' template.
	 *
	 * @param int $event_id The event ID.
	 *
	 * @return void
	 */
	public function events_edit( int $event_id ) {
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
	 * Loads the 'event' template.
	 *
	 * @param string $event_slug The event slug.
	 *
	 * @return void
	 */
	public function events_details( string $event_slug ) {
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
		$attending_event_ids = get_user_meta( $user->ID, self::USER_META_KEY_ATTENDING, true ) ?: array();
		$user_is_attending   = isset( $attending_event_ids[ $event_id ] );

		$stats_calculator = new Stats_Calculator();
		try {
			$event_stats = $stats_calculator->for_event( $event );
		} catch ( Exception $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( $e );
			$this->die_with_error( esc_html__( 'Failed to calculate event stats', 'gp-translation-events' ) );
		}

		$this->tmpl( 'event', get_defined_vars() );
	}

	/**
	 * Toggle whether the current user is attending an event.
	 * If the user is not currently marked as attending, they will be marked as attending.
	 * If the user is currently marked as attending, they will be marked as not attending.
	 */
	public function events_attend( int $event_id ) {
		$user = wp_get_current_user();
		if ( ! $user ) {
			$this->die_with_error( esc_html__( 'Only logged-in users can attend events', 'gp-translation-events' ), 403 );
		}

		$event = get_post( $event_id );

		if ( ! $event ) {
			$this->die_with_404();
		}

		$event_ids = get_user_meta( $user->ID, self::USER_META_KEY_ATTENDING, true ) ?? array();
		if ( ! $event_ids ) {
			$event_ids = array();
		}

		if ( ! isset( $event_ids[ $event_id ] ) ) {
			// Not yet attending, mark as attending.
			$event_ids[ $event_id ] = true;
		} else {
			// Currently attending, mark as not attending.
			unset( $event_ids[ $event_id ] );
		}

		update_user_meta( $user->ID, self::USER_META_KEY_ATTENDING, $event_ids );

		wp_safe_redirect( gp_url( "/events/$event->post_name" ) );
		exit;
	}

	/**
	 * Loads the 'events_user_created' template.
	 *
	 * @return void
	 */
	public function my_events() {
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
		$events               = get_user_meta( $user_id, self::USER_META_KEY_ATTENDING, true ) ?: array();
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

	/**
	 * Convert date time stored in UTC to a date time in a time zone.
	 *
	 * @param string $date_time The date time in UTC.
	 * @param string $time_zone The time zone.
	 *
	 * @return string The date time in the time zone.
	 * @throws Exception When date is invalid.
	 */
	public static function convertToTimezone( string $date_time, string $time_zone ): string {
		return ( new DateTime( $date_time, new DateTimeZone( 'UTC' ) ) )->setTimezone( new DateTimeZone( $time_zone ) )->format( 'Y-m-d H:i:s' );
	}
}
