<?php

wp_enqueue_style( 'wporg', ( is_ssl() ? gp_url_ssl( gp_url_public_root() ) : gp_url_public_root() ) . 'gp-templates/style.css', array( 'base' ), '20150501' );

add_action( 'tmpl_load_locations', function( $locations, $template, $args, $template_path ) {
	$core_templates = GP_PATH . 'gp-templates/';
	require_once $core_templates . 'helper-functions.php';
	$locations[] = $core_templates;
	return $locations;
}, 50, 4 );

add_filter( 'gp_breadcrumb', function( $breadcrumb ) {
	$breadcrumb = preg_replace( '#<span class="separator">(.*?)</span>#', '', $breadcrumb, 1 );
	if ( false !== strpos( $breadcrumb, '<span class="active bubble">' ) )
		$breadcrumb = str_replace( '</span><span class="active bubble">', ' <span class="active bubble">', $breadcrumb ) . '</span>';
	return $breadcrumb;
}, 11 ); // After bubble is added by lamba() in gp-templates/project.php
