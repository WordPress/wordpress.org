<?php
/**
 * Composer Repository Root — packages.json
 *
 * Serves the Composer v2 repository manifest with a metadata-url template
 * for lazy-loading per-package metadata.
 *
 * @link https://getcomposer.org/doc/05-repositories.md#composer
 * @package WordPressdotorg\API\Composer
 */

declare( strict_types = 1 );

header( 'Content-Type: application/json; charset=utf-8' );
header( 'Access-Control-Allow-Origin: *' );
header( 'Cache-Control: public, max-age=300' ); // 5 minutes.

// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- No WP loaded.
echo json_encode(
	array(
		'packages'                   => (object) array(),
		'metadata-url'               => '/packages/p2/%package%.json',
		'notify-batch'               => '/packages/downloads',
		'available-package-patterns' => array(
			'wp-plugin/*',
			'wp-theme/*',
			'wp-core/*',
		),
	),
	JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
);
