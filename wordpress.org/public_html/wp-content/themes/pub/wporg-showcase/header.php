<?php
$GLOBALS['pagetitle'] = wp_get_document_title();
$GLOBALS['wporg_global_header_options'] = array(
	'in_wrapper' => '<a class="skip-link screen-reader-text" href="#pagebody">' . esc_html( 'Skip to content', 'wporg-showcase' ) . '</a>',
);

$prefix = is_ssl() ? 'https://' : 'http://s.';
wp_enqueue_style( 'blog-wp4', $prefix.'wordpress.org/style/blog-wp4.css', array(), 4 );
wp_enqueue_style( 'showcase', $prefix.'wordpress.org/wp-content/themes/pub/wporg-showcase/style.css', array(), 17 );
require WPORGPATH . 'header.php';
?>
<div id="headline">
	<div class="wrapper">
		<a id="wpsc-mobile-menu-button" class="" href="#" onclick="toggle_wpsc_mobile_menu();"></a>
		<h2><?php _e( 'WordPress Website Showcase', 'wporg-showcase' ); ?></h2>
	</div>
</div>
