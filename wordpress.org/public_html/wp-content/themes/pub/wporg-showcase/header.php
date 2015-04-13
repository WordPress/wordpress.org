<?php
global $pagetitle;

if ( is_single() )
	$pagetitle = 'Showcase &raquo; ' . wp_title( '', false );
elseif ( is_category() )
	$pagetitle = 'Showcase &raquo; Flavor &raquo; ' . wp_title( '', false );
elseif ( is_tag() )
	$pagetitle = 'Showcase &raquo; Tag &raquo; ' . wp_title( '', false );

$prefix = is_ssl() ? 'https://' : 'http://s.';
wp_enqueue_style( 'blog-wp4', $prefix.'wordpress.org/style/blog-wp4.css', array(), 4 );
wp_enqueue_style( 'showcase', $prefix.'wordpress.org/wp-content/themes/showcase/style.css', array(), 13 );
require WPORGPATH . 'header.php';
?>
<div id="headline">
	<div class="wrapper">
		<h2>Showcase</h2>
	</div>
</div>
