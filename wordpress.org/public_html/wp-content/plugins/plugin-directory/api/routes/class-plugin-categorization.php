<?php
namespace WordPressdotorg\Plugin_Directory\API\Routes;

use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\Template;
use WordPressdotorg\Plugin_Directory\Tools;
use WordPressdotorg\Plugin_Directory\API\Base;
use WP_REST_Server;
use WP_Error;
use WP_User;

/**
 * An API Endpoint to manage plugin categorization.
 */
class Plugin_Categorization extends Base {
	public function __construct() {
		register_rest_route( 'plugins/v1', '/plugin/(?P<plugin_slug>[^/]+)/community/?', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'save_external_repository_url' ],
				'permission_callback' => function( $request ) {
					return current_user_can(
						'plugin_admin_edit',
						Plugin_Directory::get_plugin_post( $request['plugin_slug'] )
					);
				},
				'args'                => [
					'plugin_slug' => [
						'validate_callback' => [ $this, 'validate_plugin_slug_callback' ],
						'required'          => true,
					],
					'repositoryURL' => [
						'validate_callback' => [ $this, 'validate_url' ],
						'required'          => true,
					],
				],
			],
		] );

		register_rest_route( 'plugins/v1', '/plugin/(?P<plugin_slug>[^/]+)/commercial/?', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'save_external_support_url' ],
				'permission_callback' => function( $request ) {
					return current_user_can(
						'plugin_admin_edit',
						Plugin_Directory::get_plugin_post( $request['plugin_slug'] )
					);
				},
				'args'                => [
					'plugin_slug' => [
						'validate_callback' => [ $this, 'validate_plugin_slug_callback' ],
						'required'          => true,
					],
					'supportURL' => [
						'validate_callback' => [ $this, 'validate_url' ],
						'required'          => true,
					],
				],
			],
		] );
	}

	/**
	 * Validates a URL.
	 *
	 * @param string $value Submitted field value.
	 * @return bool True if URL is valid, else false.
	 */
	public function validate_url( $value ) {
		$value = trim( strip_tags( stripslashes( $value ) ) );

		if ( ! $value ) {
			return true;
		}

		return (bool) filter_var( $value, FILTER_VALIDATE_URL );
	}

	/**
	 * Saves the submitted repository URL.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function save_external_repository_url( $request ) {
		return $this->save_url( 'external_repository_url', 'repositoryURL', $request );
	}

	/**
	 * Saves the submitted support URL.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function save_external_support_url( $request ) {
		return $this->save_url( 'external_support_url', 'supportURL', $request );
	}

	/**
	 * Saves a URL as post meta.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	protected function save_url( $meta_key, $request_key, $request ) {
		$url  = $request[ $request_key ] ?? '';
		$post = Plugin_Directory::get_plugin_post( $request['plugin_slug'] );

		if ( ! $post ) {
			return;
		}

		$fail = false;

		// Sanitize URL.
		if ( $url ) {
			$url = trim( strip_tags( stripslashes( $url ) ) );
		}

		// Delete post meta if new value is empty, else save.
		if ( ! $url ) {
			delete_post_meta( $post->ID, $meta_key );
		} else {
			update_post_meta( $post->ID, $meta_key, wp_slash( esc_url_raw( $url, [ 'http', 'https' ] ) ) );
		}

		if ( $fail ) {
			return new WP_Error( 'failed', __( 'The operation failed. Please try again.', 'wporg-plugins' ) );
		}

		return [ $request_key => $url ];
	}
}
