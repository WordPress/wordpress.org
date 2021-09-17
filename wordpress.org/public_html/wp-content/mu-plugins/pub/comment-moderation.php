<?php
/**
 * Plugin Name: Comment Moderation Customizations
 * Description: Miscellaneous comment moderation customizations.
 */


/**
 * Adjusts the max number of links permitted in comments to disregard the
 * number of whitelisted links.
 *
 * Permits whitelisted URLs from counting towards the comment_max_links
 * configured limit that triggers moderation of the comment.
 *
 * @param int    $num_links The number of links found.
 * @param string $url       Comment author's URL. Included in allowed links total.
 * @param string $comment   Content of the comment.
 * @return int
 */
function wporg_comment_max_links_url( $num_links, $url, $comment ) {

	/******* START CONFIG *******/

	// Get whitelisted URLs.
	// Note: unnecessary to specify subdomains, do not include trailing slash.
	$whitelist_urls = array(
		'wordpress.org',
		'github.com/WordPress',
	);

	// Prevent abuse by enforcing a max link limit regardless of whitelist.
	$max_max_links = 15;

	/******* END CONFIG *******/


	// Get comment max links.
	$max_links = get_option( 'comment_max_links' );

	// Bail if the number of links doesn't exceed the maximum.
	if ( $num_links < $max_links ) {
		return $num_links;
	}

	// In the event site sets comment_max_links higher than max_max_links,
	// adjust max_max_links to be 15 higher than comment_max_links.
	$max_max_links = ( $max_links > $max_max_links ) ? ( $max_links + 15 ) : $max_max_links;

	// Bail if the number of links exceeds the true max.
	if ( $num_links >= $max_max_links ) {
		return $num_links;
	}

	// Bail if no whitelisted URLs are defined.
	if ( ! $whitelist_urls ) {
		return $num_links;
	}

	// Check if any whitelisted URLs are present in comment.
	foreach ( $whitelist_urls as $url ) {

		// Count the number of occurrences of this particular whitelisted URL.
		$num_whitelist_links = preg_match_all( '%<a [^>]*href=([\'\"])https?://([^/>]+\.)?' . preg_quote( $url, '%' ) . '(/|\\1)%i', $comment, $out );

		// Increase the limit by the number of whitelisted URLs (so that they don't
		// count against the limit).
		$num_links -= $num_whitelist_links;

		// Stop if there are enough whitelisted links to bring the number of
		// non-whitelisted links below the max.
		if ( $num_links < $max_links ) {
			break;
		}

	}

	return $num_links;
}

add_filter( 'comment_max_links_url', 'wporg_comment_max_links_url', 10, 3 );
