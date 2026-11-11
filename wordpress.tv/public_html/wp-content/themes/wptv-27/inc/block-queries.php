<?php
/**
 * Slug-based taxonomy constraints for theme-owned Query blocks.
 *
 * @package WordPressTV
 */

namespace WordPressTV\BlockQueries;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves the theme's named queries without persisting environment-specific IDs.
 *
 * The inherited main query is deliberately excluded. Missing terms must return
 * no posts rather than silently broadening a featured/category query.
 *
 * @param array     $query Query arguments.
 * @param \WP_Block $block Post Template block, including its Query context.
 * @return array
 */
function resolve_terms( $query, $block ) {
	$context = $block->context['query'] ?? array();
	if ( ! empty( $context['inherit'] ) ) {
		return $query;
	}

	$constraints = array(
		'wptv/featured'       => array( 'post_tag' => 'featured' ),
		'wptv/home-lead'      => array( 'post_tag' => 'featured' ),
		'wptv/wordcamp'       => array( 'category' => 'wordcamptv' ),
		'wptv/wordcamp-event' => array( 'category' => 'wordcamptv' ),
	);
	// Core provides the query object as context, but not the namespace attribute.
	$namespace = $context['wptvQuery'] ?? '';
	if ( ! isset( $constraints[ $namespace ] ) ) {
		return $query;
	}

	$tax_query = array( 'relation' => 'AND' );
	foreach ( $constraints[ $namespace ] as $taxonomy => $slug ) {
		$term = get_term_by( 'slug', $slug, $taxonomy );
		if ( ! $term instanceof \WP_Term ) {
			$query['post__in'] = array( 0 );
			return $query;
		}
		$tax_query[] = array(
			'taxonomy' => $taxonomy,
			'field'    => 'term_id',
			'terms'    => array( $term->term_id ),
		);
	}
	// Match wptv2's front-page lead: newest featured post, restricted to stickies
	// when configured. The WordCampTV category belongs to a different listing.
	if ( 'wptv/home-lead' === $namespace ) {
		$sticky = get_option( 'sticky_posts', array() );
		if ( $sticky ) {
			$query['post__in'] = wp_parse_id_list( $sticky );
		}
	}
	// Independent landing-page cards must match BOTH the category and this event.
	// Never fall back to all category videos when the prototype has no event ID.
	if ( 'wptv/wordcamp-event' === $namespace ) {
		$event_id = absint( $context['wptvEvent'] ?? 0 );
		if ( ! $event_id ) {
			$query['post__in'] = array( 0 );
			return $query;
		}
		$tax_query[]                  = array(
			'taxonomy' => 'event',
			'field'    => 'term_id',
			'terms'    => array( $event_id ),
		);
		$query['posts_per_page']      = 4;
		$query['orderby']             = array(
			'date' => 'DESC',
			'ID'   => 'DESC',
		);
		$query['ignore_sticky_posts'] = true;
	}
	// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Taxonomy filter the block needs; part of the documented query.
	$query['tax_query'] = ! empty( $query['tax_query'] )
		? array(
			'relation' => 'AND',
			$query['tax_query'],
			$tax_query,
		)
		: $tax_query;
	return $query;
}
add_filter( 'query_loop_block_query_vars', __NAMESPACE__ . '\resolve_terms', 10, 2 );

/**
 * Excludes structural categories only while core renders the homepage cards.
 *
 * Core Term Template does not forward an exclude argument from termQuery.
 * Delegate to its registered renderer with a temporary, slug-resolved filter.
 *
 * @param array  $args Block registration arguments.
 * @param string $name Block name.
 * @return array
 */
function register_category_card_renderer( $args, $name ) {
	if ( 'core/term-template' !== $name || ! is_callable( $args['render_callback'] ?? null ) ) {
		return $args;
	}

	$render                  = $args['render_callback'];
	$args['render_callback'] = static function ( $attributes, $content, $block ) use ( $render ) {
		$classes = preg_split( '/\s+/', $attributes['className'] ?? '' );
		$query   = $block->context['termQuery'] ?? array();
		if ( ! in_array( 'wptv-home__categories-grid', $classes, true )
			|| 'category' !== ( $query['taxonomy'] ?? '' ) || ! empty( $query['inherit'] ) ) {
			return call_user_func( $render, $attributes, $content, $block );
		}

		$excluded = array();
		foreach ( array( 'year', 'location' ) as $slug ) {
			$term = get_term_by( 'slug', $slug, 'category' );
			if ( $term instanceof \WP_Term ) {
				$excluded[] = $term->term_id;
			}
		}
		$exclude = static function ( $term_args, $taxonomies ) use ( $excluded ) {
			if ( array( 'category' ) === (array) $taxonomies ) {
				$term_args['exclude'] = array_unique(
					array_merge(
						wp_parse_id_list( $term_args['exclude'] ?? array() ),
						$excluded
					)
				);
			}
			return $term_args;
		};

		add_filter( 'get_terms_args', $exclude, 10, 2 );
		try {
			return call_user_func( $render, $attributes, $content, $block );
		} finally {
			remove_filter( 'get_terms_args', $exclude, 10 );
		}
	};
	return $args;
}
add_filter( 'register_block_type_args', __NAMESPACE__ . '\register_category_card_renderer', 10, 2 );
