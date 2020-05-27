<?php
$GLOBALS['pagetitle'] = wp_get_document_title();
global $wporg_global_header_options;
if ( !isset( $wporg_global_header_options['in_wrapper'] ) )
	$wporg_global_header_options['in_wrapper'] = '';
$wporg_global_header_options['in_wrapper'] .= '<a class="skip-link screen-reader-text" href="#pagebody">' . esc_html__( 'Skip to content', 'wporg-showcase' ) . '</a>';

$prefix = is_ssl() ? 'https://' : 'http://s.';
wp_enqueue_style( 'blog-wp4', $prefix.'wordpress.org/style/blog-wp4.css', array(), 4 );
wp_enqueue_style( 'showcase', get_stylesheet_uri(), array(), 19 );
require WPORGPATH . 'header.php';
?>
<div id="headline">
	<div class="wrapper">
		<a id="wpsc-mobile-menu-button" class="" href="#" onclick="toggle_wpsc_mobile_menu();"></a>
		<h1><?php _e( 'WordPress Website Showcase', 'wporg-showcase' ); ?></h1>
	</div>
</div>
