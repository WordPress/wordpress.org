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

		if ( $request->get_param('dismiss') ) {
			return $this->self_dismiss( $request, $plugin );
		}

		// Toggle the postmeta value. Note that there is a race condition here.
		$did = '';
		if ( get_post_meta( $plugin->ID, '_public_preview', true ) ) {
			$r = delete_post_meta( $plugin->ID, '_public_preview' );
			$did = 'disabled';
		} else {
			$r = add_post_meta( $plugin->ID, '_public_preview', '1', true );
			$did = 'enabled';
		}

		// Add an audit-log entry as to why this has happened.
		Tools::audit_log(
			sprintf( 'Plugin preview %s. Reason: Author Request from %s', $did, $_SERVER['REMOTE_ADDR'] ),
			$plugin
		);

		return $result;
	}

	/**
	 * Endpoint special case to dismiss the missing blueprint notice.
	 *
	 * @param \WP_REST_Request $request The Rest API Request.
	 * @param object $plugin The plugin post.
	 * @return bool True if the toggle was successful.
	 */
	public function self_dismiss( $request, $plugin ) {
		$result = [
			'location' => wp_get_referer() ?: get_permalink( $plugin ),
		];

		// Change the value from 1 to 0. This will persist and prevent it from being added again.
		update_post_meta( $plugin->ID, '_missing_blueprint_notice', 0, 1 );

		return $result;
	}
}
