<?php
/**
 * The template for displaying the header.
 *
 * @package wporg-login
 */

//global $pagetitle, $wporg_global_header_options;
//$pagetitle = wp_title( '', false );

wp_enqueue_style( 'normalize', get_template_directory_uri() . '/stylesheets/normalize.css', array(), filemtime( __DIR__ . '/style.css' ) );
wp_enqueue_style( 'custom-login', get_template_directory_uri() . '/stylesheets/login.css', array(), filemtime( __DIR__ . '/style.css' ) );
?>
<!doctype html>
<html class="no-js" lang="">
<head>
<meta charset="utf-8">
<meta http-equiv="x-ua-compatible" content="ie=edge">
<title>WordPress.org OAuth Login</title>
<meta name="description" content="">
<meta name="viewport" content="width=device-width, initial-scale=1">

<?php wp_head(); ?>
</head>
<body>

<div class="wrapper">
	<div class="login">
		<h1><a href="https://wordpress.org/" title="WordPress.org" tabindex="-1">WordPress.org Login</a></h1>