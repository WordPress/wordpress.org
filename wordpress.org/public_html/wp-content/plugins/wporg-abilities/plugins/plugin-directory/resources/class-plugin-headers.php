<?php
/**
 * Plugin File Headers resource.
 *
 * @package WordPressdotorg\Abilities\Plugins\Plugin_Directory\Resources
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Abilities\Plugins\Plugin_Directory\Resources;

use WordPressdotorg\Abilities\Plugins\Plugin_Directory\Resource_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin_Headers class.
 */
class Plugin_Headers extends Resource_Base {

	/**
	 * Register this resource as an ability.
	 */
	public static function register(): void {
		wp_register_ability(
			'wporg/plugins/plugin-directory/plugin-headers',
			array(
				'label'               => 'Plugin File Headers',
				'description'         => 'Required and optional PHP file headers for WordPress plugins.',
				'category'            => 'wporg-plugins-plugin-directory',
				'execute_callback'    => array( __CLASS__, 'execute' ),
				'permission_callback' => '__return_true',
				'meta'                => array(
					'mcp' => array( 'type' => 'resource' ),
					'uri' => 'wporg://plugins/plugin-directory/plugin-headers',
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
		return self::get_devhub_post_content( 10908, 'wporg://plugins/plugin-directory/plugin-headers' );
	}
}
