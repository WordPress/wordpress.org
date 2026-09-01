<?php
/**
 * Plugin Name: WordPress.org Translator - GlotPress API
 * Plugin URI: https://github.com/Automattic/gp-extended-api-plugins
 * Description: Adds API endpoints needed by the WordPress.org Translator to GlotPress.
 * Version: 2020-11-13
*/

// Load the API endpoints once GlotPress is loaded.
add_action( 'gp_init', function() {
	require_once( __DIR__ . '/gp-translation-extended-api/gp-translation-extended-api.php' );
}, 1 );

// Set CORS headers..
add_action( 'gp_before_request', function( $class_name ) {
	if ( 'GP_Route_Translation_Extended' !== $class_name ) {
		return;
	}

	if ( empty( $_SERVER['HTTP_ORIGIN'] ) ) {
		return;
	}

	$host = parse_url( $_SERVER['HTTP_ORIGIN'], PHP_URL_HOST );
	if ( ! preg_match( '!^[^.]+\.wordpress\.org$!', $host ) ) {
		return;
	}

	header( 'Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN'] );
	header( 'Access-Control-Allow-Credentials: true' );
} );
