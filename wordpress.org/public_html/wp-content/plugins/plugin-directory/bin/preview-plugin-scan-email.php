<?php
namespace WordPressdotorg\Plugin_Directory\Bin\PreviewPluginScanEmail;

use WordPressdotorg\Plugin_Directory\Jobs\Plugin_Updates_PCP;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;

if ( 'cli' !== php_sapi_name() ) {
	die();
}

$opts = getopt(
	'',
	[
		'url:',
		'abspath:',
		'plugin:',
		'tag::',
		'result-json::',
		'send',
	]
);

if ( empty( $opts['plugin'] ) ) {
	fwrite( STDERR, "Usage: php bin/preview-plugin-scan-email.php --plugin=<slug> [--tag=<tag>] [--result-json=<file>] [--send]\n" );
	exit( 1 );
}

if ( empty( $opts['url'] ) ) {
	$opts['url'] = 'https://wordpress.org/plugins/';
}

if ( empty( $opts['abspath'] ) && false !== strpos( __DIR__, 'wp-content' ) ) {
	$opts['abspath'] = substr( __DIR__, 0, strpos( __DIR__, 'wp-content' ) );
}

$_SERVER['HTTP_HOST']   = parse_url( $opts['url'], PHP_URL_HOST );
$_SERVER['REQUEST_URI'] = parse_url( $opts['url'], PHP_URL_PATH );

require rtrim( $opts['abspath'], '/' ) . '/wp-load.php';

if ( ! class_exists( '\WordPressdotorg\Plugin_Directory\Plugin_Directory' ) ) {
	fwrite( STDERR, "Error: plugin-directory is not loaded on this site.\n" );
	exit( 1 );
}

$plugin = Plugin_Directory::get_plugin_post( $opts['plugin'] );
if ( ! $plugin ) {
	fwrite( STDERR, "Error: unknown plugin slug '{$opts['plugin']}'.\n" );
	exit( 1 );
}

$tag = $opts['tag'] ?? 'trunk';

$results = load_results( $opts['result-json'] ?? '' );
if ( ! $results ) {
	fwrite( STDERR, "Error: invalid result payload.\n" );
	exit( 1 );
}

$send_live = isset( $opts['send'] );
if ( ! $send_live ) {
	add_filter(
		'pre_wp_mail',
		static function( $pre, $atts ) {
			echo "=== PREVIEW MODE: email was not sent ===\n";
			echo 'To: ';
			echo implode( ', ', (array) ( $atts['to'] ?? [] ) );
			echo "\n";
			echo 'Subject: ' . ( $atts['subject'] ?? '' ) . "\n";
			echo "Headers:\n";
			echo implode( "\n", (array) ( $atts['headers'] ?? [] ) ) . "\n";
			echo "\nMessage:\n";
			echo ( $atts['message'] ?? '' ) . "\n";
			echo "=== END PREVIEW ===\n";

			return true;
		},
		10,
		2
	);
}

Plugin_Updates_PCP::notify_plugin_authors( $plugin, $results, $tag );

if ( $send_live ) {
	echo "Email send attempted in live mode.\n";
}

function load_results( $result_json_path ) {
	if ( $result_json_path ) {
		$json = file_get_contents( $result_json_path );
		if ( ! $json ) {
			return false;
		}

		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			return false;
		}

		return $data;
	}

	// Default sample payload mirrors the structure expected by notify_plugin_authors().
	return [
		'totals' => [
			'errors' => 1,
		],
		'files'  => [
			'example-plugin.php' => [
				[
					'line'    => 42,
					'type'    => 'ERROR',
					'code'    => 'example_error_code',
					'message' => 'Example scan error for preview/testing.',
					'context' => [
						41 => '$value = $_GET[\'value\'];',
						42 => 'echo $value;',
					],
				],
			],
		],
	];
}
