<?php
/**
 * Plugin Guidelines resource.
 *
 * @package WordPressdotorg\Abilities\Plugins\Plugin_Directory\Resources
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Abilities\Plugins\Plugin_Directory\Resources;

use WordPressdotorg\Abilities\Plugins\Plugin_Directory\Ability_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin_Guidelines class.
 */
class Plugin_Guidelines extends Ability_Base {

	/**
	 * Register this resource as an ability.
	 */
	public static function register(): void {
		wp_register_ability(
			'wporg/plugins/plugin-directory/plugin-guidelines',
			array(
				'label'               => 'Plugin Guidelines',
				'description'         => 'The WordPress.org plugin directory detailed guidelines that all plugins must follow.',
				'category'            => 'wporg-plugins-plugin-directory',
				'execute_callback'    => array( __CLASS__, 'execute' ),
				'permission_callback' => '__return_true',
				'meta'                => array(
					'mcp' => array( 'type' => 'resource' ),
					'uri' => 'wporg://plugins/plugin-directory/plugin-guidelines',
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
		return self::get_devhub_post_content( 15264, 'wporg://plugins/plugin-directory/plugin-guidelines' );
	}
}
