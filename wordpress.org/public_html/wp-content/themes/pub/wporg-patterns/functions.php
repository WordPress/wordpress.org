<?php

namespace WordPressdotorg\Pattern_Directory\Theme;

use const WordPressdotorg\Pattern_Directory\Pattern_Post_Type\POST_TYPE;

add_action( 'after_setup_theme', __NAMESPACE__ . '\setup' );
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\enqueue_assets' );
add_action( 'wp_head', __NAMESPACE__ . '\generate_block_editor_styles_html' );
add_action( 'pre_get_posts', __NAMESPACE__ . '\pre_get_posts' );

/**
 * Sets up theme defaults and registers support for various WordPress features.
 *
 * Note that this function is hooked into the after_setup_theme hook, which
 * runs before the init hook. The init hook is too late for some features, such
 * as indicating support for post thumbnails.
 */
function setup() {
	add_theme_support( 'post-thumbnails' );

	// The parent wporg theme is designed for use on wordpress.org/* and assumes locale-domains are available.
	// Remove hreflang support.
	remove_action( 'wp_head', 'WordPressdotorg\Theme\hreflang_link_attributes' );
}

/**
 * Enqueue styles & scripts.
 *
 * The wporg theme registers these with static versions, so we need to override with dynamic versions for
 * cache-busting. The version is set to the last modified time during development.
 */
function enqueue_assets() {
	wp_enqueue_style(
		'wporg-style',
		get_theme_file_uri( '/css/style.css' ),
		array( 'dashicons', 'open-sans' ),
		filemtime( __DIR__ . '/css/style.css' )
	);

	$script_asset_path = dirname( __FILE__ ) . '/build/index.asset.php';
	if ( is_singular( POST_TYPE ) && file_exists( $script_asset_path ) ) {
		$script_asset = require( $script_asset_path );
		wp_enqueue_script(
			'wporg-pattern-script',
			get_theme_file_uri( '/build/index.js' ),
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);

		wp_enqueue_style( 'wp-components' );

		wp_set_script_translations( 'wporg-pattern-script', 'wporg-patterns' );
	}
}

/**
 * Generate styles used in the block pattern preview iframe.
 * See https://github.com/WordPress/gutenberg/blob/6ad2a433769a4514fc52083e97aa47a0bc9edf07/lib/client-assets.php#L710
 */
function generate_block_editor_styles_html() {
	if ( ! is_singular( POST_TYPE ) ) {
		return;
	}

	$handles = array(
		'wp-block-editor',
		'wp-block-library',
		'wp-edit-blocks',
	);

	$block_registry = \WP_Block_Type_Registry::get_instance();

	foreach ( $block_registry->get_all_registered() as $block_type ) {
		if ( ! empty( $block_type->style ) ) {
			$handles[] = $block_type->style;
		}

		if ( ! empty( $block_type->editor_style ) ) {
			$handles[] = $block_type->editor_style;
		}
	}

	$handles = array_unique( $handles );
	$done    = wp_styles()->done;

	ob_start();

	wp_styles()->done = array();
	wp_styles()->do_items( $handles );
	wp_styles()->done = $done;

	wp_add_inline_script(
		'wporg-pattern-script',
		sprintf(
			'window.__editorStyles = JSON.parse( decodeURIComponent( \'%s\' ) );',
			rawurlencode( wp_json_encode( array( 'html' => ob_get_clean() ) ) )
		),
		'before'
	);
}

/**
 * Update the archive views to show block patterns.
 *
 * @param \WP_Query $wp_query The WordPress Query object.
 */
function pre_get_posts( $wp_query ) {
	if ( is_admin() ) {
		return;
	}

	// Unless otherwise specified, queries should fetch published block patterns.
	if (
		empty( $wp_query->query_vars['pagename'] ) &&
		( empty( $wp_query->query_vars['post_type'] ) || 'post' == $wp_query->query_vars['post_type'] )
	) {
		$wp_query->query_vars['post_type']   = array( POST_TYPE );
		$wp_query->query_vars['post_status'] = array( 'publish' );
	}
}
