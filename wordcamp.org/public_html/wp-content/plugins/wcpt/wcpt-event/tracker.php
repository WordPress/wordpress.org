<?php

namespace WordPress_Community\Applications\Tracker;

use \WordCamp_Loader;
defined( 'WPINC' ) or die();

const SHORTCODE_SLUG = 'application-tracker';

add_shortcode( SHORTCODE_SLUG, __NAMESPACE__ . '\render_status_shortcode' );
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\enqueue_scripts' );

/**
 * Render the [application-tracker] shortcode.
 */
function render_status_shortcode() {
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
			SELECT DISTINCT post_id
			FROM {$wpdb->prefix}postmeta
			WHERE
				meta_key like '_status_change_log_$wordcamp_post_type%'
			AND
				meta_value >= %d
			",
			$inactive_timestamp
		)
	);
	$wordcamp_post_ids = wp_list_pluck( $wordcamp_post_objs, 'post_id' );

	$raw_posts = get_posts(
		array(
			'post_type'      => WCPT_POST_TYPE_ID,
			'post_status'    => $shown_statuses,
			'posts_per_page' => -1,
			'order'          => 'ASC',
			'orderby'        => 'post_title',
			'post__in'       => $wordcamp_post_ids,
		)
	);

	foreach ( $raw_posts as $key => $post ) {
		$last_update_timestamp = get_last_update_timestamp( $post->ID );

		$wordcamps[] = array(
			'city'       => $post->post_title,
			'cityUrl'    => filter_var( get_post_meta( $post->ID, 'URL', true ), FILTER_VALIDATE_URL ),
			'applicant'  => get_post_meta( $post->ID, 'Organizer Name', true ),
			'milestone'  => $milestones[ $post->post_status ],
			'status'     => $statuses[ $post->post_status ],
			'lastUpdate' => $last_update_timestamp,
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
