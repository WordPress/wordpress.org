<?php
/**
 * Plugin Name: WordPress.org Media Resource Checker
 * Description: Displays warnings in the block editor when media resources are from domains other than the recommended ones.
 * Version:     1.0
 * Author:      WordPress.org
 * Author URI:  http://wordpress.org/
 * License:     GPLv2 or later
 */

namespace WordPressdotorg\Media_Resource_Checker;

use function WordPressdotorg\Media_Resource_Checker\{ get_build_path, get_build_url };

defined( 'WPINC' ) || die();

define( __NAMESPACE__ . '\PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( __NAMESPACE__ . '\PLUGIN_URL', plugins_url( '/', __FILE__ ) );

/**
 * Actions and filters.
 */
add_action( 'enqueue_block_editor_assets', __NAMESPACE__ . '\enqueue_block_editor_assets' );
add_action( 'enqueue_block_assets', __NAMESPACE__ . '\enqueue_block_assets' );


/**
 * Shortcut to the build directory.
 *
 * @return string
 */
function get_build_path() {
	return PLUGIN_DIR . 'build/';
}

/**
 * Shortcut to the build URL.
 *
 * @return string
 */
function get_build_url() {
	return PLUGIN_URL . 'build/';
}


/**
 * Enqueue scripts for the block editor.
 */
function enqueue_block_editor_assets() {
	$script_asset_path = get_build_path() . 'index.asset.php';
	if ( ! file_exists( $script_asset_path ) ) {
		wp_die( 'You need to run `yarn start` or `yarn build` to build the required assets.' );
	}
	$script_asset = require( $script_asset_path );
	wp_enqueue_script(
		'wporg-media-resource-checker',
		get_build_url() . 'index.js',
		$script_asset['dependencies'],
		$script_asset['version'],
		true
	);
	wp_set_script_translations( 'wporg-media-resource-checker', 'wporg-media-resource-checker' );
}


/**
 * Enqueue styles for the block.
 */
function enqueue_block_assets() {
	if ( ! is_admin() ) {
		return;
	}
	$script_asset_path = get_build_path() . 'index.asset.php';
	if ( ! file_exists( $script_asset_path ) ) {
		wp_die( 'You need to run `yarn start` or `yarn build` to build the required assets.' );
	}
	$script_asset = require( $script_asset_path );
	wp_enqueue_style(
		'wporg-media-resource-checker',
		get_build_url() . 'style-index.css',
		[],
		$script_asset['version']
	);
}
