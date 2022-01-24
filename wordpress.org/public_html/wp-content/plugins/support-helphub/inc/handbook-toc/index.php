<?php
/**
 * Plugin Name: WordPress.org Handbooks Table of Contents
 * Description: This is the WordPress.org Handbooks TOC plugin, inside the HelpHub repo.
 * 
 * @package HelpHub
 */

namespace WordPressdotorg\HelpHub;

add_action( 'init', function() {
	if ( ! class_exists( 'WPorg_Handbook_TOC' ) ) {
		require __DIR__ . '/table-of-contents.php';
	}

	$post_types = array_keys( helphub_post_types()->post_types );

	// And for pages too while we're at it.
	$post_types[] = 'page';

	new \WPorg_Handbook_TOC( $post_types,  array(
		'header_text' => __( 'Topics', 'wporg-forums' ),
		'top_text'    => __( 'Top &uarr;', 'wporg-forums' ),
	) );
} );

// Add our custom styling for the TOC.
add_filter( 'the_content', function( $contents ) {
	if (
		! wp_style_is( 'table-of-contents', 'enqueued' ) &&
		false !== strpos( $contents, '<div class="table-of-contents">' )
	) {
		wp_enqueue_style( 'table-of-contents', plugins_url( 'inc/handbook-toc/style.css', PLUGIN ), array(), filemtime( __DIR__ . '/style.css' ) );
	}

	return $contents;
}, 20 );