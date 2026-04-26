<?php
/**
 * Plugin Name: WordPress.org Media Resource Checker
 * Description: Displays warnings in the block editor when media resources are from domains other than the recommended ones.
 * Version:     1.0.0
 * Author:      WordPress.org
 * Author URI:  http://wordpress.org/
 * License:     GPLv2 or later
 */

namespace WordPressdotorg\Media_Resource_Checker;

defined( 'WPINC' ) || die();

define( __NAMESPACE__ . '\PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( __NAMESPACE__ . '\PLUGIN_URL', plugins_url( '/', __FILE__ ) );

/**
 * Actions and filters.
 */
add_action( 'enqueue_block_editor_assets', __NAMESPACE__ . '\enqueue_block_editor_assets' );
add_action( 'enqueue_block_assets', __NAMESPACE__ . '\enqueue_block_assets' );

/**
 * Enqueue scripts for the block editor.
 */
function enqueue_block_editor_assets() {
	$script_asset_path = __DIR__ . '/build/index.asset.php';
	if ( ! file_exists( $script_asset_path ) ) {
		wp_die( 'You need to run `yarn start` or `yarn build` to build the required assets.' );
	}
	$script_asset = require $script_asset_path;
	wp_enqueue_script(
		'wporg-media-resource-checker',
		plugins_url( 'build/index.js', __FILE__ ),
		$script_asset['dependencies'],
		$script_asset['version'],
		true
	);
	wp_set_script_translations( 'wporg-media-resource-checker', 'wporg' );
}


/**
 * Enqueue styles for the block.
 */
function enqueue_block_assets() {
	if ( ! is_admin() ) {
		return;
	}
	$script_asset_path = __DIR__ . '/build/index.asset.php';
	if ( ! file_exists( $script_asset_path ) ) {
		wp_die( 'You need to run `yarn start` or `yarn build` to build the required assets.' );
	}
	$script_asset = require( $script_asset_path );
	wp_enqueue_style(
		'wporg-media-resource-checker',
		plugins_url( 'build/style-index.css', __FILE__ ),
		[],
		$script_asset['version']
	);
	wp_style_add_data( 'wporg-media-resource-checker', 'rtl', 'replace' );
}
