<?php
namespace WordPressdotorg\Plugin_Directory\API\Routes;

use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\API\Base;
use WordPressdotorg\Plugin_Directory\Tools;
use WordPressdotorg\Plugin_Directory\Email\Plugin_Transferred as Plugin_Transferred_Email;

/**
 * An API endpoint for transferring a particular plugin.
 *
 * @package WordPressdotorg_Plugin_Directory
 */
class Plugin_Self_Transfer extends Base {

	public function __construct() {
		register_rest_route( 'plugins/v1', '/plugin/(?P<plugin_slug>[^/]+)/self-transfer', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'self_transfer' ],
			'args'                => [
				'plugin_slug' => [
					'validate_callback' => [ $this, 'validate_plugin_slug_callback' ],
				],
				'new_owner' => [
					'validate_callback' => function( $id ) {
						return (bool) get_user_by( 'id', $id );
					}
				]
			],
			'permission_callback' => function( $request ) {
				$plugin = Plugin_Directory::get_plugin_post( $request['plugin_slug'] );

				return
					current_user_can( 'plugin_admin_edit', $plugin ) &&
					get_current_user_id() == $plugin->post_author &&
					'publish' === $plugin->post_status;
			},
		] );

		add_filter( 'rest_pre_echo_response', [ $this, 'override_cookie_expired_message' ], 10, 3 );
	}

	/**
	 * Redirect back to the plugins page when this endpoint is accessed with an invalid nonce.
	 */
	function override_cookie_expired_message( $result, $obj, $request ) {
		if (
			is_array( $result ) && isset( $result['code'] ) &&
			preg_match( '!^/plugins/v1/plugin/([^/]+)/self-transfer$!', $request->get_route(), $m )
		) {
			if ( 'rest_cookie_invalid_nonce' == $result['code'] ) {
				wp_die( 'The link you have followed has expired.' );
			} elseif ( 'rest_forbidden' == $result['code'] ) {
				wp_die( "Sorry, You can't do that." );
			}
		}

		return $result;
	}

	/**
	 * Endpoint to self-transfer a plugin.
	 *
	 * @param \WP_REST_Request $request The Rest API Request.
	 * @return bool True if the favoriting was successful.
	 */
	public function self_transfer( $request ) {
		$plugin   = Plugin_Directory::get_plugin_post( $request['plugin_slug'] );
		$location = get_permalink( $plugin ) . 'advanced/';
		header( "Location: $location" );
		$result = [
			'location'    => $location,
			'transferred' => false,
		];

		// New owner must also have commit rights.
		$new_owner = get_user_by( 'id', $request['new_owner'] );
		if ( ! user_can( $new_owner, 'plugin_admin_edit', $plugin ) ) {
			return $result;
		}

		// Change the authorship.
		$plugin->post_author = $new_owner->ID;
		wp_update_post( $plugin );

		// Add an audit-log entry as to why this has happened.
		Tools::audit_log( sprintf(
			'Ownership self-transferred to <a href="%s">%s</a>.',
			esc_url( 'https://profiles.wordpress.org/' . $new_owner->user_nicename .'/' ),
			$new_owner->user_login
		), $plugin );

		// Email all Plugin Committers.
		$email = new Plugin_Transferred_Email(
			$plugin,
			Tools::get_plugin_committers( $plugin->post_name ),
			[
				'owner' => $new_owner
			]
		);
		$email->send();

		$result['transferred'] = true;

		return $result;
	}

}
