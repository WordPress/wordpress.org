<?php
namespace WordPressdotorg\MU_Plugins\Site_Branding;
/**
 * Plugin Name: WordPress.org Site Branding
 * Description: Ensures that <title> tags on WordPress.org always include WordPress.
 */

add_filter( 'document_title_parts', __NAMESPACE__ . '\document_title_parts', 100 );
function document_title_parts( $parts ) {
	// In some places on WordPress.org the document title is used within the theme, don't affect title calls after the </head>.
	if ( did_action( 'wp_body_open' ) ) {
		return $parts;
	}

	$combined = implode( ' ', $parts );

	// Ensure that 'WordPress' is present in the title of the URL
	if ( false === strpos( $combined, 'WordPress' ) ) {
		$parts['wporg-suffix'] = get_wordpress_brand();
	}

	return $parts;
}

add_filter( 'bbp_title', __NAMESPACE__ . '\bbp_title', 100 );
function bbp_title( $title ) {
	return $title . get_wordpress_brand();
}

/**
 * Return the 'Brand' of the WordPress.org site.
 * 
 * This is "WordPress.org" or a localised variant such as "WordPress Deutch".
 */
function get_wordpress_brand() {
	global $rosetta;

	if ( ! isset( $rosetta ) ) {
		return 'WordPress.org';
	}

	$root_id = $rosetta->get_root_site_id();
	$name    = get_blog_option( $root_id, 'blogname' );

	if ( false !== strpos( $name, 'WordPress' ) ) {
		return $name;
	}

	return "WordPress.org $name";
}