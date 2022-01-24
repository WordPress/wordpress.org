<?php

function domdocument_from_url( $url ) {
	$html = file_get_contents( $url );

	$doc = new DOMDocument();
	$doc->validateOnParse = false;

	$doc->loadHTML( $html );

	return $doc;
}

function domdocument_for_trac() {
	$doc = new DOMDocument();

	$doc->loadHTML( '<!DOCTYPE html>
	<html xmlns="http://www.w3.org/1999/xhtml" xmlns:py="http://genshi.edgewall.org/" py:strip=""></html>' );

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

	// Escape CDATA tags in <script>
	$html = preg_replace( '#<script([^>]*)><!\[CDATA\[(.+?)\]\]></script>#ism', "<script$1>//<![CDATA[\n$2\n//]]></script>", $html );

	// Remove trailing whitespace.
	$html = preg_replace( '#(\S)\s+$#m', '$1', $html );

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
$search_field = $search_form->getElementsByTagName( 'input' )[0];
$search_field->setAttribute( 'placeholder', 'Search Trac...' );
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
