<?php
namespace WordPressdotorg\Trac\Watcher\Trac;

add_action( 'import_trac_feeds', function() {
	// Trac RSS feed import from profiles.w.org to be moved here.
} );

function format_trac_markup( $message ) {
	$message = esc_html( $message );
	$message = preg_replace( '!`(.*?)`!i', '<code>$1</code>', $message );
	$message = preg_replace( '!{{{(.*?)}}}!sm', '<code>$1</code>', $message );

	$message = preg_replace( '!\[([^] ]+) ([^]]+)\]!i', '<a href="$1">$2</a>', $message );

	// Escape shortcodes, but that takes out changesets..
	// $message = str_replace( [ '[', ']'], [ '[[', ']]' ], $message );

	// Might need to disable this, or escape more things prior to it.
	$message = apply_filters( 'the_content', $message );
	$message = make_clickable( $message );

	return $message;
}