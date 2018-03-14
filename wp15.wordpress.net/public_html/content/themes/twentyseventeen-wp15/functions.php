<?php

namespace WP15\Theme;

defined( 'WPINC' ) || die();

add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\enqueue_scripts' );

/**
 * Enqueue scripts and styles
 */
function enqueue_scripts() {
	wp_enqueue_style(
		'twentyseventeen-parent-style',
		get_template_directory_uri() . '/style.css'
	);
}
