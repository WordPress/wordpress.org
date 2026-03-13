<?php
/**
 * Validate Readme tool.
 *
 * @package WordPressdotorg\Abilities\Plugins\Plugin_Directory\Tools
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Abilities\Plugins\Plugin_Directory\Tools;

use WordPressdotorg\Abilities\Plugins\Plugin_Directory\Ability_Base;
use WordPressdotorg\Plugin_Directory\Readme\Validator;

defined( 'ABSPATH' ) || exit;

/**
 * Validate_Readme class.
 */
class Validate_Readme extends Ability_Base {

	/**
	 * Register this tool as an ability.
	 */
	public static function register(): void {
		wp_register_ability(
			'wporg/plugins/plugin-directory/validate-readme',
			array(
				'label'               => 'Validate Plugin Readme',
				'description'         => 'Validates a WordPress plugin readme.txt file and returns errors, warnings, and notes.',
				'category'            => 'wporg-plugins-plugin-directory',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'content' => array(
							'type'        => 'string',
							'description' => 'The full text content of the readme.txt or readme.md file to validate.',
						),
					),
					'required'   => array( 'content' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'errors'   => array(
							'type'                 => 'object',
							'description'          => 'Validation errors that must be fixed before submission.',
							'additionalProperties' => array( 'type' => 'string' ),
						),
						'warnings' => array(
							'type'                 => 'object',
							'description'          => 'Warnings that should be reviewed and fixed if applicable.',
							'additionalProperties' => array( 'type' => 'string' ),
						),
						'notes'    => array(
							'type'                 => 'object',
							'description'          => 'Informational notes and suggestions.',
							'additionalProperties' => array( 'type' => 'string' ),
						),
					),
				),
				'execute_callback'    => array( __CLASS__, 'execute' ),
				'permission_callback' => '__return_true',
				'meta'                => array(
					'mcp'         => array( 'type' => 'tool' ),
					'annotations' => array(
						'readonly'     => true,
						'idempotent'   => true,
						'destructive'  => false,
					),
				),
			)
		);
	}

	/**
	 * Validate the provided readme content.
	 *
	 * @param array $input The tool input containing 'content'.
	 * @return array MCP tool result.
	 */
	public static function execute( array $input ): array {
		self::maybe_load_plugin_directory();

		if ( ! class_exists( Validator::class ) ) {
			return array(
				'error' => 'The plugin directory readme validator is not available.',
			);
		}

		$results = Validator::instance()->validate_content( $input['content'] );

		// Convert HTML messages to markdown/plain text for AI agent consumption.
		foreach ( $results as $type => $items ) {
			foreach ( $items as $code => $message ) {
				if ( is_string( $message ) ) {
					$results[ $type ][ $code ] = self::html_to_text( $message );
				}
			}
		}

		return $results;
	}
}
