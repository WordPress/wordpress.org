<?php

namespace WordPressdotorg\Plugin_Directory\Block_JSON;

use WP_Error;

defined( 'WPINC' ) || die();

/**
 * Class Parser
 *
 * @package WordPressdotorg\Plugin_Directory\Block_JSON
 */
class Parser {
	/**
	 * A location resource must be pointing to a file named this.
	 *
	 * @const string
	 */
	const REQUIRED_FILENAME = 'block.json';

	/**
	 * Parse the JSON content of a given resource into a PHP object.
	 *
	 * @param array $resource An associative array with one item where the key specifies the type of resource and the
	 *                        value represents the resource as a string. Valid types are `url`, `file`, and `content`.
	 *                        In this case, the value of `content` is expected to be a raw string of JSON, while the
	 *                        other two are expected to be the location of a file containing the JSON.
	 *
	 * @return object|WP_Error An object if the parse was successful, otherwise a WP_Error.
	 */
	public static function parse( array $resource ) {
		reset( $resource );
		$type   = key( $resource );
		$handle = current( $resource );

		switch ( $type ) {
			case 'url':
				$content = self::extract_content_from_url( $handle );
				break;
			case 'file':
				$content = self::extract_content_from_file( $handle );
				break;
			case 'content':
				$content = $handle;
				break;
			default:
				$content = new WP_Error(
					'no_valid_resource',
					__( 'No valid resource type was given.', 'wporg-plugins' )
				);
				break;
		}

		if ( is_wp_error( $content ) ) {
			return $content;
		}

		return self::parse_content( $content );
	}

	/**
	 * Get the contents of a block.json file via an HTTP request to a URL.
	 *
	 * @param string $url
	 *
	 * @return string|WP_Error
	 */
	protected static function extract_content_from_url( $url ) {
		$url = esc_url_raw( $url );

		$filename_length = strlen( self::REQUIRED_FILENAME );
		if ( strtolower( substr( $url, - $filename_length ) ) !== self::REQUIRED_FILENAME ) {
			return new WP_Error(
				'resource_url_invalid',
				sprintf(
					/* translators: %s: file name */
					__( 'URL must end in %s!', 'wporg-plugins' ),
					'<code>' . self::REQUIRED_FILENAME . '</code>'
				)
			);
		}

		$response      = wp_safe_remote_get( $url );
		$response_code = wp_remote_retrieve_response_code( $response );

		if ( is_wp_error( $response ) ) {
			return $response;
		} elseif ( 200 !== $response_code ) {
			return new WP_Error(
				'resource_url_unexpected_response',
				__( 'URL returned an unexpected status code.', 'wporg-plugins' ),
				array(
					'status' => $response_code,
				)
			);
		}

		return wp_remote_retrieve_body( $response );
	}

	/**
	 * Get the contents of a block.json file via a path in the filesystem.
	 *
	 * @param string $file
	 *
	 * @return string|WP_Error
	 */
	protected static function extract_content_from_file( $file ) {
		$filename_length = strlen( self::REQUIRED_FILENAME );
		if ( strtolower( substr( $file, - $filename_length ) ) !== self::REQUIRED_FILENAME ) {
			return new WP_Error(
				'resource_file_invalid',
				sprintf(
					/* translators: %s: file name */
					__( 'File must be named %s!', 'wporg-plugins' ),
					'<code>' . self::REQUIRED_FILENAME . '</code>'
				)
			);
		}

		if ( ! is_readable( $file ) ) {
			return new WP_Error(
				'resource_file_unreadable',
				__( 'The file could not be read.', 'wporg-plugins' ),
				array(
					'file' => $file,
				)
			);
		}

		$content = file_get_contents( $file );

		if ( false === $content ) {
			return new WP_Error(
				'resource_file_failed_retrieval',
				__( 'Could not get the contents of the file.', 'wporg-plugins' ),
				array(
					'file' => $file,
				)
			);
		}

		return $content;
	}

	/**
	 * Parse a JSON string into an object, and handle parsing errors.
	 *
	 * @param string $content
	 *
	 * @return object|WP_Error
	 */
	protected static function parse_content( $content ) {
		$parsed = json_decode( $content );
		$error  = json_last_error_msg();

		// TODO Once we are on PHP 7.3 we can use the JSON_THROW_ON_ERROR option and catch an exception here.
		if ( 'No error' !== $error ) {
			return new WP_Error(
				'json_parse_error',
				sprintf(
					__( 'JSON Parser: %s', 'wporg-plugins' ),
					esc_html( $error )
				),
				array(
					'error_code' => json_last_error(),
				)
			);
		}

		return $parsed;
	}
}
