<?php
/**
 * The template for displaying the header.
 *
 * @package wporg-login
 */
?>
<!doctype html>
<html class="no-js" <?php language_attributes(); ?>>
<head>
<meta charset="utf-8">
<meta http-equiv="x-ua-compatible" content="ie=edge">
<title><?php _e( 'WordPress.org Login', 'wporg' ); ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="dns-prefetch" href="//www.googletagmanager.com">

<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','GTM-P24PF4B');</script>

<?php wp_head(); ?>
</head>
<body <?php body_class( 'wp-core-ui login' ); ?>>
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-P24PF4B" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>

<div id="login">
	<h1><a href="https://wordpress.org/" title="WordPress.org" tabindex="-1"><?php _e( 'WordPress.org Login', 'wporg' ); ?></a></h1>
