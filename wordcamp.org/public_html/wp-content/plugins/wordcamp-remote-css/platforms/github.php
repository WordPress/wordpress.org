<?php

namespace WordCamp\RemoteCSS;
use WP_Error;

defined( 'WPINC' ) or die();

/*
 * @todo -- Once another platform has been added and you can see the similarities, this should probably be
 * refactored to extend an abstract class or implement an interface.
 */

add_filter( 'wcrcss_trusted_remote_hostnames', __NAMESPACE__ . '\whitelist_trusted_hostnames' );
add_filter( 'wcrcss_validate_remote_css_url',  __NAMESPACE__ . '\convert_to_api_urls'         );
add_filter( 'pre_http_request',                __NAMESPACE__ . '\authenticate_requests', 10, 3 );
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
 * Add authentication parameters to GitHub API requests
 *
 * This allows us to make 5k requests per hour, instead of just 60.
 *
 * @param false|array|WP_Error $preempt      See `pre_http_request`
 * @param array                $request_args
 * @param string               $request_url
 *
 * @return false|array|WP_Error
 */
function authenticate_requests( $preempt, $request_args, $request_url ) {
	$parsed_url = parse_url( $request_url );

	/*
	 * SECURITY: Make sure we're only authorizing the requests we're intending to, to avoid the possibility of
	 * the keys being used for another purpose. That's not likely, but it's better to err on the side of caution.
	 */
	$is_relevant_request = GITHUB_API_HOSTNAME === $parsed_url['host']                 &&
	                       'GET'               === $request_args['method']             &&
	                       '/repos'            === substr( $parsed_url['path'], 0, 6 ) &&
	                       '.css'              === substr( $parsed_url['path'], strlen( $parsed_url['path'] ) - 4 );

	if ( $is_relevant_request ) {
		if ( isset( $parsed_url['query'] ) ) {
			parse_str( $parsed_url['query'], $request_query_params );
		} else {
			$request_query_params = array();
		}

		$has_authentication_params = array_key_exists( 'client_id',     $request_query_params ) &&
		                             array_key_exists( 'client_secret', $request_query_params );

		if ( ! $has_authentication_params ) {
			$request_url = add_query_arg(
				array(
					'client_id'     => REMOTE_CSS_GITHUB_ID,
					'client_secret' => REMOTE_CSS_GITHUB_SECRET
				),
				$request_url
			);

			$preempt = wp_remote_get( $request_url, $request_args );
		}
	}

	return $preempt;
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
