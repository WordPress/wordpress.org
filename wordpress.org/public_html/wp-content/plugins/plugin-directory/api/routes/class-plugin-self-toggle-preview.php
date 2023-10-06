<?php
namespace WordPressdotorg\Plugin_Directory\API\Routes;

use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\Admin\Status_Transitions;
use WordPressdotorg\Plugin_Directory\API\Base;
use WordPressdotorg\Plugin_Directory\Tools;

/**
 * An API endpoint for toggling Preview button availability on a particular plugin.
 *
 * @package WordPressdotorg_Plugin_Directory
 */
class Plugin_Self_Toggle_Preview extends Base {

	public function __construct() {
		register_rest_route( 'plugins/v1', '/plugin/(?P<plugin_slug>[^/]+)/self-toggle-preview', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'self_toggle_preview' ],
			'args'                => [
				'plugin_slug' => [
					'validate_callback' => [ $this, 'validate_plugin_slug_callback' ],
				],
			],
			'permission_callback' => function( $request ) {
				$plugin = Plugin_Directory::get_plugin_post( $request['plugin_slug'] );

				return current_user_can( 'plugin_self_close', $plugin );
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
			preg_match( '!^/plugins/v1/plugin/([^/]+)/self-toggle-preview$!', $request->get_route(), $m )
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
	 * Endpoint to toggle the Preview status.
	 *
	 * @param \WP_REST_Request $request The Rest API Request.
	 * @return bool True if the toggle was successful.
	 */
	public function self_toggle_preview( $request ) {
		$plugin = Plugin_Directory::get_plugin_post( $request['plugin_slug'] );
		$result = [
			'location' => wp_get_referer() ?: get_permalink( $plugin ),
		];
		header( 'Location: ' . $result['location'] );

		// Toggle the postmeta value. Note that there is a race condition here.
		$did = '';
		if ( get_post_meta( $plugin->ID, '_no_preview', true ) ) {
			$r = delete_post_meta( $plugin->ID, '_no_preview' );
			$did = 'enabled';
		} else {
			$r = add_post_meta( $plugin->ID, '_no_preview', '1', true );
			$did = 'disabled';
		}

		// Add an audit-log entry as to why this has happened.
		Tools::audit_log(
			sprintf( 'Plugin preview %s. Reason: Author Request from %s', $did, $_SERVER['REMOTE_ADDR'] ),
			$plugin
		);

		return $result;
	}

}
