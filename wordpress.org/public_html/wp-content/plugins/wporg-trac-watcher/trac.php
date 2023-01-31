<?php
namespace WordPressdotorg\Trac\Watcher\Trac;
use function WordPressdotorg\Trac\Watcher\SVN\get_svns;
use SimpleXmlElement;

add_action( 'import_trac_feeds', function() {

	foreach ( get_svns() as $svn ) {
		if ( empty( $svn['trac'] ) || empty( $svn['trac_table'] ) ) {
			continue;
		}

		import_trac_feed( $svn );
	}
} );

function import_trac_feed( $svn ) {
	global $wpdb;

	$feed_url = $svn['trac'] . '/timeline?ticket=on&changeset=on&milestone=on&wiki=on&max=50&daysback=5&format=rss';

	$feed = wp_remote_retrieve_body( wp_remote_get( $feed_url, array( 'timeout' => 60 ) ) );
	if ( ! $feed ) {
		return;
	}

	$xml = new SimpleXmlElement( $feed );
	if ( ! isset( $xml->channel->item ) ) {
		return;
	}

	$trac_table = $svn['trac_table']; // Not user input, safe.

	foreach ( $xml->channel->item as $item ) {
		$dc     = $item->children( 'http://purl.org/dc/elements/1.1/' );
		$md5_id = md5( strip_tags( $item->title . $dc->creator . $item->pubDate ) );

		if ( $wpdb->get_var( $wpdb->prepare( "SELECT md5_id FROM {$trac_table} WHERE md5_id = %s LIMIT 1", $md5_id ) ) ) {
			// if this entry is already in our database, that means all the previous ones should be too
			break;
		}

		$description = (string) $item->description;
		// Trac RSS feeds include a `…` in the changes list..
		$description = str_replace( '<li>…</li>', '', $description );
		$description = trim( strip_tags( $description, '<a><strike>' ) );

		$fields = [
			'md5_id'      => $md5_id,
			'description' => $description,
			'summary'     => (string) $item->summary,
			'category'    => (string) $item->category,
			'username'    => (string) $dc->creator,
			'link'        => (string) $item->link,
			'pubdate'     => gmdate( 'Y-m-d H:i:s', strtotime( (string) $item->pubDate ) ),
			'title'       => (string) $item->title,
		];

		if ( 'plugins' === $svn['slug'] && 'changeset' === (string) $item->category ) {
			// The slug is the first line before the first '/'.
			$fields['slug'] = explode( '/', explode( "\n", $description )[0] )[0];
		}

		$wpdb->insert(
			$trac_table,
			$fields
		);
	}
}

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