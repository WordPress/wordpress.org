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

	$shown_statuses = $statuses;
	unset( $shown_statuses[ WCPT_FINAL_STATUS ] );
	$shown_statuses = array_keys( $shown_statuses );

	$posts = get_posts( array(
		'post_type'      => WCPT_POST_TYPE_ID,
		'post_status'    => $shown_statuses,
		'posts_per_page' => 300,
		'order'          => 'ASC',
		'orderby'        => 'post_title',
	) );

	require_once( dirname( __DIR__ ) . '/wcpt-wordcamp/wordcamp-admin.php'                             );
	require(      dirname( __DIR__ ) . '/views/applications/tracker/shortcode-application-tracker.php' );
}

/**
 * Get the time difference between now and the last status update.
 *
 * @param $post_id
 *
 * @return string
 */
function get_last_status_update_time_diff( $post_id ) {
	$time_diff      = '';
	$status_changes = get_post_meta( $post_id, '_status_change' );

	if ( $status_changes ) {
		usort( $status_changes, 'wcpt_sort_log_entries' );
		$time_diff = human_time_diff( time(), $status_changes[0]['timestamp'] ) . ' ago';
	}

	return $time_diff;
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
