<?php
/**
 * Legacy category/event spotlight, rendered with core blocks and no new query.
 *
 * @package WordPressTV
 */

namespace WordPressTV\Archives;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Matches the legacy spotlight; WordCampTV also previews archives of 1–6 posts.
 *
 * @param \WP_Query $query Archive query.
 * @return bool
 */
function has_spotlight( $query ) {
	return $query instanceof \WP_Query
		&& ! $query->is_feed()
		&& ( $query->is_category() || $query->is_tax( 'event' ) )
		&& (int) $query->get( 'paged' ) < 2
		&& ( (int) $query->found_posts > 6
			|| ( $query->is_category( 'wordcamptv' ) && (int) $query->found_posts > 0 ) );
}

/**
 * Renders marked Post Templates against in-memory slices of the main results.
 *
 * Core cannot partition an inherited Query without querying again or changing
 * pagination. Delegate to the same core blocks with an isolated query clone;
 * the real main query, loop position and pagination totals remain unchanged.
 * Unmarked blocks, feeds, other archives and independent queries are untouched.
 *
 * @param string|null $pre_render   Previously short-circuited output.
 * @param array       $parsed       Parsed block.
 * @param \WP_Block   $parent_block Parent block, when present.
 * @return string|null
 */
function render_partition( $pre_render, $parsed, $parent_block = null ) {
	global $wp_query, $post;
	static $rendering = false;

	if ( null !== $pre_render || ! $wp_query instanceof \WP_Query
		|| $wp_query->is_feed() || is_admin()
		|| ! ( $wp_query->is_category() || $wp_query->is_tax( 'event' ) ) ) {
		return $pre_render;
	}

	$classes   = preg_split( '/\s+/', $parsed['attrs']['className'] ?? '' );
	$spotlight = has_spotlight( $wp_query );
	if ( 'core/group' === $parsed['blockName'] && in_array( 'wptv-taxonomy__spotlight', $classes, true ) && ! $spotlight ) {
		return '';
	}
	if ( 'core/post-terms' === $parsed['blockName'] && in_array( 'wptv-taxonomy__category-events', $classes, true ) && ! $wp_query->is_category() ) {
		return '';
	}
	if ( $rendering || 'core/post-template' !== $parsed['blockName'] || ! $parent_block instanceof \WP_Block
		|| 'core/query' !== $parent_block->name || empty( $parent_block->attributes['query']['inherit'] ) ) {
		return $pre_render;
	}

	$partitions = array(
		'wptv-taxonomy__lead'      => array( 0, 1 ),
		'wptv-taxonomy__secondary' => array( 1, 5 ),
		'wptv-taxonomy__remaining' => array( 6, null ),
	);
	$partition  = array_intersect( array_keys( $partitions ), $classes );
	if ( ! $partition || ! $spotlight ) {
		return $pre_render;
	}

	list( $offset, $length ) = $partitions[ reset( $partition ) ];
	$original_query          = $wp_query;
	$original_post           = $post;
	$isolated                = clone $wp_query;
	// WP::register_globals() binds posts by reference; cloning retains that link.
	unset( $isolated->posts );
	$isolated->posts      = array_slice( $wp_query->posts, $offset, $length );
	$isolated->post_count = count( $isolated->posts );
	$isolated->rewind_posts();
	$isolated->in_the_loop = false;
	$context               = $parent_block->context;
	$context['query']      = array( 'inherit' => true );

	// The globals are swapped only while this block renders and restored in finally.
	// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
	$rendering = true;
	$wp_query  = $isolated;
	try {
		return ( new \WP_Block( $parsed, $context ) )->render();
	} finally {
		$wp_query = $original_query;
		$post     = $original_post;
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited
		$rendering = false;
		if ( $post instanceof \WP_Post ) {
			setup_postdata( $post );
		}
	}
}
add_filter( 'pre_render_block', __NAMESPACE__ . '\\render_partition', 10, 3 );
