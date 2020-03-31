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
 * An API Endpoint to manage plugin support reps.
 *
 * @package WordPressdotorg_Plugin_Directory
 */
class Plugin_Support_Reps extends Base {

	function __construct() {
		register_rest_route( 'plugins/v1', '/plugin/(?P<plugin_slug>[^/]+)/support-reps/?', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_support_reps' ),
				'permission_callback' => function( $request ) {
					return current_user_can(
						'plugin_admin_edit',
						Plugin_Directory::get_plugin_post( $request['plugin_slug'] )
					);
				},
				'args'                => array(
					'plugin_slug' => array(
						'validate_callback' => array( $this, 'validate_plugin_slug_callback' ),
						'required'          => true,
					),
				),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'add_support_rep' ),
				'permission_callback' => function( $request ) {
					return current_user_can(
						'plugin_add_support_rep',
						Plugin_Directory::get_plugin_post( $request['plugin_slug'] )
					);
				},
				'args'                => array(
					'plugin_slug' => array(
						'validate_callback' => array( $this, 'validate_plugin_slug_callback' ),
						'required'          => true,
					),
				),
			),
		) );

		register_rest_route( 'plugins/v1', '/plugin/(?P<plugin_slug>[^/]+)/support-reps/(?P<support_rep>[^/]+)/?', array(
			'methods'             => WP_REST_Server::DELETABLE,
			'callback'            => array( $this, 'remove_support_rep' ),
			'permission_callback' => function( $request ) {
				return current_user_can(
					'plugin_remove_support_rep',
					Plugin_Directory::get_plugin_post( $request['plugin_slug'] )
				);
			},
			'args'                => array(
				'plugin_slug' => array(
					'validate_callback' => array( $this, 'validate_plugin_slug_callback' ),
					'required'          => true,
				),
				'support_rep' => array(
					'validate_callback' => array( $this, 'validate_user_slug_callback' ),
					'required'          => true,
				),
			),
		) );
	}

	/**
	 *
	 */
	function list_support_reps( $request ) {
		$plugin_slug = $request['plugin_slug'];

		$support_reps = array();
		foreach ( (array) Tools::get_plugin_support_reps( $plugin_slug ) as $user_nicename ) {
			$user           = get_user_by( 'slug', $user_nicename );
			$support_reps[] = $this->user_support_rep_details( $user );
		}

		return $support_reps;
	}

	function add_support_rep( $request ) {
		$user = new WP_User( $request['support_rep'] );

		// If user_login is not found, try searching by user_nicename.
		if ( ! $user || ! $user->exists() ) {
			$user = get_user_by( 'slug', $request['support_rep'] );
		}

		// If user_nicename is not found, try searching by user_email.
		if ( ! $user || ! $user->exists() ) {
			$user = get_user_by( 'email', $request['support_rep'] );
		}

		if ( ! $user || ! $user->exists() ) {
			return new WP_Error( 'plugin_user_not_found', __( 'The provided user could not be found.', 'wporg-plugins' ) );
		}

		$plugin_slug = $request['plugin_slug'];

		if ( ! Tools::add_plugin_support_rep( $plugin_slug, $user ) ) {
			return new WP_Error( 'failed', __( 'The operation failed. Please try again.', 'wporg-plugins' ) );
		}

		return $this->user_support_rep_details( $user );
	}

	function remove_support_rep( $request ) {
		$user = new WP_User( $request['support_rep'] );

		// If user_login is not found, try searching by user_nicename.
		if ( ! $user || ! $user->exists() ) {
			$user = get_user_by( 'slug', $request['support_rep'] );
		}

		// If user_nicename is not found, try searching by user_email.
		if ( ! $user || ! $user->exists() ) {
			$user = get_user_by( 'email', $request['support_rep'] );
		}

		if ( ! $user || ! $user->exists() ) {
			return new WP_Error( 'plugin_user_not_found', __( 'The provided user could not be found.', 'wporg-plugins' ) );
		}

		$plugin_slug = $request['plugin_slug'];

		$result = Tools::remove_plugin_support_rep( $plugin_slug, $user );
		if ( ! $result ) {
			return new WP_Error( 'failed', __( 'The operation failed. Please try again.', 'wporg-plugins' ) );
		}

		return true;
	}

	/**
	 * Validate that a user by the given slug exists.
	 */
	function validate_user_slug_callback( $value ) {
		return (bool) get_user_by( 'slug', $value );
	}

	/**
	 * Helper function to return a support rep object
	 */
	function user_support_rep_details( $user ) {
		$data = array(
			'nicename' => $user->user_nicename,
			'profile'  => esc_url( 'https://profiles.wordpress.org/' . $user->user_nicename ),
			'avatar'   => get_avatar_url( $user->ID, 32 ),
			'name'     => $user->display_name,
		);

		if ( current_user_can( 'plugin_review' ) ) {
			$data['email'] = $user->user_email;
		}

		return $data;
	}

}


