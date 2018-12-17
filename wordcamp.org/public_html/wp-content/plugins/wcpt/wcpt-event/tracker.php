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
 * Get camps that are active enough to be shown on the tracker
 *
 * @return array
 */
function get_active_wordcamps() {
	global $wpdb;
	$wordcamps          = array();
	$statuses           = WordCamp_Loader::get_post_statuses();
	$milestones         = WordCamp_Loader::map_statuses_to_milestones();
	$inactive_timestamp = strtotime( '60 days ago' );

	$shown_statuses = $statuses;
	unset( $shown_statuses[ WCPT_FINAL_STATUS ] );
	$shown_statuses = array_keys( $shown_statuses );
	$wordcamp_post_type = WCPT_POST_TYPE_ID;

	$wordcamp_post_objs = $wpdb->get_results(
		$wpdb->prepare(
			"
			SELECT DISTINCT post_id, MAX( meta_value ) as last_updated
			FROM {$wpdb->prefix}postmeta
			WHERE
				meta_key like '_status_change_log_$wordcamp_post_type%'
			AND
				meta_value >= %d
			GROUP BY post_id
			",
			$inactive_timestamp
		)
	);
	$wordcamp_post_obj = array();
	foreach ( $wordcamp_post_objs as $wordcamp_post ) {
		$wordcamp_post_obj[ $wordcamp_post->post_id ] = $wordcamp_post->last_updated;
	}

	$raw_posts = get_posts(
		array(
			'post_type'      => WCPT_POST_TYPE_ID,
			'post_status'    => $shown_statuses,
			'posts_per_page' => 1000,
			'order'          => 'ASC',
			'orderby'        => 'post_title',
			'post__in'       => array_keys ( $wordcamp_post_obj ),
		)
	);

	foreach ( $raw_posts as $key => $post ) {
		$last_update_timestamp = $wordcamp_post_obj[ $post->ID ];

		$wordcamps[] = array(
			'city'       => $post->post_title,
			'cityUrl'    => filter_var( get_post_meta( $post->ID, 'URL', true ), FILTER_VALIDATE_URL ),
			'applicant'  => get_post_meta( $post->ID, 'Organizer Name', true ),
			'milestone'  => $milestones[ $post->post_status ],
			'status'     => $statuses[ $post->post_status ],
			'lastUpdate' => time() - $last_update_timestamp,
		);
	}

	return $wordcamps;
}

/**
 * Get the columns headers for WordCamp
 *
 * @return array
 */
function get_wordcamp_display_columns() {
	return array(
		'city'       => 'City',
		'applicant'  => 'Applicant',
		'milestone'  => 'Milestone',
		'status'     => 'Status',
		'lastUpdate' => 'Updated',
	);
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

	if ( 'wordcamp' === $application_type ) {
		wp_localize_script(
			'wpc-application-tracker',
			'wpcApplicationTracker',
			array(
				'applications'     => get_active_wordcamps(),
				'displayColumns'   => get_wordcamp_display_columns(),
				'initialSortField' => 'city',
			)
		);
	} elseif ( 'meetup' === $application_type ) {
		wp_localize_script(
			'wpc-application-tracker',
			'wpcApplicationTracker',
			array(
				'applications',
				'displayColumns',
				'initialSortField',
			)
		);
	} 
}

