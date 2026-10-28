<?php
namespace WordPressdotorg\Forums;
use WP_REST_Server;

class REST_API {
	function __construct() {
		add_action( 'rest_api_init', [ $this, 'register' ] );
	}

	function register() {
		register_rest_route( 'wporg-support/v1', '/subscribe-user-to-term', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'subscribe_user_to_term' ),
			'args'                => array(
				'type' => array(
					'type'     => 'enum',
					'enum'     => [ 'plugin', 'theme' ],
					'required' => true,
				),
				'slug' => array(
					'type'     => 'string',
					'required' => true,
				),
				'user_id' => array(
					'type'     => 'int',
					'required' => true,
				)
			),
			'permission_callback' => [ $this, 'internal_permission_callback' ],
		) );
	}

	function internal_permission_callback( $request ) {
		$authorization_header = $request->get_header( 'authorization' );
		$authorization_header = trim( str_ireplace( 'bearer', '', $authorization_header ) );

		return (
			$authorization_header &&
			defined( 'PLUGIN_API_INTERNAL_BEARER_TOKEN' ) &&
			hash_equals( PLUGIN_API_INTERNAL_BEARER_TOKEN, $authorization_header )
		);
	}

	function subscribe_user_to_term( $request ) {
		$type    = $request['type'];
		$slug    = $request['slug'];
		$user_id = $request['user_id'];

		$support_forums = Plugin::get_instance();

		if ( 'plugin' === $type ) {
			$subscriptions = $support_forums->plugin_subscriptions ?? false;
		} elseif ( 'theme' === $type ) {
			$subscriptions = $support_forums->theme_subscriptions ?? false;
		}

		if ( ! $subscriptions ) {
			return new \WP_Error( 'invalid_type', 'Invalid type' );
		}

		$subscriptions->directory->for_slug( $slug );

		$term_id = $subscriptions->directory->term->term_id ?? false;

		if ( ! $term_id ) {
			return new \WP_Error( 'invalid_slug', 'Invalid slug' );
		}

		if ( $subscriptions->is_user_subscribed_to_term( $user_id, $term_id ) ) {
			return true;
		}

		return (bool) $subscriptions->add_user_subscription( $user_id, $term_id );
	}
}