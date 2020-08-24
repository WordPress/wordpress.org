<?php
namespace WordPressdotorg\Plugin_Directory\API\Routes;

use WP_REST_Response;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\API\Base;
use WordPressdotorg\Plugin_Directory\Tools;
use WordPressdotorg\Plugin_Directory\Jobs\Plugin_Import;
use WordPressdotorg\Plugin_Directory\Shortcodes\Release_Confirmation as Release_Confirmation_Shortcode;
use WordPressdotorg\Plugin_Directory\Email\Release_Confirmation_Enabled as Release_Confirmation_Enabled_Email;
use WordPressdotorg\Plugin_Directory\Email\Release_Confirmation_Access as Release_Confirmation_Access_Email;

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

		register_rest_route( 'plugins/v1', '/plugin/(?P<plugin_slug>[^/]+)/release-confirmation/(?P<plugin_tag>[^/]+)', [
			'methods'             => \WP_REST_Server::READABLE, // TODO: This really should be a POST
			'callback'            => [ $this, 'confirm_release' ],
			'args'                => [
				'plugin_slug' => [
					'validate_callback' => [ $this, 'validate_plugin_slug_callback' ],
				],
				'plugin_tag' => [
					'validate_callback' => [ $this, 'validate_plugin_tag_callback' ],
				]
			],
			'permission_callback' => function( $request ) {
				$plugin = Plugin_Directory::get_plugin_post( $request['plugin_slug'] );

				return (
					Release_Confirmation_Shortcode::can_access() &&
					current_user_can( 'plugin_admin_edit', $plugin ) &&
					'publish' === $plugin->post_status
				);
			},
		] );

		register_rest_route( 'plugins/v1', '/release-confirmation-access', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'send_access_email' ],
			'args'                => [
			],
			'permission_callback' => function( $request ) {
				return is_user_logged_in();
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
			(
				preg_match( '!^/plugins/v1/plugin/([^/]+)/release-confirmation(/[^/]+)?$!', $request->get_route(), $m )
				||
				'/plugins/v1/release-confirmation-access' === $request->get_route()
			)
		) {
			if ( 'rest_cookie_invalid_nonce' == $result['code'] || 'rest_forbidden' == $result['code'] ) {
				wp_die( 'The link you have followed has expired.' );
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
		$plugin = Plugin_Directory::get_plugin_post( $request['plugin_slug'] );
		$result = [
			'location' => wp_get_referer() ?: get_permalink( $plugin ),
		];

		// Only redirect if we've been called via the rest api, and not an internal api request.
		if ( wp_is_json_request() ) {
			header( 'Location: ' . $result['location'] );
		}

		$confirmations_required = $request['confirmations_required'] ?? 1;

		// Abort early if needed.
		if ( $plugin->release_confirmation == $confirmations_required ) {
			return $result;
		}

		// Update the Metadata.
		update_post_meta( $plugin->ID, 'release_confirmation', $confirmations_required );

		// Mark all existing releases as confirmed.
		$tags     = get_post_meta( $plugin->ID, 'tags', true ) ?: [];
		$releases = get_post_meta( $plugin->ID, 'confirmed_releases', true ) ?: [];
		if ( ! $tags ) {
			$tags = get_post_meta( $plugin->ID, 'tagged_versions', true ) ?: [];
		}
		foreach ( $tags as $tag => $tag_data ) {
			// tagged_data compat.
			if ( is_string( $tag_data ) ) {
				$tag      = $tag_data;
				$tag_data = [
					'date'   => $plugin->last_updated,
					'author' => '',
					'tag'    => $tag_data,
				];
			}
			if ( ! isset( $releases[ $tag ] ) ) {
				$releases[ $tag ] = [
					'date'          => strtotime( $tag_data['date'] ),
					'tag'           => $tag,
					'version'       => $tag,
					'zips_built'    => true,
					'confirmations' => [],
					'confirmed'     => true,
					'committer'     => [ $tag_data['author'] ],
					'revision'      => [],
				];
			}
		}
		update_post_meta( $plugin->ID, 'confirmed_releases', $releases );

		// Add an audit-log entry.
		Tools::audit_log(
			sprintf(
				'Release Confirmations Enabled. %d confirmations required',
				$confirmations_required
			),
			$plugin
		);

		$email = new Release_Confirmation_Enabled_Email(
			$plugin,
			Tools::get_plugin_committers( $plugin->post_name ),
			[]
		);
		$email->send();

		return $result;
	}

	/**
	 * A simple endpoint to confirm a release.
	 */
	public function confirm_release( $request ) {
		$plugin   = Plugin_Directory::get_plugin_post( $request['plugin_slug'] );
		$releases = get_post_meta( $plugin->ID, 'confirmed_releases', true ) ?: [];
		$tag      = $request['plugin_tag'];
		$result   = [
			'location' => wp_get_referer() ?: home_url( '/developers/releases/' ),
		];
		header( 'Location: ' . $result['location'] );

		$user_login = wp_get_current_user()->user_login;

		if ( ! empty( $releases[ $tag ]['confirmed'][ $user_login ] ) ) {
			// Already confirmed.
			$result['confirmed'] = false;
			return $result;
		}

		// Record this user as confirming the release.
		$releases[ $tag ]['confirmations'][ $user_login ] = time();
		$result['confirmed']                              = true;

		// Mark the release as confirmed if enough confirmations.
		if ( count( $releases[ $tag ]['confirmations'] ) >= $plugin->release_confirmation ) {
			$releases[ $tag ]['confirmed'] = true;
			$result['fully_confirmed']     = true;
		}

		if ( ! update_post_meta( $plugin->ID, 'confirmed_releases', $releases ) ) {
			$result['confirmed'] = false;
			unset( $result['fully_confirmed'] );
			return $result;
		}

		// Trigger the import for the plugin.
		Plugin_Import::queue(
			$plugin->post_name,
			// TODO this is not 100% right... but will probably work.
			[
				'tags_touched'   => [
					'trunk',
					$tag
				],
				// Assume everything was modified.
				'readme_touched' => true,
				'code_touched'   => true,
				'assets_touched' => true,
				'revisions'      => $releases[ $tag ]['revision'],
			]
		);

		return $result;
	}

	/**
	 * Send a Access email
	 */
	public function send_access_email( $request ) {
		$result = [
			'location' => wp_get_referer() ?: home_url( '/developers/releases/' ),
		];
		$result['location'] = add_query_arg( 'send_access_email', '1', $result['location'] );
		header( 'Location: ' . $result['location'] );

		$email = new Release_Confirmation_Access_Email(
			wp_get_current_user()
		);
		$result['sent'] = $email->send();

		return $result;
	}

	public function validate_plugin_tag_callback( $tag, $request ) {
		$plugin = Plugin_Directory::get_plugin_post( $request['plugin_slug'] );

		$releases = get_post_meta( $plugin->ID, 'confirmed_releases', true ) ?: [];

		return !empty( $releases[ $tag ] );
	}
}
