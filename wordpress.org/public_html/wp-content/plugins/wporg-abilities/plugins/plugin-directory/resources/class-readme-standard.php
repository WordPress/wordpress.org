<?php
/**
 * Readme.txt Standard resource.
 *
 * @package WordPressdotorg\Abilities\Plugins\Plugin_Directory\Resources
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Abilities\Plugins\Plugin_Directory\Resources;

use WordPressdotorg\Abilities\Plugins\Plugin_Directory\Resource_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Readme_Standard class.
 */
class Readme_Standard extends Resource_Base {

	/**
	 * Register this resource as an ability.
	 */
	public static function register(): void {
		wp_register_ability(
			'wporg/plugins/plugin-directory/readme-standard',
			array(
				'label'               => 'Readme.txt Standard',
				'description'         => 'The WordPress.org plugin readme.txt format specification including required headers, sections, and markdown support.',
				'category'            => 'wporg-plugins-plugin-directory',
				'execute_callback'    => array( __CLASS__, 'execute' ),
				'permission_callback' => '__return_true',
				'meta'                => array(
					'mcp' => array( 'type' => 'resource' ),
					'uri' => 'wporg://plugins/plugin-directory/readme-standard',
				),
			)
		);
	}

	/**
	 * Return the resource content.
	 *
	 * @return array MCP resource contents array.
	 */
	public static function execute(): array {
		return self::get_devhub_post_content( 19147, 'wporg://plugins/plugin-directory/readme-standard' );
	}
}
