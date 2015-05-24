<?php

wp_enqueue_style( 'rosetta', get_stylesheet_uri(), array(), 17, 'screen' );

if ( is_rtl() ) {
	wp_enqueue_style( 'rosetta-rtl', get_locale_stylesheet_uri(), array( 'rosetta' ), 2, 'screen' );
}

if ( is_locale_css() ) {
	wp_enqueue_style( 'rosetta-locale', get_locale_css_url(), array(), 1 );
}

require WPORGPATH . 'header.php';

