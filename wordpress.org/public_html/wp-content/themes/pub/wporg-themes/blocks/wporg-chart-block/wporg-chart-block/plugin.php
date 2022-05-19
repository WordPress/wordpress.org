<?php
/**
 * Plugin Name: Chart Block
 * Description: Hook an endpoint to display data using a chart library.
 * Plugin URI: https://github.com/WordPress/wporg-chart-block
 * Author: WordPress.org
 * Version: 1.1.0
 * Text Domain: wporg-chart-block
 * License: GPL2+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.txt
 */

namespace WordPressdotorg\Chart_Block;

/**
 * Render the block content (html) on the frontend of the site.
 *
 * @param array  $attributes
 * @param string $content
 * @return string HTML output used by the calendar JS.
 */
function render_callback( $attributes, $content ) {
	return sprintf(
		'<div class="wporg-chart-block wporg-chart-block-js"
			data-url="%s" 
			data-title="%s" 
			data-headings="%s" 
			data-notes="%s" 
			data-type="%s" 
			data-options="%s"
		>Loading Stats ...</div>',
		esc_attr( $attributes['dataURL'] ?? '' ),
		esc_attr( $attributes['title'] ?? '' ),
		esc_attr( $attributes['headings'] ?? '' ),
		esc_attr( $attributes['notes'] ?? '' ),
		esc_attr( $attributes['chartType'] ?? '' ),
		esc_attr( $attributes['chartOptions'] ?? '' ),
	);
}

/**
 * Register scripts, styles, and block.
 */
function register_assets() {
	$block_deps_path    = __DIR__ . '/build/index.asset.php';
	$frontend_deps_path = __DIR__ . '/build/frontend.asset.php';
	if ( ! file_exists( $block_deps_path ) || ! file_exists( $frontend_deps_path ) ) {
		return;
	}

	$block_info    = require $block_deps_path;
	$frontend_info = require $frontend_deps_path;

	// Register our block script with WordPress.
	wp_register_script(
		'wporg-chart-block-block-script',
		get_template_directory_uri() . '/blocks/wporg-chart-block/build/index.js',
		$block_info['dependencies'],
		$block_info['version'],
		false
	);

	var_dump('o');

	// Register our block's base CSS .
	wp_register_style(
		'wporg-chart-block-block-style',
		get_template_directory_uri() . '/blocks/wporg-chart-block/build/style.css',
		array(),
		$block_info['version']
	);

	// No frontend scripts in the editor
	if ( ! is_admin() ) {
		wp_register_script(
			'wporg-chart-block-script',
			get_stylesheet_directory_uri() . '/blocks/wporg-chart-block/build/frontend.js',
			$frontend_info['dependencies'],
			$frontend_info['version'],
			false
		);
		wp_register_style(
			'wporg-chart-block-style',
			get_stylesheet_directory_uri() . '/blocks/wporg-chart-block/build/frontend.css',
			array( 'wp-components' ),
			$frontend_info['version']
		);
	}

	// Enqueue the script in the editor.
	register_block_type(
		'wporg-chart-block/main',
		array(
			'editor_script'   => 'wporg-chart-block-block-script',
			'editor_style'    => 'wporg-chart-block-block-style',
			'script'          => 'wporg-chart-block-script',
			'style'           => 'wporg-chart-block-style',
			'render_callback' => __NAMESPACE__ . '\render_callback',
		)
	);
}
add_action( 'init', __NAMESPACE__ . '\register_assets' );

/**
 * Conditionally remove the Script/Style assets added through `register_block_type()`.
 */
function conditionally_load_assets() {
	if ( ! is_singular() || ! has_block( 'wporg-chart-block/main' ) ) {
		wp_dequeue_script( 'wporg-chart-block-script' );
		wp_dequeue_style( 'wporg-chart-block-style' );
	}
}
add_action( 'enqueue_block_assets', __NAMESPACE__ . '\conditionally_load_assets' );
