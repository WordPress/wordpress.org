<?php

wp_enqueue_style( 'wporg', gp_url_ssl( gp_url_public_root() ) . 'gp-templates/style.css', array( 'base' ), '20151123' );

add_action( 'tmpl_load_locations', function( $locations, $template, $args, $template_path ) {
	$core_templates = GP_PATH . 'gp-templates/';
	require_once $core_templates . 'helper-functions.php';
	$locations[] = $core_templates;
	return $locations;
}, 50, 4 );

