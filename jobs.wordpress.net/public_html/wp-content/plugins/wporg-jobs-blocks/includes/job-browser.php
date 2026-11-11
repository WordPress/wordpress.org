<?php
/**
 * Category filtering for the homepage job browser, with the Interactivity API on core blocks.
 *
 * Opt-ins: a core/query with the `wporg-jobs/job-browser` namespace, a core/term-template with the `pills` style and a core/paragraph with the `filter-empty` style.
 *
 * @package wporg-jobs-blocks
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

const WPORG_JOBS_BLOCKS_BROWSER_NS = 'wporg-jobs/job-browser';

/**
 * Registers the view script module and the empty-state paragraph style.
 */
function wporg_jobs_blocks_browser_register(): void {
	wp_register_script_module(
		'wporg-jobs-blocks/job-browser',
		WPORG_JOBS_BLOCKS_URL . 'assets/js/job-browser.js',
		array( '@wordpress/interactivity' ),
		WPORG_JOBS_BLOCKS_VERSION
	);

	register_block_style(
		'core/paragraph',
		array(
			'name'  => 'filter-empty',
			'label' => __( 'Job filter empty state', 'wporg-jobs-blocks' ),
		)
	);
}
add_action( 'init', 'wporg_jobs_blocks_browser_register' );

/**
 * Seeds the store on the server, with derived state for the first paint.
 *
 * @param array|null $counts Jobs per category slug, when known.
 */
function wporg_jobs_blocks_browser_state( ?array $counts = null ): void {
	$state = array(
		'active'    => 'all',
		'current'   => static function (): string|false {
			$context = wp_interactivity_get_context();
			return isset( $context['slug'] ) && 'all' === $context['slug'] ? 'true' : false;
		},
		'isVisible' => true,
		'isEmpty'   => false,
	);
	if ( null !== $counts ) {
		$state['counts'] = (object) $counts;
		$state['totals'] = (object) wporg_jobs_blocks_snapshot()['counts'];
	}
	wp_interactivity_state( WPORG_JOBS_BLOCKS_BROWSER_NS, $state );
}

/**
 * Whether a rendered block carries a class.
 *
 * @param array  $block Parsed block.
 * @param string $class_name Class.
 * @return bool
 */
function wporg_jobs_blocks_has_class( array $block, string $class_name ): bool {
	$classes = isset( $block['attrs']['className'] ) && is_string( $block['attrs']['className'] ) ? $block['attrs']['className'] : '';
	return in_array( $class_name, preg_split( '/\s+/', $classes ), true );
}

/**
 * Flags that a job-browser query is rendering.
 *
 * @param string|null $pre_render Short-circuit value.
 * @param array       $block      Parsed block.
 * @return string|null
 */
function wporg_jobs_blocks_browser_enter( ?string $pre_render, array $block ): ?string {
	if ( 'core/query' === $block['blockName'] && isset( $block['attrs']['namespace'] ) && WPORG_JOBS_BLOCKS_BROWSER_NS === $block['attrs']['namespace'] ) {
		$GLOBALS['wporg_jobs_blocks_in_browser'] = true;
	}
	return $pre_render;
}
add_filter( 'pre_render_block', 'wporg_jobs_blocks_browser_enter', 10, 2 );

/**
 * Leaves the job-browser query.
 *
 * @param string $block_content Rendered query.
 * @param array  $block         Parsed block.
 * @return string
 */
function wporg_jobs_blocks_browser_leave( string $block_content, array $block ): string {
	if ( isset( $block['attrs']['namespace'] ) && WPORG_JOBS_BLOCKS_BROWSER_NS === $block['attrs']['namespace'] ) {
		$GLOBALS['wporg_jobs_blocks_in_browser'] = false;
	}
	return $block_content;
}
add_filter( 'render_block_core/query', 'wporg_jobs_blocks_browser_leave', 10, 2 );

/**
 * Makes each job in the browser's post-template filterable.
 *
 * @param string $block_content Rendered post-template.
 * @return string
 */
function wporg_jobs_blocks_browser_items( string $block_content ): string {
	if ( empty( $GLOBALS['wporg_jobs_blocks_in_browser'] ) ) {
		return $block_content;
	}

	$counts = array();
	$tags   = new WP_HTML_Tag_Processor( $block_content );
	if ( ! $tags->next_tag( 'ul' ) ) {
		return $block_content;
	}
	$tags->set_attribute( 'data-wp-interactive', WPORG_JOBS_BLOCKS_BROWSER_NS );

	while ( $tags->next_tag( 'li' ) ) {
		$cats = array();
		foreach ( iterator_to_array( $tags->class_list(), false ) as $class ) {
			if ( 0 === strpos( $class, 'job_category-' ) ) {
				$slug            = substr( $class, strlen( 'job_category-' ) );
				$cats[]          = $slug;
				$counts[ $slug ] = isset( $counts[ $slug ] ) ? $counts[ $slug ] + 1 : 1;
			}
		}
		if ( ! $cats ) {
			continue;
		}
		$tags->set_attribute( 'data-wp-context', wp_json_encode( array( 'cats' => $cats ) ) );
		$tags->set_attribute( 'data-wp-bind--hidden', '!state.isVisible' );
	}

	wporg_jobs_blocks_browser_state( $counts );
	wp_enqueue_script_module( 'wporg-jobs-blocks/job-browser' );

	return $tags->get_updated_html();
}
add_filter( 'render_block_core/post-template', 'wporg_jobs_blocks_browser_items' );

/**
 * Turns the pills-styled term template into category filters.
 *
 * @param string $block_content Rendered term template.
 * @param array  $block         Parsed block.
 * @return string
 */
function wporg_jobs_blocks_browser_pills( string $block_content, array $block ): string {
	if ( ! wporg_jobs_blocks_has_class( $block, 'is-style-pills' ) ) {
		return $block_content;
	}

	$all           = sprintf(
		'<li class="wp-block-term wporg-jobs-filter-all"><a href="%1$s" data-wp-on--click="actions.select" data-wp-context="%2$s" data-wp-bind--aria-current="state.current">%3$s</a></li>',
		esc_url( home_url( '/#jobs' ) ),
		esc_attr( wp_json_encode( array( 'slug' => 'all' ) ) ),
		esc_html__( 'All', 'wporg-jobs-blocks' )
	);
	$block_content = (string) preg_replace_callback(
		'#<ul\b[^>]*>#',
		static function ( array $matches ) use ( $all ): string {
			return $matches[0] . $all;
		},
		$block_content,
		1
	);

	$tags = new WP_HTML_Tag_Processor( $block_content );
	if ( ! $tags->next_tag( 'ul' ) ) {
		return $block_content;
	}
	$tags->set_attribute( 'data-wp-interactive', WPORG_JOBS_BLOCKS_BROWSER_NS );
	wporg_jobs_blocks_browser_state();

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

	wp_enqueue_script_module( 'wporg-jobs-blocks/job-browser' );

	return $tags->get_updated_html();
}
add_filter( 'render_block_core/term-template', 'wporg_jobs_blocks_browser_pills', 10, 2 );

/**
 * Hides the empty-state paragraph until the selected category has no jobs.
 *
 * @param string $block_content Rendered paragraph.
 * @param array  $block         Parsed block.
 * @return string
 */
function wporg_jobs_blocks_browser_empty( string $block_content, array $block ): string {
	if ( ! wporg_jobs_blocks_has_class( $block, 'is-style-filter-empty' ) ) {
		return $block_content;
	}

	$tags = new WP_HTML_Tag_Processor( $block_content );
	if ( $tags->next_tag() ) {
		$tags->set_attribute( 'data-wp-interactive', WPORG_JOBS_BLOCKS_BROWSER_NS );
		$tags->set_attribute( 'data-wp-bind--hidden', '!state.isEmpty' );
		$tags->set_attribute( 'hidden', true );
	}

	return $tags->get_updated_html();
}
add_filter( 'render_block_core/paragraph', 'wporg_jobs_blocks_browser_empty', 10, 2 );
