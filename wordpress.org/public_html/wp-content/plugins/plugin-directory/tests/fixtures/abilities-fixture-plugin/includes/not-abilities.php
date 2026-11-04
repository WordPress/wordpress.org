<?php
/**
 * Look-alikes a scanner must not report, and one runtime-only ability.
 *
 * @package abilities-fixture
 */

namespace Abilities_Fixture;

defined( 'ABSPATH' ) || exit;

// A REST namespace shaped like an ability name, next to a 'description'.
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'abilities-fixture/v1',
			'/status',
			array(
				'methods'             => 'GET',
				'callback'            => '__return_empty_array',
				'permission_callback' => '__return_true',
				'args'                => array(
					'verbose' => array(
						'description' => 'Not an ability.',
						'type'        => 'boolean',
					),
				),
			)
		);
	}
);

/**
 * Feature detection mentions the function without calling it.
 *
 * @return bool
 */
function abilities_available() {
	return function_exists( 'wp_register_ability' ) && function_exists( 'wp_get_ability' );
}

// A commented-out call is not a registration.
// wp_register_ability( 'abilities-fixture/commented-out', array( 'label' => 'Commented out' ) ); // phpcs:ignore Squiz.PHP.CommentedOutCode.Found -- The scanner must skip it.

/**
 * Looking up an ability by name is not registering it.
 *
 * @return bool
 */
function site_summary_exists() {
	return null !== wp_get_ability( 'abilities-fixture/get-site-summary' );
}

/*
 * Registered at runtime, but its name and label are built from data, so no scanner
 * can read them. It appears in expected-abilities.json under runtime_only, not abilities.
 */
add_action(
	'wp_abilities_api_init',
	function () {
		foreach ( array( 'post', 'page' ) as $post_type ) {
			wp_register_ability(
				sprintf( 'abilities-fixture/count-%ss', $post_type ),
				array(
					/* translators: %s: Post type. */
					'label'               => sprintf( __( 'Count %s items', 'abilities-fixture' ), $post_type ),
					'description'         => __( 'Returns the number of published items of one post type.', 'abilities-fixture' ),
					'category'            => 'abilities-fixture-tools',
					'execute_callback'    => function () use ( $post_type ) {
						return (int) wp_count_posts( $post_type )->publish;
					},
					'permission_callback' => __NAMESPACE__ . '\can_manage',
				)
			);
		}
	}
);
