<?php
/**
 * Plugin FAQ resource.
 *
 * @package WordPressdotorg\Abilities\Plugins\Plugin_Directory\Resources
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Abilities\Plugins\Plugin_Directory\Resources;

use WordPressdotorg\Abilities\Plugins\Plugin_Directory\Resource_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin_FAQ class.
 */
class Plugin_FAQ extends Resource_Base {

	/**
	 * Register this resource as an ability.
	 */
	public static function register(): void {
		wp_register_ability(
			'wporg/plugins/plugin-directory/plugin-faq',
			array(
				'label'               => 'Plugin Directory FAQ',
				'description'         => 'Frequently asked questions about the WordPress.org plugin directory submission and review process.',
				'category'            => 'wporg-plugins-plugin-directory',
				'execute_callback'    => array( __CLASS__, 'execute' ),
				'permission_callback' => '__return_true',
				'meta'                => array(
					'mcp' => array( 'type' => 'resource' ),
					'uri' => 'wporg://plugins/plugin-directory/plugin-faq',
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
		return self::get_devhub_post_content( 15282, 'wporg://plugins/plugin-directory/plugin-faq' );
	}
}
