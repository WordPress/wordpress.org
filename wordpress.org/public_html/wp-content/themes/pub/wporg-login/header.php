<?php
/**
 * The template for displaying the header.
 *
 * @package wporg-login
 */

$normalize_css = '/stylesheets/normalize.css';
wp_enqueue_style( 'normalize', get_template_directory_uri() . $normalize_css, array(), filemtime( __DIR__ . $normalize_css ) );

$login_css = '/stylesheets/login.css';
wp_enqueue_style( 'custom-login', get_template_directory_uri() . $login_css, array(), filemtime( __DIR__ . $login_css ) );
?>
<!doctype html>
<html class="no-js" lang="">
<head>
<meta charset="utf-8">
<meta http-equiv="x-ua-compatible" content="ie=edge">
<title><?php _e( 'WordPress.org Login' ); ?></title>
<meta name="description" content="">
<meta name="viewport" content="width=device-width, initial-scale=1">

<?php wp_head(); ?>
</head>
<body>

<div class="wrapper">
	<div class="login">
		<h1><a href="https://wordpress.org/" title="WordPress.org" tabindex="-1"><?php _e( 'WordPress.org Login' ); ?></a></h1>
