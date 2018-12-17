<?php

namespace WordPress_Community\Applications\Tracker;

use \WordCamp_Loader;
defined( 'WPINC' ) or die();

const SHORTCODE_SLUG = 'application-tracker';

add_shortcode( SHORTCODE_SLUG, __NAMESPACE__ . '\render_status_shortcode' );

/**
 * Render the [application-tracker] shortcode.
 */
function render_status_shortcode( $atts = [] ) {
	$application_type = 'wordcamp';
	if ( isset ( $atts['type'] ) ) {
		$application_type = $atts['type'];
	}
	enqueue_scripts( $application_type );
	return '<div id="wpc-application-tracker">Loading WordCamp Application Tracker...</div>';
}

/**
 * Get applications that are active enough to be shown on the tracker
 *
 * @param string $application_type Type of application. Could be `wordcamp` or `meetup`.
 * @return array
 */
function get_active_events( $application_type ) {
	global $wpdb;
	$events             = array();
	$shown_statuses     = array();
	$statuses           = array();
	$milestones         = array();
	$inactive_timestamp = strtotime( '60 days ago' );
	$post_type          = '';

	if ( 'wordcamp' === $application_type ) {
		$statuses = WordCamp_Loader::get_post_statuses();
		$milestones = WordCamp_Loader::map_statuses_to_milestones();
		unset( $shown_statuses[ WCPT_FINAL_STATUS ] );
		$post_type = WCPT_POST_TYPE_ID;
	} elseif ( 'meetup' === $application_type ) {
		$statuses = \Meetup_Loader::get_post_statuses();
		$post_type = WCPT_MEETUP_SLUG;
	}

	$shown_statuses = array_keys( $statuses );

	$event_post_objs = $wpdb->get_results(
		$wpdb->prepare(
			"
			SELECT DISTINCT post_id, MAX( meta_value ) as last_updated
			FROM {$wpdb->prefix}postmeta
			WHERE
				meta_key like '_status_change_log_$post_type%'
			AND
				meta_value >= %d
			GROUP BY post_id
			",
			$inactive_timestamp
		)
	);

	$event_posts = array();
	foreach ( $event_post_objs as $event_post ) {
		$event_posts[ $event_post->post_id ] = $event_post->last_updated;
	}

	$raw_posts = get_posts(
		array(
			'post_type'      => $post_type,
			'post_status'    => $shown_statuses,
			'posts_per_page' => 1000,
			'order'          => 'ASC',
			'orderby'        => 'post_title',
			'post__in'       => array_keys ( $event_posts ),
		)
	);

	foreach ( $raw_posts as $key => $post ) {
		$last_update_timestamp = $event_posts[ $post->ID ];

		if ( 'wordcamp' === $application_type ) {
			$events[] = array(
				'city'       => $post->post_title,
				'cityUrl'    => filter_var( get_post_meta( $post->ID, 'URL', true ), FILTER_VALIDATE_URL ),
				'applicant'  => esc_html( get_post_meta( $post->ID, 'Organizer Name', true ) ),
				'milestone'  => $milestones[ $post->post_status ],
				'status'     => $statuses[ $post->post_status ],
				'lastUpdate' => time() - $last_update_timestamp,
			);
		} elseif ( 'meetup' === $application_type ) {
			$events[] = array(
				'city'       => $post->post_title,
				'cityUrl'    => filter_var( get_post_meta( $post->ID, 'Meetup URL', true ), FILTER_VALIDATE_URL ),
				'applicant'  => esc_html( get_post_meta( $post->ID, 'Organizer Name', true ) ),
				'status'     => $statuses[ $post->post_status ],
				'lastUpdate' => time() - $last_update_timestamp,
			);
		}
	}

	return $events;
}

/**
 * Get the columns headers for WordCamp
 *
 * @return array
 */
function get_display_columns( $application_type ) {
	switch ( $application_type ) {
		case 'wordcamp':
			return array(
				'city'       => 'City',
				'applicant'  => 'Applicant',
				'milestone'  => 'Milestone',
				'status'     => 'Status',
				'lastUpdate' => 'Updated',
			);
		case 'meetup':
			return array(
				'city' => 'City',
				'applicant' => 'Applicant',
				'status' => 'Status',
				'lastUpdate' => 'Updated',
			);
	}
}

/**
 * Enqueue scripts and styles.
 * Based on the event type passed, we will localize different data for Meetup and WordCamp events.
 * 
 * @param string application_type Application type for the tracker table. Could be either `wordcamp` or `meetup`. 
 */
function enqueue_scripts( $application_type ) {
	global $post;

	wp_register_script(
		'wpc-application-tracker',
		plugins_url( 'javascript/tracker/build/applications.min.js', dirname( __FILE__ ) ), // this file was renamed from 'tracker', which was getting flagged by ad blockers
		array(),
		1,
		true
	);

	wp_register_style(
		'wpc-application-tracker',
		plugins_url( 'javascript/tracker/build/applications.min.css', dirname( __FILE__ ) ), // this file was renamed from 'tracker', which was getting flagged by ad blockers
		array( 'dashicons', 'list-tables' ),
		1
	);

	wp_enqueue_script( 'wpc-application-tracker' );

	wp_enqueue_style( 'wpc-application-tracker' );

	wp_localize_script(
		'wpc-application-tracker',
		'wpcApplicationTracker',
		array(
			'applications'     => get_active_events( $application_type ),
			'displayColumns'   => get_display_columns( $application_type ),
			'initialSortField' => 'city',
		)
	);
}

