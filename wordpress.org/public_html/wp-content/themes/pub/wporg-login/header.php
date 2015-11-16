<?php
/**
 * The template for displaying the header.
 *
 * @package wporg-login
 */

global $pagetitle, $wporg_global_header_options;
$pagetitle = wp_title( '', false );
wp_enqueue_style( 'blog-wp4', 'https://wordpress.org/style/blog-wp4.css', array(), 12 );
wp_enqueue_style( 'theme', get_stylesheet_uri(), array(), filemtime( __DIR__ . '/style.css' ) );

$wporg_global_header_options = array(
	'menu' => '',
	'search' => '',
);

require WPORGPATH . 'header.php';
