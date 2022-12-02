<?php

namespace WordPressdotorg\Forums;

class Blocks {
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	public function enqueue_scripts() {
		wp_enqueue_script( 'wporg-bbp-blocks', plugins_url( '/js/blocks.js', __DIR__ ), array( 'wp-block-editor', 'jquery' ), filemtime( dirname( __DIR__ ) . '/js/blocks.js' ), true );
	}
}
