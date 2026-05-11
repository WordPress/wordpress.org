<?php
// phpcs:ignoreFile — CLI script: output not escaped, loop vars shadow WP globals.
namespace WordPressdotorg\Plugin_Directory;

use WordPressdotorg\Plugin_Directory\CLI\Import;

// This script should only be called in a CLI environment.
if ( 'cli' != php_sapi_name() ) {
	die();
}

$opts = getopt( '', array( 'url:', 'abspath:', 'plugin:', 'limit:', 'dry-run' ) );

if ( empty( $opts['url'] ) ) {
	$opts['url'] = 'https://wordpress.org/plugins/';
}
if ( empty( $opts['abspath'] ) && false !== strpos( __DIR__, 'wp-content' ) ) {
	$opts['abspath'] = substr( __DIR__, 0, strpos( __DIR__, 'wp-content' ) );
}

$opts['dry-run'] = isset( $opts['dry-run'] );
$opts['limit']   = isset( $opts['limit'] ) ? (int) $opts['limit'] : 0;

// Bootstrap WordPress.
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

global $wpdb;

// Be more patient with slow SVN responses during a bulk run than the
// per-request import path is. Uses http_request_args (not the timeout
// filter) so the helper's explicit `timeout => 15` is overridden.
add_filter(
	'http_request_args',
	function ( $args ) {
		$args['timeout'] = 30;
		return $args;
	}
);

$meta_keys = array(
	'screenshot' => 'assets_screenshots',
	'banner'     => 'assets_banners',
	'icon'       => 'assets_icons',
);

if ( ! empty( $opts['plugin'] ) ) {
	$plugin = Plugin_Directory::get_plugin_post( $opts['plugin'] );
	if ( ! $plugin ) {
		fwrite( STDERR, "Plugin '{$opts['plugin']}' not found.\n" );
		exit( 1 );
	}
	$post_ids = array( $plugin->ID );
} else {
	$post_ids = $wpdb->get_col( $wpdb->prepare(
		"SELECT DISTINCT post_id
		   FROM {$wpdb->postmeta}
		  WHERE meta_key IN ( %s, %s, %s )
		    AND meta_value != ''
		    AND meta_value != 'a:0:{}'
		  ORDER BY post_id ASC",
		$meta_keys['screenshot'],
		$meta_keys['banner'],
		$meta_keys['icon']
	) );

	if ( $opts['limit'] > 0 ) {
		$post_ids = array_slice( $post_ids, 0, $opts['limit'] );
	}
}

$total_plugins   = count( $post_ids );
$plugins_updated = 0;
$counts          = array(
	'reused'    => 0,
	'extracted' => 0,
	'failed'    => 0,
	'skipped'   => 0, // SVG / non-raster.
);
$failed_files    = array();

echo "Scanning {$total_plugins} plugins" . ( $opts['dry-run'] ? ' (dry-run)' : '' ) . "...\n";

foreach ( $post_ids as $i => $post_id ) {
	$plugin = get_post( $post_id );
	if ( ! $plugin || 'plugin' !== $plugin->post_type ) {
		continue;
	}

	$slug         = $plugin->post_name; // Only used for the failure list.
	$plugin_dirty = false;

	foreach ( $meta_keys as $type => $meta_key ) {
		$records = get_post_meta( $post_id, $meta_key, true );
		if ( ! is_array( $records ) || ! $records ) {
			continue;
		}

		$meta_dirty = false;

		foreach ( $records as $filename => $record ) {
			if ( ! is_array( $record ) || empty( $record['filename'] ) || ! isset( $record['revision'] ) ) {
				continue;
			}

			$ext       = strtolower( pathinfo( $record['filename'], PATHINFO_EXTENSION ) );
			$is_raster = in_array( $ext, array( 'png', 'jpg', 'jpeg', 'gif' ), true );
			$has_dims  = ! empty( $record['width'] ) && ! empty( $record['height'] );

			if ( ! $is_raster ) {
				++$counts['skipped'];
				continue;
			}
			if ( $has_dims ) {
				++$counts['reused'];
				continue;
			}

			// Pass the existing record as the prior so the helper short-circuits
			// for cases it already knows the answer for.
			$updated = Import::enrich_asset_dimensions( $record, $record, $plugin );

			if ( ! empty( $updated['width'] ) && ! empty( $updated['height'] ) ) {
				++$counts['extracted'];
			} else {
				++$counts['failed'];
				$failed_files[] = "{$slug} {$type} {$record['filename']}";
			}

			if ( $updated !== $record ) {
				$records[ $filename ] = $updated;
				$meta_dirty           = true;
			}
		}

		if ( $meta_dirty ) {
			$plugin_dirty = true;
			if ( ! $opts['dry-run'] ) {
				update_post_meta( $post_id, $meta_key, wp_slash( $records ) );
			}
		}
	}

	if ( $plugin_dirty ) {
		++$plugins_updated;
	}

	if ( ( $i + 1 ) % 100 === 0 ) {
		echo sprintf(
			"  [%d/%d] reused=%d extracted=%d failed=%d skipped=%d\n",
			$i + 1,
			$total_plugins,
			$counts['reused'],
			$counts['extracted'],
			$counts['failed'],
			$counts['skipped']
		);
	}
}

echo "\nDone.\n";
echo "  Plugins scanned: {$total_plugins}\n";
echo "  Plugins updated: {$plugins_updated}" . ( $opts['dry-run'] ? ' (would update)' : '' ) . "\n";
echo "  Assets with cached dimensions reused: {$counts['reused']}\n";
echo "  Assets newly extracted:                {$counts['extracted']}\n";
echo "  Assets failed extraction:              {$counts['failed']}\n";
echo "  Non-raster assets skipped (SVG/etc.):  {$counts['skipped']}\n";

if ( $failed_files ) {
	echo "\nFailed assets:\n  " . implode( "\n  ", $failed_files ) . "\n";
}
