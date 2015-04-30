<?php
/**
 * WP Super Cache puts http and https requests in the same bucket which
 * generates mixed content warnings and generat breakage all around. The
 * following makes sure only HTTPS requests are cached.
 */
add_action( 'init', function() {
	if ( ! is_ssl() )
		define( 'DONOTCACHEPAGE', true );
});
