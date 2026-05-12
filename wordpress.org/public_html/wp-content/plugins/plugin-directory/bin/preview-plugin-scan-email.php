<?php
/**
 * Preview Plugin Scan Email.
 *
 * @package WordPressdotorg\Plugin_Directory\Bin\PreviewPluginScanEmail
 */

namespace WordPressdotorg\Plugin_Directory\Bin\PreviewPluginScanEmail;

use WordPressdotorg\Plugin_Directory\Jobs\Plugin_Updates_PCP;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;

if ( 'cli' !== php_sapi_name() ) {
	die();
}

$opts = getopt(
	'',
	array(
		'url:',
		'abspath:',
		'plugin:',
		'tag::',
		'result-json::',
		'send',
	)
);

if ( empty( $opts['plugin'] ) ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI script output.
	fwrite( STDERR, "Usage: php bin/preview-plugin-scan-email.php --plugin=<slug> [--tag=<tag>] [--result-json=<file>] [--send]\n" );
	exit( 1 );
}

if ( empty( $opts['url'] ) ) {
	$opts['url'] = 'https://wordpress.org/plugins/';
}

if ( empty( $opts['abspath'] ) && false !== strpos( __DIR__, 'wp-content' ) ) {
	$opts['abspath'] = substr( __DIR__, 0, strpos( __DIR__, 'wp-content' ) );
}

// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- CLI bootstrap URL parsing.
$_SERVER['HTTP_HOST'] = parse_url( $opts['url'], PHP_URL_HOST );
// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- CLI bootstrap URL parsing.
$_SERVER['REQUEST_URI'] = parse_url( $opts['url'], PHP_URL_PATH );

require rtrim( $opts['abspath'], '/' ) . '/wp-load.php';

if ( ! class_exists( '\WordPressdotorg\Plugin_Directory\Plugin_Directory' ) ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI script output.
	fwrite( STDERR, "Error: plugin-directory is not loaded on this site.\n" );
	exit( 1 );
}

$plugin_post = Plugin_Directory::get_plugin_post( $opts['plugin'] );
if ( ! $plugin_post ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI script output.
	fwrite( STDERR, "Error: unknown plugin slug '{$opts['plugin']}'.\n" );
	exit( 1 );
}

$scan_tag = $opts['tag'] ?? 'trunk';

$results = load_results( $opts['result-json'] ?? '' );
if ( ! $results ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI script output.
	fwrite( STDERR, "Error: invalid result payload.\n" );
	exit( 1 );
}

$send_live = isset( $opts['send'] );
if ( ! $send_live ) {
	add_filter(
		'pre_wp_mail',
		static function ( $pre, $atts ) {
			$to      = implode( ', ', array_map( 'strval', (array) ( $atts['to'] ?? array() ) ) );
			$headers = implode( "\n", array_map( 'strval', (array) ( $atts['headers'] ?? array() ) ) );
			$subject = (string) ( $atts['subject'] ?? '' );
			$message = (string) ( $atts['message'] ?? '' );

			echo "=== PREVIEW MODE: email was not sent ===\n";
			echo 'To: ' . esc_html( $to ) . "\n";
			echo 'Subject: ' . esc_html( $subject ) . "\n";
			echo "Headers:\n" . esc_html( $headers ) . "\n";
			echo "\nMessage:\n" . esc_html( $message ) . "\n";
			echo "=== END PREVIEW ===\n";

			return true;
		},
		10,
		2
	);
}

Plugin_Updates_PCP::notify_plugin_authors( $plugin_post, $results, $scan_tag );

if ( $send_live ) {
	echo "Email send attempted in live mode.\n";
}

/**
 * Load scanner result payload from JSON file or fallback sample data.
 *
 * @param string $result_json_path Absolute path to a JSON payload file.
 * @return array|false
 */
function load_results( $result_json_path ) {
	if ( $result_json_path ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local CLI helper reads a local JSON file.
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
	return array(
		'totals' => array(
			'errors' => 1,
		),
		'files'  => array(
			'example-plugin.php' => array(
				array(
					'line'    => 42,
					'type'    => 'ERROR',
					'code'    => 'example_error_code',
					'message' => 'Example scan error for preview/testing.',
					'context' => array(
						41 => '$value = $_GET[\'value\'];',
						42 => 'echo $value;',
					),
				),
			),
		),
	);
}
