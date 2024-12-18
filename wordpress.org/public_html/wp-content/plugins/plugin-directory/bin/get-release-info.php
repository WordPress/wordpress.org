<?php
namespace WordPressdotorg\Plugin_Directory;

// CLI script for printing release CPT details for a plugin
// Usage: php get-release-info.php --plugin hello-dolly

// Intended for use while testing; this can probably be removed once everything is live; or turned into a fixer script.

// This script should only be called in a CLI environment.
if ( 'cli' != php_sapi_name() ) {
	die();
}

ob_start();

$opts = getopt( '', array( 'url:', 'abspath:', 'plugin:', 'changed-tags:', 'async', 'publish' ) );

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

if ( empty( $opts['changed-tags'] ) ) {
	$opts['changed-tags'] = array( 'trunk' );
} else {
	$opts['changed-tags'] = explode( ',', $opts['changed-tags'] );
}

$opts['async']  = isset( $opts['async'] );
$opts['publish'] = isset( $opts['publish'] );

foreach ( array( 'url', 'abspath', 'plugin' ) as $opt ) {
	if ( empty( $opts[ $opt ] ) ) {
		fwrite( STDERR, "Missing Parameter: $opt\n" );
		fwrite( STDERR, "Usage: php {$argv[0]} --plugin hello-dolly --abspath /home/example/public_html --url https://wordpress.org/plugins/\n" );
		fwrite( STDERR, "Optional: --async to queue a job to import, --create to create a Post if none exist.\n" );
		fwrite( STDERR, "--url and --abspath will be guessed if possible.\n" );
		die();
	}
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

$plugin_slug  = $opts['plugin'];
$start_time   = microtime( 1 );

$plugin = Plugin_Directory::get_plugin_post( $plugin_slug );
if ( ! $plugin ) {
	fwrite( STDERR, "Plugin not found: $plugin_slug\n" );
	die();
}

echo "Showing releases for $plugin_slug ($plugin->ID)...\n";

$releases = Plugin_Release::instance()->get_releases( $plugin );
if ( is_wp_error( $releases ) ) {
	fwrite( STDERR, "Failed to update releases for $plugin_slug: " . $updated->get_error_message() . "\n" );
	die();
}

foreach( $releases as $release ) {
	echo "ID:                     " . $release->ID . "\n";
	echo "Status:                 " . $release->post_status . "\n";
	echo "Release version:        " . $release->post_title . "\n";
	echo "Tag:                    " . $release->release_tag . "\n";
	echo "Post date GMT:          " . $release->post_date_gmt . "\n";
	if ( $release->release_date ) {
		echo "Date:                   " . ( new \DateTime( '@' . $release->release_date ) )->format( 'Y-m-d H:i:s' ) . "\n";
	}
	echo "Committers:             " . implode( ', ', $release->release_committer ) . "\n";
	echo "Zips built:             " . ( $release->release_zips_built ? 'Yes' : 'No' ) . "\n";
	echo "Confirmations required: " . ( $release->release_confirmations_required ? 'Yes' : 'No' ) . "\n";
	echo "Release revision:       " . $release->release_revision_final . "\n";
	echo "Previous version rev:   " . $release->release_revision_prior . "\n";
	if ( $release->release_commit_log ) {
		echo "Commit log:\n";
		foreach( $release->release_commit_log as $commit ) {
			echo "  " . ( new \DateTime( '@' . $commit['date'] ) )->format( 'Y-m-d' ) . " - ";
			echo "  " . $commit['author'] . ' r' . $commit['revision'] . " - " . \wp_trim_words( $commit['message'] ) . "\n";
		}
	}
	echo "-----------------------------------\n";
}