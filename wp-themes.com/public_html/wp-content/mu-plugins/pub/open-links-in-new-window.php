<?php
namespace WordPressdotorg\Theme_Preview;
/**
 * Plugin Name: Open external links in new window/tab.
 * Description: Injects some JS to WordPress.org links to open in a new tab, and prevent navigation to other hostnames breaking preview iframes.
 */

add_action( 'wp_footer', function() {
	echo '<script>
( function() {
	var links = document.getElementsByTagName( "a" );
	for ( var i = 0; i < links.length; i++ ) {
		var link = links[i],
			url,
			hostname;

		try {
			url      = new URL( link.href, document.location.href );
			hostname = url.hostname;
			if ( "mailto:" === url.protocol ) {
				hostname = "mailto"; // not whitelisted hostname to fall through.
			}
		} catch( e ) {
			// Internet Explorer and invalid links, fall back to regex.
			if ( hostname = link.href.match( /^\s*(?:(?:https?:)?\/\/)([^/]+)(\/|$)/ ) ) {
				hostname = hostname[1];
			} else if ( "mailto" === link.href.substr( 0, 6 ) ) {
				hostname = "mailto"; // not whitelisted hostname to fall through.
			}
		}

		// Self links are allowed.
		if ( ! hostname || "wp-themes.com" === hostname ) {
			continue;
		}

		// Links to WordPress.org should be allowed, but open in a new window.
		if ( "wordpress.org" === hostname || ".wordpress.org" === hostname.substr(-14) ) {
			link.target = "_blank";
			continue;
		}

		// The link should not be followed, but the href is kept to allow for a[href^=] based styling.
		link.addEventListener( "click", function( e ) {
			e.preventDefault();
		} );
	}
} )();
</script>';
}, 9999 );
