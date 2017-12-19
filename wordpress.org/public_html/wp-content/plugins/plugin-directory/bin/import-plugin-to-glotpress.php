<?php
namespace WordPressdotorg\Plugin_Directory;

use Exception;
use WordPressdotorg\Plugin_Directory\Clients\Slack;

// This script should only be called in a CLI environment.
if ( 'cli' != php_sapi_name() ) {
	die();
}

$opts = getopt( '', array( 'url:', 'abspath:', 'plugin:', 'tag:', 'type:', 'no-slack' ) );

// Guess the default parameters:
if ( empty( $opts ) && $argc == 2 ) {
	$opts['plugin'] = $argv[1];
	$argv[1]        = '--plugin ' . $argv[1];
}
if ( empty( $opts['url'] ) ) {
	$opts['url'] = 'https://wordpress.org/plugins/';
}
if ( empty( $opts['abspath'] ) && false !== strpos( __DIR__, 'wp-content' ) ) {
	$opts['abspath'] = substr( __DIR__, 0, strpos( __DIR__, 'wp-content' ) );
}

foreach ( array( 'url', 'abspath', 'plugin', 'tag', 'type' ) as $opt ) {
	if ( empty( $opts[ $opt ] ) ) {
		fwrite( STDERR, "Missing Parameter: $opt\n" );
		fwrite( STDERR, "Usage: php {$argv[0]} --plugin hello-dolly --abspath /home/example/public_html --url https://wordpress.org/plugins/ --tag trunk --type readme\n" );
		fwrite( STDERR, "--url and --abspath will be guessed if possible.\n" );
		die();
	}
}

if ( ! in_array( $opts['type'], [ 'code', 'readme' ] ) ) {
	fwrite( STDERR, "Invalid value for type argument: {$opts['type']}\n" );
	die();
}

// Bootstrap WordPress
$_SERVER['HTTP_HOST']   = parse_url( $opts['url'], PHP_URL_HOST );
$_SERVER['REQUEST_URI'] = parse_url( $opts['url'], PHP_URL_PATH );

require rtrim( $opts['abspath'], '/' ) . '/wp-load.php';

if ( ! class_exists( '\WordPressdotorg\Plugin_Directory\Plugin_Directory' ) ) {
	fwrite( STDERR, "Error! This site doesn't have the Plugin Directory plugin enabled.\n" );
	if ( defined( 'WPORG_PLUGIN_DIRECTORY_BLOGID' ) ) {
		fwrite( STDERR, "Run the following command instead:\n" );
		fwrite( STDERR, "\tphp " . implode( ' ', $argv ) . ' --url ' . get_site_url( WPORG_PLUGIN_DIRECTORY_BLOGID, '/' ) . "\n" );
	}
	die();
}

$plugin_slug = $opts['plugin'];
$tag         = $opts['tag'];
$type        = $opts['type'];
$start_time  = microtime( 1 );

$plugin = Plugin_Directory::get_plugin_post( $plugin_slug );
if ( ! $plugin ) {
	fwrite( STDERR, "[{$plugin_slug}] Plugin I18N Import Failed: Plugin doesn't exist.\n" );
	exit( 1 );
}

// Prepare Slack notification.
$send_slack = defined( 'PLUGIN_IMPORTS_SLACK_WEBHOOK' ) && ! isset( $opts['no-slack'] );
if ( $send_slack ) {
	$slack_client = new Slack( PLUGIN_IMPORTS_SLACK_WEBHOOK );
	$slack_client->add_attachment( 'ts', time() );
	$slack_client->add_attachment( 'fallback', "{$plugin->post_title} has been imported." );
	$slack_client->add_attachment( 'title', "{$plugin->post_title} has been imported" );
	$slack_client->add_attachment( 'title_link', "https://translate.wordpress.org/projects/wp-plugins/{$plugin_slug}" );
	$fields = [
		[
			'title' => 'Type',
			'value' => ( 'readme' === $type ) ? 'Readme' : 'Code',
			'short' => true,
		],
		[
			'title' => 'Version',
			'value' => $tag,
			'short' => true,
		],
	];
}

echo "Processing I18N Import for $plugin_slug...\n";
try {
	if ( 'readme' === $type ) {
		$importer = new CLI\I18N\Readme_Import( $plugin_slug );
		$importer->import_from_tag( $tag );
	} elseif ( 'code' === $type ) {
		$importer = new CLI\I18N\Code_Import( $plugin_slug );
		$importer->import_from_tag( $tag );
	}

	$runtime = round( microtime( 1 ) - $start_time, 2 );

	// Send Slack notification.
	if ( $send_slack ) {
		$fields[] = [
			'title' => 'Status',
			'value' => sprintf( '%s Successfully imported! (%ss)', $slack_client->get_success_emoji(), $runtime ),
			'short' => false,
		];
		$fields[] = [
			'title' => 'Plugin',
			'value' => sprintf(
				'<%1$s|%2$s> | <https://plugins.trac.wordpress.org/log/%3$s|Log> | <%4$s|SVN>',
				get_permalink( $plugin ),
				$plugin->post_title,
				$plugin_slug,
				$importer->get_plugin_svn_url( $tag )
			),
			'short' => false,
		];
		$slack_client->add_attachment( 'fields', $fields );
		$slack_client->set_status( 'success' );
		$slack_client->send( '#meta-language-packs' );
	}

	echo "OK. Took {$runtime}s\n";
} catch ( Exception $e ) {
	$runtime = round( microtime( 1 ) - $start_time, 2 );

	// Send Slack notification.
	if ( $send_slack ) {
		$fields[] = [
			'title' => 'Status',
			'value' => sprintf( '%s %s (%ss)', $slack_client->get_failure_emoji(), $e->getMessage(), $runtime ),
			'short' => false,
		];
		$fields[] = [
			'title' => 'Plugin',
			'value' => sprintf(
				'<%1$s|%2$s> | <https://plugins.trac.wordpress.org/log/%3$s|Log> | <%4$s|SVN>',
				get_permalink( $plugin ),
				$plugin->post_title,
				$plugin_slug,
				$importer->get_plugin_svn_url( $tag )
			),
			'short' => false,
		];
		$slack_client->add_attachment( 'fields', $fields );
		$slack_client->set_status( 'failure' );
		$slack_client->send( '#meta-language-packs' );
	}

	echo "Failed. Took {$runtime}s\n";

	fwrite( STDERR, "[{$plugin_slug}] Plugin I18N Import Failed: " . $e->getMessage() . "\n" );
	exit( 2 );
}
