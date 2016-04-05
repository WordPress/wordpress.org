<?php

namespace WordPress_Community\Applications\Tracker;
defined( 'WPINC' ) or die();

const SHORTCODE_SLUG = 'application-tracker';

add_shortcode( SHORTCODE_SLUG, __NAMESPACE__ . '\render_status_shortcode' );
add_action( 'wp_print_styles', __NAMESPACE__ . '\print_shortcode_styles'  );

/**
 * Render the [application-tracker] shortcode.
 */
function render_status_shortcode() {
	$statuses   = \WordCamp_Loader::get_post_statuses();
	$milestones = \WordCamp_Loader::map_statuses_to_milestones();
	$posts      = get_active_wordcamps( $statuses );

	require_once( dirname( __DIR__ ) . '/wcpt-wordcamp/wordcamp-admin.php'                             );
	require(      dirname( __DIR__ ) . '/views/applications/tracker/shortcode-application-tracker.php' );
}

/**
 * Get camps that are active enough to be shown on the tracker
 *
 * @param array $statuses
 *
 * @return array
 */
function get_active_wordcamps( $statuses ) {
	$inactive_timestamp = strtotime( '60 days ago' );

	$shown_statuses = $statuses;
	unset( $shown_statuses[ WCPT_FINAL_STATUS ] );
	$shown_statuses = array_keys( $shown_statuses );

	$wordcamps = get_posts( array(
		'post_type'      => WCPT_POST_TYPE_ID,
		'post_status'    => $shown_statuses,
		'posts_per_page' => 300,
		'order'          => 'ASC',
		'orderby'        => 'post_title',
	) );

	foreach ( $wordcamps as $key => $wordcamp ) {
		$wordcamp->last_update_timestamp = get_last_update_timestamp( $wordcamp->ID );

		if ( $wordcamp->last_update_timestamp <= $inactive_timestamp ) {
			unset( $wordcamps[ $key ] );
		}
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
 * Print CSS styles for the [application-tracker] shortcode.
 */
function print_shortcode_styles() {
	global $post;

	if ( empty( $post->post_content ) || ! has_shortcode( $post->post_content, SHORTCODE_SLUG ) ) {
		return;
	}
	
	?>

	<style type="text/css">
		.application-tracker.striped > tbody > :nth-child( odd ) {
			background-color: #f9f9f9;
		}

		/* Show extra data on large screens */
		.application-tracker .milestone,
		.application-tracker .applicant {
			display: none;
		}

		@media screen and ( min-width: 750px ) {
			.application-tracker .applicant {
				display: table-cell;
			}
		}

		@media screen and ( min-width: 850px ) {
			.application-tracker .milestone {
				display: table-cell;
			}
		}
	</style>

	<?php
}
