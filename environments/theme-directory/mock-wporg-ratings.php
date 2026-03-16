<?php
/**
 * Plugin Name: WordPress.org Ratings System (Stub)
 * Description: Stub for the wporg-ratings plugin for local development.
 */

wp_cache_add_global_groups( 'wporg-ratings' );

class WPORG_Ratings {

	const CACHE_GROUP = 'wporg-ratings';
	const CACHE_TIME = HOUR_IN_SECONDS;
	const REVIEWS_FORUM = 21272;

	public static function get_post_rating( int $post_id = 0 ): int {
		return 0;
	}

	public static function get_user_rating( $object_type, $object_slug, $user_id ): int {
		return 0;
	}

	public static function get_avg_rating( $object_type, $object_slug ): float {
		return 0.0;
	}

	public static function get_rating_count( $object_type, $object_slug ): int {
		return 0;
	}

	public static function get_rating_counts( $object_type, $object_slug ): array {
		return array( 0, 0, 0, 0, 0 );
	}
}
