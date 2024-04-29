<?php
namespace WordPressdotorg\Plugin_Directory\API\Routes;
use WordPressdotorg\Plugin_Directory\API\Base;
use WP_Error;
use WP_REST_Server;

/**
 * An API Endpoint to expose a single Plugin data via api.wordpress.org/plugins/info/1.x
 *
 * @package WordPressdotorg_Plugin_Directory
 */
class Pending_Plugin extends Base {

	/**
	 * Plugin constructor.
	 */
	function __construct() {
		register_rest_route( 'plugins/v1', '/pending-plugin/(?P<plugin_id>\d+)-(?P<token>[a-f0-9]{32})/?', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'pending_plugin_info' ),
			'permission_callback' => array( $this, 'pending_plugin_permission_check' ),
		) );
	}

	/**
	 * Permission check that validates the hash for a pending plugin.
	 *
	 * @param \WP_REST_Request $request The Rest API Request.
	 * @return array A formatted array of all the data for the plugin.
	 */
	public function pending_plugin_permission_check( $request ) {
		$post          = get_post( $request['plugin_id'] );
		$expected_hash = $post->{'_pending_access_token'} ?? false;

		return (
			$post &&
			$expected_hash &&
			! empty( $request['token'] ) &&
			hash_equals( $expected_hash, $request['token'] )
		);
	}

	/**
	 * Endpoint to retrieve a full plugin representation for a pending plugin.
	 *
	 * @param \WP_REST_Request $request The Rest API Request.
	 * @return array A formatted array of all the data for the plugin.
	 */
	public function pending_plugin_info( $request ) {
		$post      = get_post( $request['plugin_id'] );
		$submitter = get_user_by( 'id', $post->post_author );

		if ( ! $post || ! in_array( $post->post_status, [ 'new', 'pending', 'rejected', 'approved' ] ) ) {
			return new WP_Error( 'plugin_not_found', 'Plugin not found', [ 'status' => 404 ] );
		}

		// Pending plugin specific fields
		$details = [
			'ID'          => $post->ID,
			'post_status' => $post->post_status,
			'edit_url'    => add_query_arg( [ 'action' => 'edit', 'post' => $post->ID ], admin_url( 'post.php' ) ),
			'submitter'   => [
				'user_login' => $submitter->user_login,
				'user_email' => $submitter->user_email,
			]
		];

		$plugin_endpoint = new Plugin;

		return $details + $plugin_endpoint->plugin_info_data( $request, $post );
	}
}
