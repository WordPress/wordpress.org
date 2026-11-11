<?php
/**
 * Main query adjustments.
 *
 * Route contract (docs/routes.md): posts_per_page decides which /page/N/ URLs
 * exist, so these values must match wptv2 exactly in Phase 1.
 *
 * @package WordPressTV
 */

namespace WordPressTV\Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sets posts_per_page per view on the main query. Runs on pre_get_posts.
 *
 * Templates must use inherited Query Loops (`"inherit": true`) so they show
 * exactly these posts. A query that already sets posts_per_page is left alone.
 *
 * @param \WP_Query $query The query.
 */
function posts_per_page( $query ) {
	if ( ! $query->is_main_query() || ! empty( $query->get( 'posts_per_page' ) ) ) {
		return;
	}

	$queried_object = $query->get_queried_object();

	if ( $query->is_front_page() ) {
		$per_page = 8;
	} elseif ( $query->is_category ) {
		$per_page = 22;
	} elseif ( $query->is_tax && 'event' === ( $queried_object->taxonomy ?? null ) ) {
		$per_page = 22;
	} elseif ( $query->is_archive || $query->is_search ) {
		$per_page = 10;
	} else {
		$per_page = 22;
	}

	$query->set( 'posts_per_page', $per_page );
}
add_action( 'pre_get_posts', __NAMESPACE__ . '\posts_per_page' );
