<?php

$stylesheet = get_stylesheet_uri();
if ( is_rtl() ) {
	$stylesheet = str_replace( '.css', '-rtl.css', $stylesheet );
}

wp_enqueue_style( 'rosetta', $stylesheet, array(), 18 );

if ( is_locale_css() ) {
	wp_enqueue_style( 'rosetta-locale', get_locale_css_url(), array(), 1 );
}

require WPORGPATH . 'header.php';
