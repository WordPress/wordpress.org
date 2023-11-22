<?php
namespace WordPressdotorg\MemorialProfiles;

/**
 * This is used to provide user data on `/remembers`.
 */

/**
 * Returns a list of memorialized profiles.
 */
function get_profiles() {
	global $wpdb;

	$results = $wpdb->get_results( 'SELECT user_id FROM bpmain_bp_xprofile_data WHERE field_id = "476" AND value = "Yes"', ARRAY_A );

	$user_ids = wp_list_pluck( $results, 'user_id' );

	// Grabs user display_name and user_nicename for profile link.
	return get_users(
		array(
			'blog_id' => 0,
			'include' => $user_ids,
			'fields'  => array(
				'display_name',
				'user_nicename',
			),
		)
	);
}
