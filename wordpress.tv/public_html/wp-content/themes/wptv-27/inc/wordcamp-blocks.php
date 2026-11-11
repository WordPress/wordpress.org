<?php
/**
 * Automatic WordCampTV sections composed from editable core-block prototypes.
 *
 * Core Query cannot aggregate a category's events by serialized recorded dates
 * or extract a year from event names. Only that data selection is custom PHP;
 * cards, headings, term lists and independent queries remain native blocks.
 *
 * @package WordPressTV
 */

namespace WordPressTV\WordCamps;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hidden core-block prototype, registered by WordPress from patterns/.
 *
 * @param string $name Pattern name, without the wptv/ namespace.
 * @return string
 */
function prototype( $name ) {
	$pattern = \WP_Block_Patterns_Registry::get_instance()->get_registered( 'wptv/' . $name );
	return $pattern['content'] ?? '';
}

/**
 * Match flagship event names, with or without their edition year.
 *
 * @param string $name Event name.
 * @return bool
 */
function is_flagship( $name ) {
	return (bool) preg_match( '/^WordCamp\s+(?:Europe|US|Asia|India)(?:\s+[0-9]{4})?$/iu', trim( $name ) );
}

/**
 * Stable partition: keep the recorded-date order within both groups.
 *
 * @param array $events Recorded dates keyed by event term ID.
 * @return array
 */
function prioritize_flagships( $events ) {
	$flagships = array();
	$others    = array();
	foreach ( $events as $id => $date ) {
		$term = get_term( $id, 'event' );
		if ( $term instanceof \WP_Term && is_flagship( $term->name ) ) {
			$flagships[ $id ] = $date;
		} else {
			$others[ $id ] = $date;
		}
	}
	return $flagships + $others;
}

/**
 * Four publication-date-ordered category videos per recent event, not cached HTML.
 *
 * @param array|null $events Recorded dates keyed by event term ID; null reads the index.
 * @return string
 */
function recent_markup( $events = null ) {
	$markup = '';
	foreach ( prioritize_flagships( $events ?? recent() ) as $id => $date ) {
		$term = get_term( $id, 'event' );
		if ( ! $term instanceof \WP_Term || ! is_wordcamp( $term->name ) ) {
			continue;
		}
		$url = get_term_link( $term );
		if ( is_wp_error( $url ) ) {
			continue;
		}
		$markup .= strtr(
			prototype( 'wordcamp-event' ),
			array(
				'WPTV_EVENT_NAME' => esc_html( $term->name ),
				'/#wptv-event'    => esc_url( $url ),
				'"wptvEvent":0'   => '"wptvEvent":' . (int) $id,
			)
		);
	}
	return $markup;
}

/**
 * Native Terms Queries restrict each year to its derived category-associated IDs.
 *
 * @param array|null $groups Event term IDs keyed by year; null reads the index.
 * @return string
 */
function directory_markup( $groups = null ) {
	$markup = '';
	foreach ( ( $groups ?? directory() ) as $year => $ids ) {
		$ids = wp_parse_id_list( $ids );
		if ( ! $ids || $year < 2000 || $year > 2099 ) {
			continue;
		}
		$markup .= strtr(
			prototype( 'wordcamp-year' ),
			array(
				'WPTV_YEAR'     => (string) (int) $year,
				'"include":[0]' => '"include":' . wp_json_encode( $ids ),
			)
		);
	}
	return $markup;
}

/**
 * Populate marked core Groups only on the first WordCampTV category page.
 * Empty indexes retain the pattern's editable empty-state paragraph. Rendering
 * a replacement core Group is guarded and never changes main-query globals.
 * The landing shows only the native 1 + 5 spotlight, never the 22-video grid.
 * Existing paginated URLs keep their inherited grid without these extras.
 *
 * @param string|null $pre_render Short-circuited block output, or null.
 * @param array       $parsed     Parsed block.
 * @return string|null
 */
function render_sections( $pre_render, $parsed ) {
	static $rendering = false;
	if ( null !== $pre_render || $rendering || is_admin() || is_feed()
		|| ! is_category( 'wordcamptv' ) ) {
		return $pre_render;
	}
	$classes = preg_split( '/\s+/', $parsed['attrs']['className'] ?? '' );
	if ( 'core/query' === $parsed['blockName'] && ! empty( $parsed['attrs']['query']['inherit'] )
		&& in_array( 'wptv-listing__query', $classes, true ) ) {
		return (int) get_query_var( 'paged' ) < 2 ? '' : $pre_render;
	}
	if ( 'core/group' !== $parsed['blockName'] ) {
		return $pre_render;
	}
	$markers = array( 'wptv-wordcamps__recent', 'wptv-wordcamps__directory', 'wptv-wordcamps__recent-content', 'wptv-wordcamps__directory-content' );
	if ( ! array_intersect( $markers, $classes ) ) {
		return $pre_render;
	}
	if ( (int) get_query_var( 'paged' ) > 1 ) {
		return '';
	}
	if ( in_array( 'wptv-wordcamps__recent-content', $classes, true ) ) {
		$markup = recent_markup();
	} elseif ( in_array( 'wptv-wordcamps__directory-content', $classes, true ) ) {
		$markup = directory_markup();
	} else {
		return $pre_render;
	}
	if ( '' === $markup ) {
		return $pre_render;
	}
	$children               = parse_blocks( $markup );
	$open                   = reset( $parsed['innerContent'] );
	$close                  = end( $parsed['innerContent'] );
	$parsed['innerBlocks']  = $children;
	$parsed['innerContent'] = array_merge( array( $open ), array_fill( 0, count( $children ), null ), array( $close ) );
	$rendering              = true;
	try {
		return ( new \WP_Block( $parsed ) )->render();
	} finally {
		$rendering = false;
	}
}
add_filter( 'pre_render_block', __NAMESPACE__ . '\\render_sections', 10, 2 );
