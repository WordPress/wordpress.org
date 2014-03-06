<?php

global $pagetitle;
$pagetitle = get_bloginfo( 'name', 'display' ) . wp_title( '&laquo;', false, 'left' );

$GLOBALS['wporg_global_header_options'] = array(
	'rosetta_site' => get_bloginfo( 'name', 'display' ),
	'search' => false,
	'menu' => wp_nav_menu( array( 'theme_location' => 'rosetta_main', 'container' => false, 'echo' => false ) ),
);

wp_enqueue_style( 'rosetta', get_bloginfo( 'stylesheet_url' ), array(), 14, 'screen' );
if ( is_locale_css() ) {
	wp_enqueue_style( 'rosetta-locale', get_locale_css_url(), array(), 1 );
}

require WPORGPATH . 'header.php';

