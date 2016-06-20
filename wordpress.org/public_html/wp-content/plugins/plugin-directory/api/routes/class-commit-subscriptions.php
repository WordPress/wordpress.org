<?php
namespace WordPressdotorg\Plugin_Directory\API\Routes;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\API\Base;
use WordPressdotorg\Plugin_Directory\Tools;

/**
 * An API endpoint for subscribing to commits for a particular plugin.
 *
 * @package WordPressdotorg_Plugin_Directory
 */
class Commit_Subscriptions extends Base {

	public function __construct() {
		register_rest_route( 'plugins/v1', '/plugin/(?P<plugin_slug>[^/]+)/commit-subscription', array(
			'methods'  => \WP_REST_Server::READABLE,
			'callback' => array( $this, 'subscribe' ),
			'args' => array(
				'plugin_slug' => array(
					'validate_callback' => array( $this, 'validate_plugin_slug_callback' ),
				),
				'subscribe' => array(
					'validate_callback' => function( $bool ) { return is_numeric( $bool ); },
				),
				'unsubscribe' => array(
					'validate_callback' => function( $bool ) { return is_numeric( $bool ); },
				),
			),
			'permission_callback' => 'is_user_logged_in'
		) );
	}

	/**
	 * Endpoint to subscribe to a plugin's commits.
	 *
	 * @param \WP_REST_Request $request The Rest API Request.
	 * @return bool True if the subscription was successful.
	 */
	public function subscribe( $request ) {
		$location = get_permalink( Plugin_Directory::get_plugin_post( $request['plugin_slug'] ) ) . '#developers';
		header( "Location: $location" );

		$result = array(
			"location" => $location,
		);

		if ( ! isset( $request['subscribe'] ) && ! isset( $request['unsubscribe'] ) ) {
			$result['error'] = 'Unknown Action';
		}

		$result['subscribed'] = Tools::subscribe_to_plugin_commits( $request['plugin_slug'], get_current_user_id(), isset( $request['subscribe'] ) );

		return (object) $result;
	}

}
