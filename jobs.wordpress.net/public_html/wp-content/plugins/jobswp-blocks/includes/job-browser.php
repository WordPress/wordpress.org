<?php
/**
 * Client-side category filtering for the homepage job browser, built with
 * the Interactivity API on core blocks rather than a custom block.
 *
 * Opt-ins, all visible in the editor:
 * - a core/query whose `namespace` is `jobswp/job-browser` (its post-template
 *   items become filterable by their job_category-<slug> classes);
 * - a core/term-template with the theme's `pills` style (each term link
 *   becomes a filter; an "All" link is prepended);
 * - a core/paragraph with the `filter-empty` style (shown only when the
 *   selected category has no jobs).
 *
 * Without JavaScript the pills stay links to the category archives.
 *
 * @package jobswp-blocks
 */

defined( 'ABSPATH' ) || exit;

const JOBSWP_BLOCKS_BROWSER_NS = 'jobswp/job-browser';

/**
 * Registers the view script module and the empty-state paragraph style.
 */
function jobswp_blocks_browser_register() {
	wp_register_script_module(
		'jobswp-blocks/job-browser',
		JOBSWP_BLOCKS_URL . 'assets/js/job-browser.js',
		array( '@wordpress/interactivity' ),
		JOBSWP_BLOCKS_VERSION
	);

	register_block_style(
		'core/paragraph',
		array(
			'name'  => 'filter-empty',
			'label' => __( 'Job filter empty state', 'jobswp-blocks' ),
		)
	);
}
add_action( 'init', 'jobswp_blocks_browser_register' );

/**
 * Seeds the store on the server, with derived state for the first paint.
 *
 * @param array $counts Jobs per category slug, when known.
 */
function jobswp_blocks_browser_state( $counts = null ) {
	$state = array(
		'active'    => 'all',
		'current'   => static function () {
			$context = wp_interactivity_get_context();
			return isset( $context['slug'] ) && 'all' === $context['slug'] ? 'true' : false;
		},
		'isVisible' => true,
		'isEmpty'   => false,
	);
	if ( null !== $counts ) {
		$state['counts'] = (object) $counts;
	}
	wp_interactivity_state( JOBSWP_BLOCKS_BROWSER_NS, $state );
}

/**
 * Whether a rendered block carries a class.
 *
 * @param array  $block Parsed block.
 * @param string $class Class.
 * @return bool
 */
function jobswp_blocks_has_class( $block, $class ) {
	$classes = isset( $block['attrs']['className'] ) && is_string( $block['attrs']['className'] ) ? $block['attrs']['className'] : '';
	return in_array( $class, preg_split( '/\s+/', $classes ), true );
}

/**
 * Tracks whether a job-browser query is rendering, so its post-template
 * can be told apart from other loops.
 *
 * @param string|null $pre_render Short-circuit value.
 * @param array       $block      Parsed block.
 * @return string|null
 */
function jobswp_blocks_browser_enter( $pre_render, $block ) {
	if ( 'core/query' === $block['blockName'] && isset( $block['attrs']['namespace'] ) && JOBSWP_BLOCKS_BROWSER_NS === $block['attrs']['namespace'] ) {
		$GLOBALS['jobswp_blocks_in_browser'] = true;
	}
	return $pre_render;
}
add_filter( 'pre_render_block', 'jobswp_blocks_browser_enter', 10, 2 );

/**
 * Leaves the job-browser query.
 *
 * @param string $block_content Rendered query.
 * @param array  $block         Parsed block.
 * @return string
 */
function jobswp_blocks_browser_leave( $block_content, $block ) {
	if ( isset( $block['attrs']['namespace'] ) && JOBSWP_BLOCKS_BROWSER_NS === $block['attrs']['namespace'] ) {
		$GLOBALS['jobswp_blocks_in_browser'] = false;
	}
	return $block_content;
}
add_filter( 'render_block_core/query', 'jobswp_blocks_browser_leave', 10, 2 );

/**
 * Makes each job in the browser's post-template filterable.
 *
 * @param string $block_content Rendered post-template.
 * @return string
 */
function jobswp_blocks_browser_items( $block_content ) {
	if ( empty( $GLOBALS['jobswp_blocks_in_browser'] ) ) {
		return $block_content;
	}

	$counts = array();
	$tags   = new WP_HTML_Tag_Processor( $block_content );
	if ( ! $tags->next_tag( 'ul' ) ) {
		return $block_content;
	}
	$tags->set_attribute( 'data-wp-interactive', JOBSWP_BLOCKS_BROWSER_NS );

	while ( $tags->next_tag( 'li' ) ) {
		$cats = array();
		foreach ( iterator_to_array( $tags->class_list(), false ) as $class ) {
			if ( 0 === strpos( $class, 'job_category-' ) ) {
				$slug           = substr( $class, strlen( 'job_category-' ) );
				$cats[]         = $slug;
				$counts[ $slug ] = isset( $counts[ $slug ] ) ? $counts[ $slug ] + 1 : 1;
			}
		}
		if ( ! $cats ) {
			continue;
		}
		$tags->set_attribute( 'data-wp-context', wp_json_encode( array( 'cats' => $cats ) ) );
		$tags->set_attribute( 'data-wp-bind--hidden', '!state.isVisible' );
	}

	jobswp_blocks_browser_state( $counts );
	wp_enqueue_script_module( 'jobswp-blocks/job-browser' );

	return $tags->get_updated_html();
}
add_filter( 'render_block_core/post-template', 'jobswp_blocks_browser_items' );

/**
 * Turns the pills-styled term template into category filters.
 *
 * @param string $block_content Rendered term template.
 * @param array  $block         Parsed block.
 * @return string
 */
function jobswp_blocks_browser_pills( $block_content, $block ) {
	if ( ! jobswp_blocks_has_class( $block, 'is-style-pills' ) ) {
		return $block_content;
	}

	$all = sprintf(
		'<li class="wp-block-term jobswp-filter-all"><a href="%1$s" data-wp-on--click="actions.select" data-wp-context="%2$s" data-wp-bind--aria-current="state.current">%3$s</a></li>',
		esc_url( home_url( '/#jobs' ) ),
		esc_attr( wp_json_encode( array( 'slug' => 'all' ) ) ),
		esc_html__( 'All', 'jobswp-blocks' )
	);
	$block_content = (string) preg_replace( '#(<ul\b[^>]*>)#', '$1' . $all, $block_content, 1 );

	$tags = new WP_HTML_Tag_Processor( $block_content );
	if ( ! $tags->next_tag( 'ul' ) ) {
		return $block_content;
	}
	$tags->set_attribute( 'data-wp-interactive', JOBSWP_BLOCKS_BROWSER_NS );
	jobswp_blocks_browser_state();

	while ( $tags->next_tag( 'a' ) ) {
		if ( null !== $tags->get_attribute( 'data-wp-context' ) ) {
			continue;
		}
		$href = (string) $tags->get_attribute( 'href' );
		$path = trim( (string) wp_parse_url( $href, PHP_URL_PATH ), '/' );
		$slug = sanitize_title( basename( $path ) );
		if ( '' === $slug ) {
			continue;
		}
		$tags->set_attribute( 'data-wp-on--click', 'actions.select' );
		$tags->set_attribute( 'data-wp-context', wp_json_encode( array( 'slug' => $slug ) ) );
		$tags->set_attribute( 'data-wp-bind--aria-current', 'state.current' );
	}

	wp_enqueue_script_module( 'jobswp-blocks/job-browser' );

	return $tags->get_updated_html();
}
add_filter( 'render_block_core/term-template', 'jobswp_blocks_browser_pills', 10, 2 );

/**
 * Hides the empty-state paragraph until the selected category has no jobs.
 *
 * @param string $block_content Rendered paragraph.
 * @param array  $block         Parsed block.
 * @return string
 */
function jobswp_blocks_browser_empty( $block_content, $block ) {
	if ( ! jobswp_blocks_has_class( $block, 'is-style-filter-empty' ) ) {
		return $block_content;
	}

	$tags = new WP_HTML_Tag_Processor( $block_content );
	if ( $tags->next_tag() ) {
		$tags->set_attribute( 'data-wp-interactive', JOBSWP_BLOCKS_BROWSER_NS );
		$tags->set_attribute( 'data-wp-bind--hidden', '!state.isEmpty' );
		$tags->set_attribute( 'hidden', true );
	}

	return $tags->get_updated_html();
}
add_filter( 'render_block_core/paragraph', 'jobswp_blocks_browser_empty', 10, 2 );
