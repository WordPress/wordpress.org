<?php
namespace WordPressdotorg\Plugin_Directory\API\Routes;

use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\API\Base;
use WordPressdotorg\Plugin_Directory\Tools;
use WordPressdotorg\Plugin_Directory\Email\Release_Confirmation_Enabled as Release_Confirmation_Enabled_Email;

/**
 * An API endpoint for closing a particular plugin.
 *
 * @package WordPressdotorg_Plugin_Directory
 */
class Plugin_Release_Confirmation extends Base {

	public function __construct() {
		register_rest_route( 'plugins/v1', '/plugin/(?P<plugin_slug>[^/]+)/release-confirmation', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'enable_release_confirmation' ],
			'args'                => [
				'plugin_slug' => [
					'validate_callback' => [ $this, 'validate_plugin_slug_callback' ],
				],
			],
			'permission_callback' => function( $request ) {
				$plugin = Plugin_Directory::get_plugin_post( $request['plugin_slug'] );

				return current_user_can( 'plugin_admin_edit', $plugin ) && 'publish' === $plugin->post_status;
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
			preg_match( '!^/plugins/v1/plugin/([^/]+)/release-confirmation$!', $request->get_route(), $m )
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
	 * Endpoint to self-close a plugin.
	 *
	 * @param \WP_REST_Request $request The Rest API Request.
	 * @return bool True if the favoriting was successful.
	 */
	public function enable_release_confirmation( $request ) {
		$plugin   = Plugin_Directory::get_plugin_post( $request['plugin_slug'] );
		$location = get_permalink( $plugin );
		header( "Location: $location" );

		$result = [
			'location' => $location,
		];

		// Abort early if needed.
		if ( $plugin->release_confirmation ) {
			return $result;
		}

		// Update the Metadata.
		update_post_meta( $plugin->ID, 'release_confirmation', 1 );

		// Add an audit-log entry.
		Tools::audit_log( 'Release Confirmations Enabled.', $plugin );

		// TODO: Send all committers an email that this has been enabled.
		$email = new Release_Confirmation_Enabled_Email(
			$plugin,
			Tools::get_plugin_committers( $plugin->post_name ),
			[]
		);
		$email->send();

		return $result;
	}

}
