<?php

if ( FEATURE_2021_GLOBAL_HEADER_FOOTER ) {
	echo do_blocks( '<!-- wp:wporg/global-header /-->' );
} else {
	global $wporg_global_header_options;
	if ( !isset( $wporg_global_header_options['in_wrapper'] ) )
		$wporg_global_header_options['in_wrapper'] = '';
	$wporg_global_header_options['in_wrapper'] .= '<a class="skip-link screen-reader-text" href="#pagebody">' . esc_html__( 'Skip to content', 'wporg-showcase' ) . '</a>';
	require WPORGPATH . 'header.php';
}

$prefix = is_ssl() ? 'https://' : 'http://s.';
wp_enqueue_style( 'blog-wp4', $prefix.'wordpress.org/style/blog-wp4.css', array(), 4 );
wp_enqueue_style( 'showcase', get_stylesheet_uri(), array(), 20 );

?>

<div id="headline">
	<div class="wrapper">
		<a id="wpsc-mobile-menu-button" class="" href="#" onclick="toggle_wpsc_mobile_menu();"></a>
		<h1><a href="<?php echo home_url('/'); ?>"><?php _e( 'WordPress Website Showcase', 'wporg-showcase' ); ?></a></h1>
	</div>
</div>
