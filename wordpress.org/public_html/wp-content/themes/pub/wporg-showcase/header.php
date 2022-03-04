<?php

\WordPressdotorg\skip_to( '#pagebody' );

echo do_blocks( '<!-- wp:wporg/global-header /-->' );

wp_enqueue_style( 'blog-wp4', 'https://wordpress.org/style/blog-wp4.css', array(), 4 );
wp_enqueue_style( 'showcase', get_stylesheet_uri(), array( 'dashicons' ), 21 );

?>

<div id="headline">
	<div class="wrapper">
		<a id="wpsc-mobile-menu-button" class="" href="#" onclick="toggle_wpsc_mobile_menu();"></a>
		<h1><a href="<?php echo home_url('/'); ?>"><?php _e( 'WordPress Website Showcase', 'wporg-showcase' ); ?></a></h1>
	</div>
</div>
