<?php
/**
 * Plugin Name: WPORG Ratings Mock
 * Description: Mock WPORG_Ratings class for local development. Returns static data.
 */

if ( class_exists( 'WPORG_Ratings' ) ) {
	return;
}

class WPORG_Ratings {

	public static function get_avg_rating( $type, $slug ) {
		return 4.5;
	}

	public static function get_rating_counts( $type, $slug ) {
		return array( 5 => 200, 4 => 50, 3 => 20, 2 => 5, 1 => 10 );
	}

	public static function get_rating_count( $type, $slug, $min_rating = 0 ) {
		return 285;
	}
}
