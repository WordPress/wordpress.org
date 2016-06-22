<?php
namespace WordPressdotorg\Plugin_Directory\API\Routes;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\API\Base;
use WordPressdotorg\Plugin_Directory\Tools;

/**
 * An API endpoint for favoriting a particular plugin.
 *
 * @package WordPressdotorg_Plugin_Directory
 */
class Plugin_Favorites extends Base {

	public function __construct() {
		register_rest_route( 'plugins/v1', '/plugin/(?P<plugin_slug>[^/]+)/favorite', array(
			'methods'  => array( \WP_REST_Server::READABLE, \WP_REST_Server::CREATABLE ),
			'callback' => array( $this, 'favorite' ),
			'args' => array(
				'plugin_slug' => array(
					'validate_callback' => array( $this, 'validate_plugin_slug_callback' ),
				),
				'favorite' => array(
					'validate_callback' => function( $bool ) { return is_numeric( $bool ); },
				),
				'unfavorite' => array(
					'validate_callback' => function( $bool ) { return is_numeric( $bool ); },
				),
			),
			'permission_callback' => 'is_user_logged_in'
		) );
	}

	/**
	 * Endpoint to favorite a plugin.
	 *
	 * @param \WP_REST_Request $request The Rest API Request.
	 * @return bool True if the favoriting was successful.
	 */
	public function favorite( $request ) {
		$location = get_permalink( Plugin_Directory::get_plugin_post( $request['plugin_slug'] ) );
		header( "Location: $location" );

		$result = array(
			"location" => $location,
		);

		if ( ! isset( $request['favorite'] ) && ! isset( $request['unfavorite'] ) ) {
			$result['error'] = 'Unknown Action';
		}

		$result['favorite'] = Tools::favorite_plugin( $request['plugin_slug'], get_current_user_id(), isset( $request['favorite'] ) );

		return (object) $result;
	}

}
