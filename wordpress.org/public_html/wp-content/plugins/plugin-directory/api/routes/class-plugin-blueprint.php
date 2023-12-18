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
			// Note: the zip part of the endpoint is also public, since playground requests blueprints without cookie credentials
			'permission_callback' => '__return_true',
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

		// Direct zip preview for plugin reviewers
		if ( $request->get_params('zip_id') ) {
			$zip_url = sprintf( 'https://wordpress.org/plugins/wp-json/plugins/v1/plugin/%s/zip/%d', $request['plugin_slug'], $request['zip_id'] );
			$zip_blueprint =<<<EOF
{
    "landingPage": "/wp-admin/",
    "preferredVersions": {
        "php": "8.0",
        "wp": "latest"
	},
    "phpExtensionBundles": [
        "kitchen-sink"
    ],
    "features": {
        "networking": true
    },
    "steps": [
        {
            "step": "installPlugin",
            "pluginZipFile": {
                "resource": "url",
                "url": "$zip_url"
            }
		},
		{
            "step": "login",
            "username": "admin",
            "password": "password"
        }
	]
}
EOF;
			header( 'Access-Control-Allow-Origin: https://playground.wordpress.net' );
			die( $zip_blueprint );
		}

        $blueprints = get_post_meta( $plugin->ID, 'assets_blueprints', true );
        // Note: for now, only use a file called `blueprint.json`.
		if ( !isset( $blueprints['blueprint.json'] ) ) {
			return new \WP_Error( 'no_blueprint', 'File not found', array( 'status' => 404 ) );
        }
        $blueprint = $blueprints['blueprint.json'];
        if ( !$blueprint || !isset( $blueprint['contents'] ) || !is_string( $blueprint['contents'] ) ) {
			return new \WP_Error( 'invalid_blueprint', 'Invalid file', array( 'status' => 500 ) );
        }

		// Configure this elsewhere?
		header( 'Access-Control-Allow-Origin: https://playground.wordpress.net' );

		// We already have a json string, returning would double-encode it.
		die( $blueprint['contents'] );
	}

}
