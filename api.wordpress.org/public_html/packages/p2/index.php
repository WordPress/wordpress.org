<?php
/**
 * Composer v2 Per-Package Metadata Endpoint.
 *
 * Serves individual package metadata in Composer v2 format.
 *
 * URL pattern: /packages/p2/wp-{type}/{slug}.json
 * Where type is: plugin, theme, or core.
 *
 * Examples:
 *   /packages/p2/wp-plugin/akismet.json
 *   /packages/p2/wp-theme/flavor.json
 *   /packages/p2/wp-core/wordpress.json
 *
 * @package WordPressdotorg\API\Composer
 */

declare( strict_types = 1 );

namespace WordPressdotorg\API\Composer;

// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Direct server variable check.
if ( 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	header( ( $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0' ) . ' 405 Method Not Allowed', true, 405 );
	header( 'Allow: GET' );
	die();
}

// Load Composer repository classes first (for parse_request).
require_once __DIR__ . '/../includes/class-version-normalizer.php';
require_once __DIR__ . '/../includes/class-package-builder.php';
require_once __DIR__ . '/../includes/class-composer-repository.php';

// Parse the request early, before loading heavy dependencies.
$parsed = Composer_Repository::parse_request( $_SERVER['REQUEST_URI'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Sanitized in parse_request via regex.

if ( ! $parsed ) {
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	header( ( $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0' ) . ' 400 Bad Request', true, 400 );
	header( 'Content-Type: application/json; charset=utf-8' );

	// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- No WP loaded.
	echo json_encode( array( 'error' => 'Invalid package URL. Expected: /packages/p2/wp-{type}/{slug}.json' ) );
	exit;
}

// Load the API environment.
require_once dirname( __DIR__, 2 ) . '/wp-init-ondemand.php';

// Load infrastructure helpers if available.
$packages_helper = dirname( __DIR__, 2 ) . '/includes/packages-helper.php';
if ( file_exists( $packages_helper ) ) {
	require_once $packages_helper;
}

// Register composer as a global cache group so keys are consistent regardless of blog context.
wp_cache_add_global_groups( 'composer' );

// Set the object cache blog prefix for the plugin directory. The plugin info API uses this for its internal cache lookups.
$wp_object_cache->blog_prefix = WPORG_PLUGIN_DIRECTORY_BLOGID;

// Serve the package.
Composer_Repository::serve( $parsed['type'], $parsed['slug'] );
