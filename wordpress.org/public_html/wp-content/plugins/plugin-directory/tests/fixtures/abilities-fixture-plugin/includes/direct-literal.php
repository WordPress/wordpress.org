<?php
/**
 * Pattern: literal arguments at the call site.
 *
 * Seen in: WooCommerce, Elementor and Yoast SEO category registration, and the
 * wordpress/mcp-adapter library that Elementor bundles. This is the only shape a
 * call-site parser like the one used for register_block_type() can read.
 *
 * @package abilities-fixture
 */

namespace Abilities_Fixture;

defined( 'ABSPATH' ) || exit;

add_action(
	'wp_abilities_api_categories_init',
	function () {
		// WooCommerce guards the category, because extensions may register it first.
		if ( ! function_exists( 'wp_has_ability_category' ) || ! wp_has_ability_category( 'abilities-fixture' ) ) {
			wp_register_ability_category(
				'abilities-fixture',
				array(
					'label'       => __( 'Abilities Fixture', 'abilities-fixture' ),
					'description' => __( 'Abilities registered by the fixture plugin.', 'abilities-fixture' ),
				)
			);
		}

		// Yoast SEO and Jetpack use an untranslated product name as the label.
		\wp_register_ability_category(
			'abilities-fixture-tools',
			[
				'label'       => 'Abilities Fixture Tools',
				/* translators: %s: Plugin name. */
				'description' => \sprintf( \__( 'Utility abilities for %s.', 'abilities-fixture' ), 'Abilities Fixture' ),
			]
		);
	}
);

add_action(
	'wp_abilities_api_init',
	function () {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'abilities-fixture/get-site-summary',
			array(
				'label'               => __( 'Get site summary', 'abilities-fixture' ),
				'description'         => __( 'Returns the site title, tagline and number of published posts.', 'abilities-fixture' ),
				'category'            => 'abilities-fixture',
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => function () {
					return array(
						'title'   => get_bloginfo( 'name' ),
						'tagline' => get_bloginfo( 'description' ),
						'posts'   => (int) wp_count_posts()->publish,
					);
				},
				'permission_callback' => __NAMESPACE__ . '\can_manage',
				'meta'                => array(
					'annotations'  => array( 'readonly' => true ),
					'show_in_rest' => true,
				),
			)
		);
	}
);
