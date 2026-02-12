<?php
/**
 * MCP Server proxy for WordPress.org.
 *
 * Proxies MCP requests to the WordPress.org REST API endpoint.
 *
 * @package WordPressdotorg\API\MCP
 */

declare( strict_types = 1 );

namespace WordPressdotorg\API\MCP;

main();

/**
 * Rewrite response headers to reference the api.wordpress.org endpoint.
 *
 * @param string $buffer The output buffer contents.
 *
 * @return false Original buffer will be output with no changes.
 */
function flush_handler( string $buffer ): false {
	// Remove CORS headers added by REST API.
	header_remove( 'access-control-allow-headers' );
	header_remove( 'access-control-expose-headers' );

	$replace = true;

	foreach ( headers_list() as $header ) {
		if ( str_starts_with( $header, 'Link: ' ) && str_contains( $header, 'wp-json' ) ) {
			$new_header = str_replace( 'https://wordpress.org/wp-json/mcp/wporg', 'https://api.wordpress.org/mcp/1.0', $header );
			if ( $new_header !== $header ) {
				header( $new_header, $replace );
				$replace = false;
			}
		}
	}

	return false;
}

/**
 * Proxy MCP requests to the WordPress.org MCP server.
 *
 * MCP clients connect to api.wordpress.org/mcp/1.0/ and this endpoint
 * boots WordPress to internally process the request via the REST API.
 */
function main(): void {
	$wp_init_host  = 'https://wordpress.org/wp-json/mcp/wporg';
	$wp_init_query = true;

	ob_start( flush_handler( ... ) );

	// Load WordPress to process the request.
	require_once dirname( __DIR__, 2 ) . '/wp-init.php';
}
