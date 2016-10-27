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
 * An API Endpoint to manage plugin committers.
 *
 * @package WordPressdotorg_Plugin_Directory
 */
class Plugin_Committers extends Base {

	function __construct() {
		register_rest_route( 'plugins/v1', '/plugin/(?P<plugin_slug>[^/]+)/committers/?', array(
			array(
				'methods'  => WP_REST_Server::READABLE,
				'callback' => array( $this, 'list_committers' ),
				'permission_callback' => function( $request ) {
					return current_user_can(
						'plugin_admin_edit',
						Plugin_Directory::get_plugin_post( $request['plugin_slug'] )
					);
				},
				'args' => array(
					'plugin_slug' => array(
						'validate_callback' => array( $this, 'validate_plugin_slug_callback' ),
						'required' => true,
					),
				)
			),
			array(
				'methods'  => WP_REST_Server::CREATABLE,
				'callback' => array( $this, 'add_committer' ),
				'permission_callback' => function( $request ) {
					return current_user_can(
						'plugin_add_committer',
						Plugin_Directory::get_plugin_post( $request['plugin_slug'] )
					);
				},
				'args' => array(
					'plugin_slug' => array(
						'validate_callback' => array( $this, 'validate_plugin_slug_callback' ),
						'required' => true,
					),
				)
			)
		) );

		register_rest_route( 'plugins/v1', '/plugin/(?P<plugin_slug>[^/]+)/committers/(?P<committer>[^/]+)/?', array(
			'methods'  => WP_REST_Server::DELETABLE,
			'callback' => array( $this, 'revoke_committer' ),
			'permission_callback' => function( $request ) {
				return current_user_can(
					'plugin_remove_committer',
					Plugin_Directory::get_plugin_post( $request['plugin_slug'] )
				);
			},
			'args' => array(
				'plugin_slug' => array(
					'validate_callback' => array( $this, 'validate_plugin_slug_callback' ),
					'required' => true,
				),
				'committer' => array(
					'validate_callback' => array( $this, 'validate_user_slug_callback' ),
					'required' => true,
				)
			)
		) );
	}

	/**
	 *
	 */
	function list_committers( $request ) {
		$plugin_slug = $request['plugin_slug'];

		$committers = array();
		foreach ( (array) Tools::get_plugin_committers( $plugin_slug ) as $user_login ) {
			$user = get_user_by( 'login', $user_login );
			$committers[] = $this->user_committer_details( $user );
		}

		return $committers;
	}

	function add_committer( $request ) {
		$user = new WP_User( $request['committer'] );
		if ( ! $user->exists() ) {
			return new WP_Error( 'plugin_user_not_found', __( 'The provided user could not be found.', 'wporg-plugins' ) );
		}

		$plugin_slug = $request['plugin_slug'];

		if ( ! Tools::grant_plugin_committer( $plugin_slug, $user ) ) {
			return new WP_Error( 'failed', __( 'The operation failed. Please try again.', 'wporg-plugins' ) );
		}

		return $this->user_committer_details( $user );
	}

	function revoke_committer( $request ) {
		$user = new WP_User( $request['committer'] );
		if ( ! $user->exists() ) {
			return new WP_Error( 'plugin_user_not_found', __( 'The provided user could not be found.', 'wporg-plugins' ) );
		}

		$plugin_slug = $request['plugin_slug'];

		$result = Tools::revoke_plugin_committer( $plugin_slug, $user );
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
	 * Helper function to return a committer object
	 */
	function user_committer_details( $user ) {
		$data = array(
			'nicename' => $user->user_nicename,
			'profile'  => esc_url( 'https://profiles.wordpress.org/' . $user->user_nicename ),
			'avatar'   => get_avatar_url( $user->ID, 32 ),
			'name'     => Template::encode( $user->display_name )
		);

		if ( current_user_can( 'plugin_review' ) ) {
			$data['email'] = $user->user_email;
		}

		return $data;
	}

}


