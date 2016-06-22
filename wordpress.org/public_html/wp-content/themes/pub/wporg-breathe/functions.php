<?php
namespace WordPressdotorg\Make\Breathe;

function styles() {
	wp_enqueue_style( 'p2-breathe', get_template_directory_uri() . '/style.css' );
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\styles', 9 );