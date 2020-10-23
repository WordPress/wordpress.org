<?php
/*
 * Plugin Name: Trac Links
 * Plugin URI: https://wordpress.org/
 * Description: Link ticket and changeset numbers to core.trac.
 * Version: 1.0
 * Author: WordPress.org
 * Author URI: https://wordpress.org/
 */

add_filter( 'the_content', 'markup_wporg_links', 0, 1 );
add_filter( 'comment_text', 'markup_wporg_links', 0, 1 );

function markup_wporg_links( $content ) {
	$url = parse_url( home_url( '/' ) );
	$url = untrailingslashit( $url['host'] . $url['path'] );

	switch( $url ) {
		// Don't link on these sites.
		case 'make.wordpress.org/cli':
			return $content;

		// Link to meta trac.
		case 'make.wordpress.org/meta':
		case 'make.wordpress.org/community':
			$trac = 'meta';
			break;

		// Link to core trac by default.
		default:
			$trac = 'core';
			break;
	}

	$tracs = 'core|blackberry|nokia|webos|plugins|bbpress|buddypress|supportpress|glotpress|backpress|windows|themes|meta';

	$find = array(
		'/(\s|^|\()(#(\d{4,5})-(' . $tracs . '))(\b|$)/im', // trac ticket #1234-plugins in http://plugins.trac.wordpress.org/ticket/1234
		'/(\s|^|\()(r(\d{4,5})-(' . $tracs . '))(\b|$)/im', // changeset r1234-plugins in http://plugins.trac.wordpress.org/changeset/1234
		'/(\s|^|\()(#(' . $tracs . ')(\d{4,5}))(\b|$)/im', // trac ticket #1234-plugins in http://plugins.trac.wordpress.org/ticket/1234
		'/(\s|^|\()(r(' . $tracs . ')(\d{4,5}))(\b|$)/im', // changeset r1234-plugins in http://plugins.trac.wordpress.org/changeset/1234
		'/(\s|^|\()(#(\d{4,5}))(\b|$)/im', // core trac ticket #1234 in http://core.trac.wordpress.org/ticket/1234
		'/(\s|^|\()(r(\d{4,5}))(\b|$)/im', // core changeset r1234 in http://core.trac.wordpress.org/changeset/1234
		'/(?<!\w)\[(\d{4,5})\](?!\w)/im', // core changeset [12345]
		'/(?<!\w)\[(\d{4,5})-(\d{4,5})\](?!\w)/im', // core log [12345-54321]
		'/(\s|^|\()(diff:@(\d{4,5}):(\d{4,5}))(\b|$)/im', // core diff diff-core:@20:30 https://core.trac.wordpress.org/changeset?new=30&old=20
	);

	$replace = array(
		'$1<a href="https://$4.trac.wordpress.org/ticket/$3">$2</a>', // trac ticket
		'$1<a href="https://$4.trac.wordpress.org/changeset/$3">$2</a>', // trac changeset
		'$1<a href="https://$3.trac.wordpress.org/ticket/$4">$2</a>', // trac ticket
		'$1<a href="https://$3.trac.wordpress.org/changeset/$4">$2</a>', // trac changeset
		'$1<a href="https://'. $trac .'.trac.wordpress.org/ticket/$3">$2</a>', // core ticket
		'$1<a href="https://'. $trac .'.trac.wordpress.org/changeset/$3">$2</a>', // core changeset
		'<a href="https://'. $trac .'.trac.wordpress.org/changeset/$1">$0</a>', // trac changeset
		'<a href="https://'. $trac .'.trac.wordpress.org/log/?revs=$1-$2">$0</a>', // trac log
		'$1<a href="https://'. $trac .'.trac.wordpress.org/changeset?new=$4&old=$3">$2</a>', // diff
	);

	// Regex and loop based on convert_smilies();
	$arr = preg_split( '/(<.*>)/U', $content, -1, PREG_SPLIT_DELIM_CAPTURE );

	$tags_to_ignore = 'a|code|pre';
	$ignore_element = false;
	$len            = count( $arr );
	
	for ( $i = 0; $i < $len; $i++ ) {
		$text = $arr[ $i ];
		if ( ! $text ) {
			continue;
		}

		$is_tag = '<' === $text[0];

		// Is this a HTML tag we want to skip the contents of?
		if (
			$is_tag &&
			! $ignore_element &&
			preg_match( '/^<(' . $tags_to_ignore . ')[^>]*>/', $text, $matches )
		) {
			$ignore_element = $matches[1];
			$closing_tag    = '</' . $ignore_element . '>';
		}

		// Process this stand-alone chunk of text.
		if ( ! $is_tag && ! $ignore_element ) {
			$content = str_replace( $text, preg_replace( $find, $replace, $text ), $content );
		}

		// Did we leave the ignored tag?
		if ( $is_tag && $ignore_element && $closing_tag === $text ) {
			$ignore_element = false;
		}
	}

	return $content;
}
