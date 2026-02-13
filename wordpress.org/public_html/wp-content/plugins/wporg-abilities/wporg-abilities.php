<?php
/**
 * Plugin Name: WordPress.org Abilities
 * Plugin URI:  https://wordpress.org/
 * Description: Provides the MCP server infrastructure for WordPress.org abilities.
 * Version:     1.0.0
 * Author:      WordPress.org
 * Author URI:  https://wordpress.org/
 * Text Domain: wporg
 * License:     GPLv2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package WordPressdotorg\Abilities
 */

namespace WordPressdotorg\Abilities;

use WP\MCP\Core\McpAdapter;

defined( 'ABSPATH' ) || exit;

// Load Composer autoloader for MCP adapter.
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

if ( ! class_exists( McpAdapter::class ) ) {
	return;
}

// Register plugin autoloader.
require_once __DIR__ . '/class-autoloader.php';
Autoloader\register_class_path( __NAMESPACE__, __DIR__ );

// Initialize MCP adapter and register server.
McpAdapter::instance();

$action = defined( 'WP_CLI' ) && constant( 'WP_CLI' ) ? 'init' : 'rest_api_init';

add_action( $action, array( Registrar::class, 'init' ) );
add_action( 'mcp_adapter_init', array( MCP_Server::class, 'register' ) );
add_filter( 'mcp_adapter_create_default_server', '__return_false' );
