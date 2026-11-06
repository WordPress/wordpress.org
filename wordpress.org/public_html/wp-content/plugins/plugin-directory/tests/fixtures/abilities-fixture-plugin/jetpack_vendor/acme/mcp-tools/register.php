<?php
/**
 * A bundled library that registers its own abilities.
 *
 * Seen in: Elementor bundles wordpress/mcp-adapter (vendor/wordpress/mcp-adapter),
 * and Jetpack bundles automattic/jetpack-wp-abilities (jetpack_vendor/). These
 * abilities exist on sites that run the plugin, but they belong to the library,
 * and several plugins may bundle the same one. Whether to list them on the
 * plugin's page is an open question; expected-abilities.json lists them under "vendored".
 *
 * @package abilities-fixture
 */

namespace Acme\MCP_Tools;

defined( 'ABSPATH' ) || exit;

add_action(
	'wp_abilities_api_categories_init',
	function () {
		if ( ! wp_has_ability_category( 'acme-mcp-tools' ) ) {
			wp_register_ability_category(
				'acme-mcp-tools',
				array(
					'label'       => 'Acme MCP Tools',
					'description' => 'Abilities for discovering other abilities.',
				)
			);
		}
	}
);

add_action(
	'wp_abilities_api_init',
	function () {
		if ( wp_has_ability( 'acme-mcp-tools/discover-abilities' ) ) {
			return;
		}

		wp_register_ability(
			'acme-mcp-tools/discover-abilities',
			array(
				'label'               => 'Discover Abilities',
				'description'         => 'Lists the abilities available on this site.',
				'category'            => 'acme-mcp-tools',
				'execute_callback'    => function () {
					return array_keys( wp_get_abilities() );
				},
				'permission_callback' => 'Abilities_Fixture\can_manage',
			)
		);
	}
);
