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
	$html = $dom->saveXML();

	// Remove the XML header
	$html = preg_replace( "#^<\?xml.+>\n?#i",  '', $html );

	// Remove CDATA tags from <style>
	$html = preg_replace( '#<style([^>]*)><!\[CDATA\[(.+?)\]\]></style>#ism', "<style$1>$2</style>", $html );

	// Escape CDATA tags in <script>
	$html = preg_replace( '#<script([^>]*)><!\[CDATA\[(.+?)\]\]></script>#ism', "<script$1>//<![CDATA[\n$2\n//]]></script>", $html );

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
save_domdocument( __DIR__ . '/wporg-header.html', $wporg_header );

// wporg-footer.html
// Everything within <body>, but not body.
$wporg_footer = domdocument_for_trac();
$html_node    = $wporg_footer->getElementsByTagName( 'html' )[0];
foreach ( $footer->getElementsByTagName( 'body' )[0]->childNodes as $node ) {
	$html_node->appendChild( $wporg_footer->importNode( $node, true ) );
}
save_domdocument( __DIR__ . '/wporg-footer.html', $wporg_footer );
