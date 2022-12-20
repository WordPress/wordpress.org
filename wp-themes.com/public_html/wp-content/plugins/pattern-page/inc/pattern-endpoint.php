<?php

namespace WordPressdotorg\PatternPreview\PatternEndpoint;

function endpoint_handler() {
	$patterns = \WP_Block_Patterns_Registry::get_instance()->get_all_registered();

	$theme_patterns = array_filter(
		$patterns,
		function ( $pattern ) {
			return ! str_starts_with( $pattern['name'], 'core/' ) && ( ! isset( $pattern['inserter'] ) || false !== $pattern['inserter'] );
		}
	);

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
