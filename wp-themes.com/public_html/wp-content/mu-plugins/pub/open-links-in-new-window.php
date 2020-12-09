<?php
namespace WordPressdotorg\Theme_Preview;
/**
 * Plugin Name: Open external links in new window/tab.
 * Description: Injects some JS to cause all external links to open in a new tab, to work around X-Frame-Options (and presenting something as WordPress.org that isn't).
 */

add_action( 'wp_footer', function() {
	echo '<script>
( function( base ) {
	var links = document.getElementsByTagName( "a" );
	for ( var i = 0; i < links.length; i++ ) {
		var href = links[i].getAttribute( "href" ).split( "#" )[0];
		if ( href && base !== href.substring( 0, base.length ) ) {
			links[i].target = "_blank";
		}
	}
} )( ' . wp_json_encode( home_url( '/' ) ) . ')
</script>';
} );