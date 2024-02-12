<?php
// phpcs:disable

// Avoid PHP Warnings from 'unexpected' tag attributes.
libxml_use_internal_errors( true );

function domdocument_from_url( $url ) {
	$html = file_get_contents( $url );

	/*
	 * Escape HTML within Javascript strings.
	 * DomDocument doesn't handle HTML tags within Javascript strings.
	 * See https://stackoverflow.com/questions/40703313/php-domdocument-errors-while-parsing-unescaped-strings
	 */
	$html = preg_replace_callback(
		'!<script([^>]+)>(.*?)</script>!ism',
		function( $m ) {
			$escaped = $m[2];
			$escaped = str_replace( array( '<', '>' ), array( '\x3C',  '\x3E' ), $escaped );
			return "<script{$m[1]}>{$escaped}</script>";
		},
		$html
	);

	// Ensure it's treated as UTF8, we'll assume if there's no <body> tag it's just a HTML blob.
	if ( ! strpos( $html, '<body' ) ) {
		$html = '<!DOCTYPE html>
			<html xmlns="http://www.w3.org/1999/xhtml">
			<head>
				<meta charset="UTF-8">
			</head>
			<body>' . $html . '</body></html>';
	}

	$doc = new DOMDocument();
	$doc->validateOnParse = false;

	$doc->loadHTML( $html );

	// Ensure it's treated as UTF-8, It should already be detected as such, but this is just in case.
	$doc->encoding = 'utf-8';

	return $doc;
}

function domdocument_for_trac() {
	$doc = new DOMDocument();
	$doc->formatOutput = true;

	$doc->loadHTML( '<!DOCTYPE html>
	<html xmlns="http://www.w3.org/1999/xhtml" xmlns:py="http://genshi.edgewall.org/" py:strip=""></html>' );

	// Set the encoding to UTF-8 to allow unicode characters in the output. This avoids them being escaped.
	$doc->encoding = 'utf-8';

	return $doc;
}

function save_domdocument( $file, $dom ) {

	// Strip all comments out of the template.
	$xpath = new DOMXPath($dom);
	foreach ( $xpath->query( '//comment()' ) as $comment ) {
		$comment->parentNode->removeChild( $comment );
	}

	$html = $dom->saveXML();

	// Remove the XML header
	$html = preg_replace( "#^<\?xml.+>\n?#i",  '', $html );

	// Remove CDATA tags from <style>
	$html = preg_replace( '#<style([^>]*)><!\[CDATA\[(.+?)\]\]></style>#ism', "<style$1>$2</style>", $html );

	// Escape or Remove CDATA tags from <script>. Trac requires this for inline scripts, but not for non-javascript tags.
	$html = preg_replace_callback(
		'#<script(?P<attr>[^>]*)><!\[CDATA\[(?P<code>.+?)\]\]></script>#ism',
		function( $m ) {
			$attr = $m['attr'];
			$code = $m['code'];
			$type = '';
			if ( preg_match( '/type=(["\'])(?P<type>[^\'"]+)\\1/', $attr, $mtype ) ) {
				$type = $mtype['type'];
			}

			// For non-javascript, remove the CDATA tags.
			if ( $type && in_array( strtolower( $type ), [ 'importmap', /* 'module' */ ] ) ) {
				return "<script{$attr}>{$code}</script>";
			}

			// If javascript, or unknown, escape it.
			return "<script{$attr}>//<![CDATA[\n{$code}\n//]]></script>";
		},
		$html
	);

	// Remove trailing whitespace.
	$html = preg_replace( '#(\S)\s+$#m', '$1', $html );

	// Standardise container IDs, to make diffs simpler.
	// The hash being replaced here is the result of `uniqid()` to privide unique element classes.
	$html = preg_replace_callback(
		'!(?P<class>(?P<prefix>wp-container|modal|wp-elements)-(?P<id>[a-f0-9]{13,14}))(?P<suffix>[^a-f0-9])!',
		function( $m ) {
			static $ids = [];
			static $next_id = 1;

			$prefix_id = $ids[ $m['class'] ] ?? ( $ids[ $m['class'] ] = $m['prefix'] . '-trac-' . ( $next_id++ ) );

			return $prefix_id . $m['suffix'];
		},
		$html
	);

	/*
	 * Use CDN assets, to avoid CORS issues.
	 * Until https://github.com/WordPress/wporg-mu-plugins/pull/430 is resolved.
	 */
	$html = preg_replace_callback(
		'!(?P<url>https:[\\\/]+wordpress.org[\\\/]+wp-(includes|content)[\\\/]+[^\'"]+)!i',
		function( $m ) {
			$url     = $m['url'];
			$escaped = false !== strpos( $url, '\/' );

			if ( $escaped ) {
				$url = stripslashes( $url );
			}

			$url  = str_replace( 'wordpress.org', 's.w.org', $url );
			$hash = md5( file_get_contents( $url ) );

			if ( preg_match( '/([?&;](ver|v)=[^&]+)/i', $url, $m ) ) {
				$url = str_replace( $m[0], $m[0] . '-' . $hash, $url );
			} else {
				$url .= ( strpos( $url, '?' ) ? '&amp;' : '?' ) . 'ver=' . $hash;
			}

			if ( $escaped ) {
				$url = addcslashes( $url, '/' );
			}

			return $url;
		},
		$html
	);

	return file_put_contents( $file, $html );
}

$header = domdocument_from_url( 'https://wordpress.org/wp-json/global-header-footer/v1/header' );
$footer = domdocument_from_url( 'https://wordpress.org/wp-json/global-header-footer/v1/footer' );

if ( ! $header || ! $footer ) {
	echo "Could not fetch header or footer.";
	exit( 1 );
}

// wporg-head.html
// Just the <head> elements children.
$wporg_head = domdocument_for_trac();
$html_node  = $wporg_head->getElementsByTagName( 'html' )[0];
foreach ( $header->getElementsByTagName( 'head' )[0]->childNodes as $node ) {
	// Skip <title>
	if (
		$node instanceOf DomElement &&
		'title' === $node->tagName
	) {
		continue;
	}

	// Skip <meta name="generator">
	if (
		$node instanceOf DomElement &&
		'meta' === $node->tagName &&
		'generator' === $node->getAttribute( 'name' )
	) {
		continue;
	}

	$html_node->appendChild( $wporg_head->importNode( $node, true ) );
}

// Swap out the shortcut icon for a Trac one. #6072
$icon_url = 'https://s.w.org/style/trac/common/trac.ico';
foreach ( ( new DOMXPath( $wporg_head ) )->query( '//link[@rel="icon"]' ) as $icon ) {
	$hash = md5( file_get_contents( $icon_url ) );
	$icon->setAttribute( 'href', $icon_url . '?v=' . $hash );
}

save_domdocument( __DIR__ . '/wporg-head.html', $wporg_head );

// wporg-header.html
// <header/> within <html>.
$wporg_header = domdocument_for_trac();
$html_node    = $wporg_header->getElementsByTagName( 'html' )[0];
foreach ( $header->getElementsByTagName( 'body' )[0]->childNodes as $node ) {
	$html_node->appendChild( $wporg_header->importNode( $node, true ) );
}

// Alter the search form to search Trac
$search_form = $wporg_header->getElementsByTagName( 'form' )[0];
$search_form->setAttribute( 'method', 'GET' );
$search_form->setAttribute( 'action', '/search' );
$search_label = $search_form->getElementsByTagName( 'label' )[0];
$search_label->nodeValue = "";
$search_label->appendChild($wporg_header->createTextNode('Search Trac'));

$search_field = $search_form->getElementsByTagName( 'input' )[0];
$search_field->setAttribute( 'name', 'q' );

// Direct the skip link to the correct element
$skip_link = $wporg_header->getElementById( 'wporg-skip-link' );
if ( $skip_link ) {
	$skip_link->setAttribute( 'href', '#main' );
	$skip_link->setAttribute( 'data-selector', '#main' );
	$skip_link->setAttribute( 'tabindex', '' );
}

save_domdocument( __DIR__ . '/wporg-header.html', $wporg_header );

// wporg-footer.html
// Everything within <body>, but not body or jQuery.
$wporg_footer = domdocument_for_trac();
$html_node    = $wporg_footer->getElementsByTagName( 'html' )[0];
foreach ( $footer->getElementsByTagName( 'body' )[0]->childNodes as $node ) {
	// Exclude jQuery 3.x, Trac has it's own 1.x bundled.
	if (
		$node instanceof DomElement &&
		'script' === $node->tagName &&
		false !== stripos( $node->getAttribute( 'src' ), 'jquery' )
	) {
		continue;
	}

	$html_node->appendChild( $wporg_footer->importNode( $node, true ) );
}
save_domdocument( __DIR__ . '/wporg-footer.html', $wporg_footer );
