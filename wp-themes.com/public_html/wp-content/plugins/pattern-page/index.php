<?php
/**
 * Plugin name: Pattern Preview
 * Description: Creates dynamic pages for previewing a pattern in a theme.
 * Version:     2.0
 * Author:      WordPress.org
 * Author URI:  http://wordpress.org/
 * License:     GPLv2 or later
 */

namespace WordPressdotorg\PatternPreview\PatternBlock;

defined( 'WPINC' ) || die();

require_once __DIR__ . '/inc/pattern-endpoint.php';
require_once __DIR__ . '/inc/page-intercept.php';

add_action( 'init', __NAMESPACE__ . '\register_assets', 20 );

/**
 * Renders the `wporg/patterns-preview` block.
 *
 * @param array    $attributes Block attributes.
 * @param string   $content    Block default content.
 * @param WP_Block $block      Block instance.
 *
 * @return string Returns the pattern content.
 */
function render_block( $attributes, $content ) {
	if ( ! isset( $attributes['pattern-name'] ) ) {
		return '';
	}

	$pattern = \WP_Block_Patterns_Registry::get_instance()->get_registered( $attributes['pattern-name'] );

	return sprintf(
		'<div id="wporg-pattern-preview" style="max-width: unset"><div>%1$s</div></div>', // We use this element ID in JS
		do_blocks( $pattern['content'] )
	);
}

/**
 * Registers assets.
 */
function register_assets() {
	/**
	 * The preview is a localized view of the page.
	 * The JS/CSS is only needed when we are previewing in an iframe.
	 * The JS/CSS modify the underlying theme.
	 */
	if ( ! is_admin() && isset( $_GET['preview'] ) ) {
		wp_enqueue_script(
			'wporg-pattern-preview',
			plugin_dir_url( __FILE__ ) . 'build/index.js',
			array(),
			filemtime( __DIR__ . '/build/index.js' ),
			true
		);

		wp_enqueue_style(
			'wporg-pattern-preview-style',
			plugin_dir_url( __FILE__ ) . 'css/style.css',
			array(),
			filemtime( __DIR__ . '/css/style.css' )
		);
	}

	register_block_type(
		__DIR__ . '/block.json',
		array(
			'render_callback' => __NAMESPACE__ . '\render_block',
		)
	);
}
