<?php

namespace WordCamp\Mentors_Dashboard;
defined( 'WPINC' ) or die();

add_action( 'admin_menu', __NAMESPACE__ . '\add_admin_pages' );

/**
 * Register new admin pages
 */
function add_admin_pages() {
	$page_hook = \add_submenu_page(
		'edit.php?post_type=wordcamp',
		'Mentors',
		'Mentors',
		'manage_network',
		'mentors',
		__NAMESPACE__ . '\render_options_page'
	);
}

/**
 * Render the view for the options page
 */
function render_options_page() {
	$mentors          = get_mentors();
	$unmentored_camps = get_unmentored_camps();

	require_once( dirname( __DIR__ ) . '/views/mentors/dashboard.php' );
}

/**
 * Get all mentors
 *
 * @return array
 */
function get_mentors() {
	$mentors = array();

	$mentored_camps = get_posts( array(
		'post_type'      => WCPT_POST_TYPE_ID,
		'post_status'    => \WordCamp_Loader::get_mentored_post_statuses(),
		'posts_per_page' => 10000,
		'meta_query'     => array(
			array(
				'key'     => 'Mentor E-mail Address',
				'value'   => '',
				'compare' => '!='
			),
		),
	) );

	foreach ( $mentored_camps as $camp ) {
		$email = get_post_meta( $camp->ID, 'Mentor E-mail Address', true );

		/*
		 * Closed camps were included in the query above, in order to get all mentors, even if they're not
		 * currently mentoring any active camps. Closed camps shouldn't be included in the list of camps being
		 * actively mentored, though.
		 */
		$count_camp = 'wcpt-closed' !== $camp->post_status;

		if ( array_key_exists( $email, $mentors ) ) {
			if ( $count_camp ) {
				$mentors[ $email ]['camps_mentoring'][] = $camp->post_title;
			}
		} else {
			$mentor_name = get_post_meta( $camp->ID, 'Mentor Name', true );

			$mentors[ $email ] = array(
				'name'            => $mentor_name,
				'camps_mentoring' => $count_camp ? array( $camp->post_title ) : array(),
			);
		}
	}

	return $mentors;
}

/**
 * Count the total number of camps being mentored
 *
 * @param array $mentors
 *
 * @return int
 */
function count_camps_being_mentored( $mentors ) {
	$camps_being_mentored = 0;

	foreach ( $mentors as $mentor ) {
		$camps_being_mentored += count( $mentor['camps_mentoring'] );
	}

	return $camps_being_mentored;
}

/**
 * Get active camps that haven't been assigned a mentor
 *
 * @return array
 */
function get_unmentored_camps() {
	$unmentored_camps = array();

	$post_statuses = array_diff(
		\WordCamp_Loader::get_mentored_post_statuses(),
		array( 'wcpt-closed' )
	);

	$posts = get_posts( array(
		'post_type'      => WCPT_POST_TYPE_ID,
		'post_status'    => $post_statuses,
		'posts_per_page' => 10000,
		'meta_query'     => array(
			array(
				'key'     => 'Mentor E-mail Address',
				'value'   => '',
				'compare' => '='
			),
		),
	) );

	foreach ( $posts as $post ) {
		$unmentored_camps[ $post->ID ] = $post->post_title;
	}

	return $unmentored_camps;
}
