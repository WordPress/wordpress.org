<?php
namespace WordPressdotorg\Plugin_Directory\API\Routes;

use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\API\Base;
use WordPressdotorg\Plugin_Directory\Tools;
use WordPressdotorg\Plugin_Directory\Template;

/**
 * An API endpoint for fetching a plugin zip file.
 *
 * @package WordPressdotorg_Plugin_Directory
 */
class Plugin_Zip extends Base {

	public function __construct() {
		register_rest_route( 'plugins/v1', '/plugin/(?P<plugin_slug>[^/]+)/zip/(?P<zip_hash>[^/]+)', array(
			'methods'             => array( \WP_REST_Server::READABLE, \WP_REST_Server::CREATABLE ),
			'callback'            => array( $this, 'output_zip' ),
			// Note: publicly accessible, since Playground does not inclue cookie credentials in its request.
			'permission_callback' => '__return_true',
			'args'                => array(
				'plugin_slug' => array(
					'validate_callback' => array( $this, 'validate_plugin_slug_callback' ),
				),
			)
		) );
	}

	/**
	 * Endpoint to output an attached zip file with CORS headers for Playground.
	 *
	 * @param \WP_REST_Request $request The Rest API Request.
	 * @return bool True if the favoriting was successful.
	 */
	public function output_zip( $request ) {
		$plugin = Plugin_Directory::get_plugin_post( $request['plugin_slug'] );
		$zip_hash = strval( $request['zip_hash'] );

		if ( ! $zip_hash ) {
			return new \WP_Error( 'invalid_id', 'File not found', array( 'status' => 404 ) );
		}

		// Surely there's a better way to confirm an attachment ID has a given mime type and belongs to a post?
		foreach ( get_attached_media( 'application/zip', $plugin ) as $zip_file ) {
			$file = get_attached_file( $zip_file->ID );
			// Should use a secure hash compare here - is there none in core?!
			if ( wp_hash( $file, 'nonce' ) === $zip_hash ) {
				if ( file_exists( $file ) ) {
					header( 'Access-Control-Allow-Origin: https://playground.wordpress.net' );
					header('Content-Description: File Transfer');
					header('Content-Type: application/zip');
					header('Content-Disposition: attachment; filename="'.basename($file).'"');
					header('Content-Length: ' . filesize($file));
					readfile($file);
					die();
				}
			}
		}
	
		// If we got this far there was something wrong with the attachment.
		return new \WP_Error( 'invalid_file', 'Invalid file', array( 'status' => 500 ) );
	}

}
