<?php

namespace WordPress_Community\Applications\Tracker;
use \WordCamp_Loader;
defined( 'WPINC' ) or die();

const SHORTCODE_SLUG = 'application-tracker';

add_shortcode( SHORTCODE_SLUG,    __NAMESPACE__ . '\render_status_shortcode' );
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\enqueue_scripts'         );

/**
 * Render the [application-tracker] shortcode.
 */
function render_status_shortcode() {
	return '<div id="wpc-application-tracker">Loading Application Tracker...</div>';
}

/**
 * Get camps that are active enough to be shown on the tracker
 *
 * @return array
 */
function get_active_wordcamps() {
	$wordcamps          = array();
	$statuses           = WordCamp_Loader::get_post_statuses();
	$milestones         = WordCamp_Loader::map_statuses_to_milestones();
	$inactive_timestamp = strtotime( '60 days ago' );

	$shown_statuses = $statuses;
	unset( $shown_statuses[ WCPT_FINAL_STATUS ] );
	$shown_statuses = array_keys( $shown_statuses );

	$raw_posts = get_posts( array(
		'post_type'      => WCPT_POST_TYPE_ID,
		'post_status'    => $shown_statuses,
		'posts_per_page' => 300,
		'order'          => 'ASC',
		'orderby'        => 'post_title',
	) );

	foreach ( $raw_posts as $key => $post ) {
		$last_update_timestamp = get_last_update_timestamp( $post->ID );

		if ( $last_update_timestamp <= $inactive_timestamp ) {
			continue;
		}

		$wordcamps[] = array(
			'city'       => $post->post_title,
			'cityUrl'    => filter_var( get_post_meta( $post->ID, 'URL', true ), FILTER_VALIDATE_URL ),
			'applicant'  => get_post_meta( $post->ID, 'Organizer Name', true ),
			'milestone'  => $milestones[ $post->post_status ],
			'status'     => $statuses[ $post->post_status ],
			'lastUpdate' => human_time_diff( time(), $last_update_timestamp ) . ' ago',
		);
	}

	return $wordcamps;
}

/**
 * Get the timestamp of the last time the post status changed
 *
 * @param int $post_id
 *
 * @return int
 */
function get_last_update_timestamp( $post_id ) {
	$last_update_timestamp = 0;
	$status_changes        = get_post_meta( $post_id, '_status_change' );

	if ( $status_changes ) {
		usort( $status_changes, 'wcpt_sort_log_entries' );
		$last_update_timestamp = $status_changes[0]['timestamp'];
	}

	return $last_update_timestamp;
}

/**
 * Enqueue scripts and styles
 */
function enqueue_scripts() {
	global $post;

	wp_register_script(
		'wpc-application-tracker',
		plugins_url( 'javascript/tracker/build/tracker.min.js', dirname( __FILE__ ) ),
		array(),
		1,
		true
	);

	wp_register_style(
		'wpc-application-tracker',
		plugins_url( 'javascript/tracker/build/tracker.min.css', dirname( __FILE__ ) ),
		array( 'dashicons', 'list-tables' ),
		1
	);

	if ( ! is_a( $post, 'WP_POST' ) || ! has_shortcode( $post->post_content, SHORTCODE_SLUG ) ) {
		return;
	}

	wp_enqueue_script( 'wpc-application-tracker' );

	wp_localize_script(
		'wpc-application-tracker',
		'wpcApplicationTracker',
		array(
			'applications'     => get_active_wordcamps(),
			'displayColumns'   => get_display_columns(),
			'initialSortField' => 'city',
		)
	);

	wp_enqueue_style( 'wpc-application-tracker' );
}

/**
 * Get the columns headers
 *
 * @return array
 */
function get_display_columns() {
	return array(
		'city'       => 'City',
		'applicant'  => 'Applicant',
		'milestone'  => 'Milestone',
		'status'     => 'Status',
		'lastUpdate' => 'Updated',
	);
}
