<?php
/**
 * Pattern: untranslated labels, a namespace that doesn't match the plugin's slug, a
 * generic category shared with other plugins, and "label" keys inside the schema.
 *
 * Seen in: Ninja Forms (includes/Abilities/). Its abilities are named ninjaforms/*
 * while the plugin's slug is ninja-forms, its category is plain "forms", and its input
 * schema describes form fields that have their own "label" property. A scanner that
 * takes the first 'label' => it finds can pick up the wrong one.
 *
 * @package abilities-fixture
 */

namespace Abilities_Fixture;

defined( 'ABSPATH' ) || exit;

add_action(
	'wp_abilities_api_categories_init',
	function () {
		// Another plugin may already own a category this generic.
		if ( ! wp_has_ability_category( 'forms' ) ) {
			wp_register_ability_category(
				'forms',
				array(
					'label'       => 'Forms',
					'description' => 'Abilities for building and managing forms.',
				)
			);
		}
	}
);

add_action(
	'wp_abilities_api_init',
	function () {
		wp_register_ability(
			'abilitiesfixture/create-form',
			array(
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'fields' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'label' => array( 'type' => 'string' ),
									'type'  => array( 'type' => 'string' ),
								),
							),
						),
					),
				),
				'label'               => 'Create Form',
				'description'         => 'Creates a form from a list of fields.',
				'category'            => 'forms',
				'execute_callback'    => '__return_true',
				'permission_callback' => __NAMESPACE__ . '\can_manage',
				'meta'                => array(
					'mcp' => array( 'public' => apply_filters( 'abilities_fixture_mcp_public', false ) ),
				),
			)
		);
	}
);
