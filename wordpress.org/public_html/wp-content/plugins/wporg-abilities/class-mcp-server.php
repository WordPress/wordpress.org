<?php
/**
 * MCP Server for WordPress.org.
 *
 * Creates a custom MCP server that exposes all WordPress.org abilities as tools.
 *
 * @package WordPressdotorg\Abilities
 */

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
		$tools = self::get_wporg_ability_names();

		if ( empty( $tools ) ) {
			return;
		}

		$adapter->create_server(
			self::SERVER_ID,
			'mcp',
			'wporg',
			'WordPress.org MCP Server',
			'MCP server for WordPress.org services.',
			'v1.0.0',
			array( HttpTransport::class ),
			ErrorLogMcpErrorHandler::class,
			NullMcpObservabilityHandler::class,
			$tools
		);
	}

	/**
	 * Get all registered WordPress.org ability names.
	 *
	 * Discovers abilities by querying the WordPress ability registry
	 * for any ability whose name starts with 'wporg/'.
	 *
	 * @return string[] Array of ability names.
	 */
	private static function get_wporg_ability_names(): array {
		$abilities = wp_get_abilities();
		$tools     = array();

		foreach ( $abilities as $ability ) {
			$name = $ability->get_name();

			if ( str_starts_with( $name, 'wporg/' ) ) {
				$tools[] = $name;
			}
		}

		return $tools;
	}
}
