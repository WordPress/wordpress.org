<?php
/**
 * An API endpoint for publishing plugin releases.
 *
 * @package WordPressdotorg_Plugin_Directory
 */

namespace WordPressdotorg\Plugin_Directory\API\Routes;

use WP_REST_Response;
use WP_REST_Server;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\Plugin_Release;
use WordPressdotorg\Plugin_Directory\API\Base;

/**
 * An API endpoint for publishing a release of a plugin.
 *
 * @package WordPressdotorg_Plugin_Directory
 */
class Plugin_Publish extends Base {

	/**
	 * Plugin_Publish constructor.
	 */
	public function __construct() {
		register_rest_route(
			'plugins/v2',
			'/plugin/(?P<plugin_slug>[^/]+)/publish',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'publish_release' ),
				'args'                => array(
					'plugin_slug' => array(
						'validate_callback' => array( $this, 'validate_plugin_slug_callback' ),
					),
				),
				'permission_callback' => array( $this, 'permission_can_access_plugin' ),
			)
		);
	}

	/**
	 * Validate that the user can manage a given plugin.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return bool
	 */
	public function permission_can_access_plugin( $request ) {
		$plugin = Plugin_Directory::get_plugin_post( $request['plugin_slug'] );
		return current_user_can( 'plugin_manage_releases', $plugin );
	}

	/**
	 * A simple endpoint to publish a release.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public function publish_release( $request ) {
		$plugin = Plugin_Directory::get_plugin_post( $request['plugin_slug'] );
		// Will return either a WP_Error, or the post ID of the published release CPT.
		$result = Plugin_Release::instance()->publish_release( $plugin );

		return $result;
	}
}
