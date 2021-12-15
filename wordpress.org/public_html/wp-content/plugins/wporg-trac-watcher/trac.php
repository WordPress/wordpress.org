<?php
namespace WordPressdotorg\Trac\Watcher\Trac;

add_action( 'import_trac_feeds', function() {
	// Trac RSS feed import from profiles.w.org to be moved here.
} );

function format_trac_markup( $message ) {
	$message = esc_html( $message );

	// Convert some Trac markdown to HTML.
	$message = preg_replace( '!`(.*?)`!i', '<code>$1</code>', $message );
	$message = preg_replace( '!{{{(.*?)}}}!sm', '<code>$1</code>', $message );
	$message = preg_replace( '!\[([^] ]+) ([^]]+)\]!i', '<a href="$1">$2</a>', $message );

	// Mark up the text, using functions we want, rather than `the_content` as it has many filters that don't strictly apply.
	$message = wptexturize( $message );
	$message = wpautop( $message );
	$message = make_clickable( $message );

	// Link tickets and changesets.
	if ( function_exists( 'markup_wporg_links' ) ) {
		$message = markup_wporg_links( $message );
	}

	// Ensure nothing funny is in the output.
	$message = wp_kses_post( $message );

	return $message;
}