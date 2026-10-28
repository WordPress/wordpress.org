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

	$date_of_passing_field_id = 483;
	$memorial_name_field_id   = 484;

	$query = $wpdb->prepare(
		'
		SELECT field_id, value, user_id
		FROM `bpmain_bp_xprofile_data`
		WHERE field_id IN ( %d, %d )
		AND user_id IN (
			SELECT user_id
			FROM bpmain_bp_xprofile_data
			WHERE field_id = 476 AND value = "Yes"
		)
		ORDER BY user_id
		',
		$date_of_passing_field_id,
		$memorial_name_field_id
	);

	// Execute the prepared statement
	$profile_data = $wpdb->get_results( $query, ARRAY_A );

	$user_ids = wp_list_pluck( $profile_data, 'user_id' );

	// Grabs user display_name, user_nicename, ID for profile link.
	$users = get_users(
		array(
			'blog_id' => 0,
			'include' => $user_ids,
			'fields'  => array(
				'ID',
				'user_nicename',
			),
		)
	);

	// Add meta each user in the result
	foreach ( $users as &$user ) {
		foreach ( $profile_data as $profile ) {
			if ( $profile['user_id'] === $user->ID ) {

				if ( $date_of_passing_field_id === (int) $profile['field_id'] ) {
					$user->date_passing = $profile['value'];
				}

				if ( $memorial_name_field_id === (int) $profile['field_id'] ) {
					$user->display_name = $profile['value'];
				}
			}
		}
	}

	// Sort based on date of passing
	usort(
		$users,
		function ( $a, $b ) {
			$timestampA = strtotime( $a->date_passing );
			$timestampB = strtotime( $b->date_passing );

			if ( $timestampA === $timestampB ) {
				return 0;
			}

			// Use the less than operator for ascending order, or greater than for descending order
			return ( $timestampA < $timestampB ) ? -1 : 1;
		}
	);

	return $users;
}
