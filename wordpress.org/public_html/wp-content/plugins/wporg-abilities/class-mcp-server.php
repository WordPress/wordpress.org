<?php
/**
 * MCP Server for WordPress.org.
 *
 * Creates a custom MCP server that exposes WordPress.org abilities as tools, resources, and prompts.
 *
 * @package WordPressdotorg\Abilities
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Abilities;

use WP\MCP\Core\McpAdapter;
use WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler;
use WP\MCP\Infrastructure\Observability\NullMcpObservabilityHandler;
use WP\MCP\Transport\HttpTransport;

defined( 'ABSPATH' ) || exit;

/**
 * MCP Server class.
 */
class MCP_Server {

	/**
	 * Server ID.
	 *
	 * @var string
	 */
	const SERVER_ID = 'wporg-mcp-server';

	/**
	 * Register the WordPress.org MCP server.
	 *
	 * Called on the 'mcp_adapter_init' action.
	 *
	 * @param McpAdapter $adapter The MCP adapter instance.
	 */
	public static function register( McpAdapter $adapter ): void {
		$components = self::get_wporg_components();

		if ( empty( $components['tools'] ) && empty( $components['resources'] ) && empty( $components['prompts'] ) ) {
			return;
		}

		$adapter->create_server(
			self::SERVER_ID,
			'mcp',
			'wporg',
			'WordPress.org MCP Server',
			implode(
				"\n\n",
				array(
					'WordPress.org MCP Server — provides tools, resources, and prompts for interacting with WordPress.org services.',
					'Use prompts/list to discover available workflows for specific tasks. Browse wporg://* resources for reference documentation.',
					'All write operations require authentication via application password. To set up authentication, visit: https://login.wordpress.org/?action=authorize_application&app_id=c4c73a54-96d7-47b9-9bdc-1a66b9b04505',
				)
			),
			'v1.0.0',
			array( HttpTransport::class ),
			ErrorLogMcpErrorHandler::class,
			NullMcpObservabilityHandler::class,
			$components['tools'],
			$components['resources'],
			$components['prompts']
		);
	}

	/**
	 * Get all registered WordPress.org abilities categorized by MCP component type.
	 *
	 * Discovers abilities whose name starts with 'wporg/' and categorizes them
	 * based on their meta['mcp']['type'] value: 'resource', 'prompt', or 'tool' (default).
	 *
	 * @return array {
	 *     Abilities grouped by MCP component type.
	 *
	 *     @type string[] $tools     Tool ability names.
	 *     @type string[] $resources Resource ability names.
	 *     @type string[] $prompts   Prompt ability names.
	 * }
	 */
	private static function get_wporg_components(): array {
		$abilities  = wp_get_abilities();
		$components = array(
			'tools'     => array(),
			'resources' => array(),
			'prompts'   => array(),
		);

		foreach ( $abilities as $ability ) {
			$name = $ability->get_name();

			if ( ! str_starts_with( $name, 'wporg/' ) ) {
				continue;
			}

			$meta = $ability->get_meta();
			$type = $meta['mcp']['type'] ?? 'tool';

			switch ( $type ) {
				case 'resource':
					$components['resources'][] = $name;
					break;
				case 'prompt':
					$components['prompts'][] = $name;
					break;
				case 'tool':
				default:
					$components['tools'][] = $name;
					break;
			}
		}

		return $components;
	}
}
