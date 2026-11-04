<?php
/**
 * Pattern: categories registered from a loop over a literal array, and a category
 * held in a local variable.
 *
 * Seen in: All in One SEO (app/Common/Abilities/Abilities.php) registers its
 * categories with foreach ( $categories as $slug => $label ). WordPress core
 * (wp-includes/abilities.php) sets $category_site = 'site' and then uses
 * 'category' => $category_site.
 *
 * @package abilities-fixture
 */

namespace Abilities_Fixture;

defined( 'ABSPATH' ) || exit;

add_action(
	'wp_abilities_api_categories_init',
	function () {
		$categories = array(
			'abilities-fixture-site'    => __( 'Abilities Fixture Site', 'abilities-fixture' ),
			'abilities-fixture-content' => __( 'Abilities Fixture Content', 'abilities-fixture' ),
		);

		foreach ( $categories as $slug => $label ) {
			wp_register_ability_category(
				$slug,
				array(
					'label'       => $label,
					/* translators: %s: Category label. */
					'description' => sprintf( __( 'Abilities in the %s group.', 'abilities-fixture' ), $label ),
				)
			);
		}
	}
);

add_action(
	'wp_abilities_api_init',
	function () {
		$category_site = 'abilities-fixture-site';

		wp_register_ability(
			'abilities-fixture/get-environment',
			array(
				'label'               => __( 'Get environment', 'abilities-fixture' ),
				'description'         => __( 'Returns the WordPress and PHP versions.', 'abilities-fixture' ),
				'category'            => $category_site,
				'execute_callback'    => function () {
					return array(
						'wordpress' => get_bloginfo( 'version' ),
						'php'       => PHP_VERSION,
					);
				},
				'permission_callback' => __NAMESPACE__ . '\can_manage',
				// Core puts "public" at the top of meta, with no "mcp" key.
				'meta'                => array( 'public' => true ),
			)
		);
	}
);
