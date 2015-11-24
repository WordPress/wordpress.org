<?php

namespace WordCamp\RemoteCSS;

defined( 'WPINC' ) or die();

/*
 * @todo -- Once another platform has been added and you can see the similarities, this should probably be
 * refactored to extend an abstract class or implement an interface.
 */

add_filter( 'wcrcss_trusted_remote_hostnames', __NAMESPACE__ . '\whitelist_trusted_hostnames' );
add_filter( 'wcrcss_validate_remote_css_url',  __NAMESPACE__ . '\convert_to_api_urls'         );
add_filter( 'wcrcss_unsafe_remote_css',        __NAMESPACE__ . '\decode_api_response',  10, 2 );

/**
 * Add GitHub's hostnames to the whitelist of trusted hostnames
 *
 * @param array $hostnames
 *
 * @return array
 */
function whitelist_trusted_hostnames( $hostnames ) {
	return array_merge( $hostnames, array( 'github.com', 'raw.githubusercontent.com', GITHUB_API_HOSTNAME ) );
}

/**
 * Convert various GitHub URLs to their API equivalents
 *
 * We need to use the API to request the the file contents, because github.com shows the file embedded in an HTML
 * page, and raw.githubusercontent.come is cached and will often respond with stale content.
 *
 * @param string $remote_css_url
 *
 * @return string
 */
function convert_to_api_urls( $remote_css_url ) {
	$owner = $repository = $file_path = null;

	$parsed_url = parse_url( $remote_css_url );
	$path       = explode( '/', $parsed_url['path'] );

	if ( 'github.com' == $parsed_url['host'] ) {
		$owner      = $path[1];
		$repository = $path[2];
		$file_path  = implode( '/', array_slice( $path, 5 ) );
	} elseif ( 'raw.githubusercontent.com' == $parsed_url['host'] ) {
		$owner      = $path[1];
		$repository = $path[2];
		$file_path  = implode( '/', array_slice( $path, 4 ) );
	}

	if ( $owner && $repository && $file_path ) {
		$remote_css_url = sprintf(
			'https://%s/repos/%s/%s/contents/%s',
			GITHUB_API_HOSTNAME,
			$owner,
			$repository,
			$file_path
		);
	}

	return $remote_css_url;
}

/**
 * Decode the file contents from GitHub's API response
 *
 * @param string $response_body
 * @param string $remote_css_url
 *
 * @return string
 */
function decode_api_response( $response_body, $remote_css_url ) {
	if ( false !== strpos( $remote_css_url, GITHUB_API_HOSTNAME ) ) {
		$response_body = json_decode( $response_body );
		$response_body = base64_decode( $response_body->content );
	}

	return $response_body;
}
