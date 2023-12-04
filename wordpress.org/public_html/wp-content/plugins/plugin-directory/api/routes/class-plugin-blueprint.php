<?php
namespace WordPressdotorg\Plugin_Directory\API\Routes;

use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\API\Base;
use WordPressdotorg\Plugin_Directory\Tools;
use WordPressdotorg\Plugin_Directory\Template;

/**
 * An API endpoint for fetching a plugin blueprint file.
 *
 * @package WordPressdotorg_Plugin_Directory
 */
class Plugin_Blueprint extends Base {

	public function __construct() {
		register_rest_route( 'plugins/v1', '/plugin/(?P<plugin_slug>[^/]+)/blueprint.json', array(
			'methods'             => array( \WP_REST_Server::READABLE, \WP_REST_Server::CREATABLE ),
			'callback'            => array( $this, 'blueprint' ),
			'args'                => array(
				'plugin_slug' => array(
					'validate_callback' => array( $this, 'validate_plugin_slug_callback' ),
				),
			)
		) );
	}

	/**
	 * Endpoint to output a blueprint file contents.
	 *
	 * @param \WP_REST_Request $request The Rest API Request.
	 * @return bool True if the favoriting was successful.
	 */
	public function blueprint( $request ) {
		$plugin = Plugin_Directory::get_plugin_post( $request['plugin_slug'] );

        $blueprints = get_post_meta( $plugin->ID, 'assets_blueprints', true );
        // Note: for now, only use a file called `blueprint.json`.
        if ( !isset( $blueprints['blueprint.json'] ) ) {
            return false;
        }
        $blueprint = $blueprints['blueprint.json'];
        if ( !$blueprint || !isset( $blueprint['contents'] ) || !is_string( $blueprint['contents'] ) ) {
            return false;
        }

		// Configure this elsewhere?
		header( 'Access-Control-Allow-Origin: https://playground.wordpress.net' );

		// We already have a json string, returning would double-encode it.
		die( $blueprint['contents'] );
	}

}
