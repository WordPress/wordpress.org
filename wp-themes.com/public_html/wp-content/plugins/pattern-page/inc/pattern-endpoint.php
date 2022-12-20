<?php

namespace WordPressdotorg\PatternPreview\PatternEndpoint;

/**
 * Recurses the blocks and returns block names.
 *
 * @param WP_Block $block
 * @param array    $names
 * @return array List of block names.
 */
function get_all_block_names( $block, $names = array() ) {
	// HTML blocks can have a null block name
	// It won't have any innerBlocks.
	if ( empty( $block['blockName'] ) ) {
		return $names;
	}

	$names[] = $block['blockName'];

	// Blocks have inner blocks that we need to recurse through
	foreach ( $block['innerBlocks'] as $inner_block ) {
		return get_all_block_names( $inner_block, $names );
	}

	return $names;
}

/**
 * Return whether the block or its innerBlocks contains a non core block.
 *
 * @param WP_Block $block
 * @return boolean
 */
function has_unsupported_block( $block ) {
	$block_names = get_all_block_names( $block );

	// We only want "core" blocks.
	$unsupported = array_filter(
		$block_names,
		function ( $block_name ) {
			return ! str_starts_with( $block_name, 'core' );
		}
	);

	return ! empty( $unsupported );
}

/**
 * Returns patterns that only contain core blocks.
 *
 * @param array[] $patterns List of block patterns
 * @return array[]
 */
function get_supported_patterns( $patterns ) {
	$supported_patterns = array();

	foreach ( $patterns as $pattern ) {
		$blocks       = parse_blocks( $pattern['content'] );
		$is_supported = true;

		// Filter out any core patterns
		if ( str_starts_with( $pattern['name'], 'core/' ) ) {
			$is_supported = false;
		}

		// Filter out any "no inserter" patterns
		if ( isset( $pattern['inserter'] ) && false === $pattern['inserter'] ) {
			$is_supported = false;
		}

		// Filter out patterns with unsupported blocks
		foreach ( $blocks as $block ) {
			if ( has_unsupported_block( $block ) ) {
				$is_supported = false;
			}
		}

		if ( $is_supported ) {
			$supported_patterns[] = $pattern;
		}
	}

	return $supported_patterns;
}

function endpoint_handler() {
	$patterns = \WP_Block_Patterns_Registry::get_instance()->get_all_registered();
	$theme_patterns = get_supported_patterns( $patterns );

	/**
	 * Add links since the links have internal business logic.
	 */
	foreach ( $theme_patterns as &$pattern ) {
		$name       = $pattern['name'];
		$theme_slug = get_option( 'stylesheet' );
		$link       = "https://wp-themes.com/$theme_slug/?page_id=9999&pattern_name=$name";

		$pattern['link']         = $link;
		$pattern['preview_link'] = "$link&preview";
	}

	return json_encode( array_values( $theme_patterns ) );
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'wporg-patterns/v1',
			'/patterns',
			array(
				'methods'             => 'GET',
				'callback'            => __NAMESPACE__ . '\endpoint_handler',
				'permission_callback' => '__return_true',
			)
		);
	}
);
