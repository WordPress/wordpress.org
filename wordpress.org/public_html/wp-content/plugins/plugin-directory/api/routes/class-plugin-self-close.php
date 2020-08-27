<?php
namespace WordPressdotorg\Plugin_Directory\API\Routes;

use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\API\Base;
use WordPressdotorg\Plugin_Directory\Tools;

/**
 * An API endpoint for closing a particular plugin.
 *
 * @package WordPressdotorg_Plugin_Directory
 */
class Plugin_Self_Close extends Base {

	public function __construct() {
		register_rest_route( 'plugins/v1', '/plugin/(?P<plugin_slug>[^/]+)/self-close', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'self_close' ],
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
			preg_match( '!^/plugins/v1/plugin/([^/]+)/self-close$!', $request->get_route(), $m )
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
	public function self_close( $request ) {
		$plugin = Plugin_Directory::get_plugin_post( $request['plugin_slug'] );
		$result = [
			'location' => wp_get_referer() ?: get_permalink( $plugin ),
		];
		header( 'Location: ' . $result['location'] );

		// Close the plugin.
		$plugin->post_status = 'closed';
		wp_update_post( $plugin );

		// Update the Metadata
		update_post_meta( $plugin->ID, '_close_reason', 'author-request' );
		update_post_meta( $plugin->ID, 'plugin_closed_date', current_time( 'mysql' ) );

		// Add an audit-log entry as to why this has happened.
		Tools::audit_log(
			sprintf( 'Plugin closed. Reason: Author Self-close Request from %s', $_SERVER['REMOTE_ADDR'] ),
			$plugin
		);

		// Email all Plugin Committers.
		$subject = sprintf( __( '[WordPress Plugin Directory] %s has been closed', 'wporg-plugins' ), $plugin->post_title );
		$message = sprintf(
			/* translators: 1: Author name 2: Date, 3: Plugin Name 4: Plugin team email address. */
			__( 'As requested by %1$s on %2$s, %3$s has been closed in the WordPress Plugin Directory.

Closing your plugin is intended to be a permanent action. You will not be able to reopen it without contacting the plugins team.

If you believe this closure to be in error, please email %4$s and explain why you feel your plugin should be re-opened.

--
The WordPress Plugin Directory Team
https://make.wordpress.org/plugins', 'wporg-plugins' ),
			wp_get_current_user()->display_name,
			gmdate( 'Y-m-d H:i:s \G\M\T' ),
			$plugin->post_title,
			'plugins@wordpress.org'
		);

		$who_to_email = [];
		foreach ( Tools::get_plugin_committers( $plugin->post_name ) as $user_login ) {
			$who_to_email[] = get_user_by( 'login', $user_login )->user_email;
		}

		wp_mail( $who_to_email, $subject, $message, 'From: plugins@wordpress.org' );

		$result['closed'] = true;

		return $result;
	}

}
