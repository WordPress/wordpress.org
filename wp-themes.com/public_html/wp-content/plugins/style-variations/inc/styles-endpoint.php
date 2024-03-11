<?php

namespace WordPressdotorg\Theme_Preview\Style_Variations\API_Endpoint;

function endpoint_handler() {
	$variations = \WP_Theme_JSON_Resolver::get_style_variations();
	$theme_slug = get_option( 'stylesheet' );
	$styles     = array();

	// The base theme URL
	$base = "https://wp-themes.com/$theme_slug";

	if ( count( $variations ) > 0 ) {
		/**
		 * Add default even though its not technically a variation, we still want to preview it.
		 */
		$styles[] = array(
			'title'        => __( 'Default' ),
			'link'         => $base,
			'preview_link' => "$base?card_view",
		);

		/**
		 * Add links since the links have internal business logic.
		 */
		foreach ( $variations as $variation ) {
			$title = strtolower( $variation['title'] );
			$link  = add_query_arg( 'style_variation', urlencode( $title ), $base );;

			$styles[] = array(
				'title'        => $title,
				'link'         => $link,
				'preview_link' => "$link&card_view",
			);
		}
	}

	return json_encode( array_values( $styles ) );
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'wporg-styles/v1',
			'/variations',
			array(
				'methods'             => 'GET',
				'callback'            => __NAMESPACE__ . '\endpoint_handler',
				'permission_callback' => '__return_true',
			)
		);
	}
);
