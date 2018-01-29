<?php
$GLOBALS['pagetitle'] = wp_get_document_title();

$prefix = is_ssl() ? 'https://' : 'http://s.';
wp_enqueue_style( 'blog-wp4', $prefix.'wordpress.org/style/blog-wp4.css', array(), 4 );
wp_enqueue_style( 'showcase', $prefix.'wordpress.org/wp-content/themes/pub/wporg-showcase/style.css', array(), 16 );
require WPORGPATH . 'header.php';
?>
<div id="headline">
	<div class="wrapper">
		<a id="wpsc-mobile-menu-button" class="" href="#" onclick="toggle_wpsc_mobile_menu();"></a>
		<h2><?php _e( 'Showcase', 'wporg-showcase' ); ?></h2>
	</div>
</div>
