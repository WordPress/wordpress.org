<?php
namespace WordPressdotorg\Plugin_Directory\API\Routes;

use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\Tools;
use WordPressdotorg\Plugin_Directory\Tools\SVN_Automation;
use WordPressdotorg\Plugin_Directory\API\Base;
use WP_REST_Server;
use WP_Error;
use WP_User;
use function WordPressdotorg\Two_Factor\{ get_revalidation_status, get_revalidate_url }; // PR https://github.com/WordPress/wporg-two-factor/pull/283

/**
 * An API Endpoint to upload a new version of a plugin to SVN.
 *
 * NOTE: This endpoint currently does not have strings translated, this is intentional.
 *       This endpoint is intended on being used as an internal endpoint / by automated tools,
 *       via the WordPress.org domain only, as a result, the strings will always be output in english.
 *
 * This is intended on being a low-level API that's used by other endpoints, such as a GitHub action.
 *
 * @package WordPressdotorg_Plugin_Directory
 */
class Plugin_Upload_to_SVN extends Base {

	/**
	 * Plugin constructor.
	 */
	function __construct() {
		register_rest_route( 'plugins/v1', '/plugin/(?P<plugin_slug>[^/]+)/?', array(
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => array( $this, 'upload' ),
			'permission_callback' => array( $this, 'permission_check' ),
			'args' => [
				'plugin_slug' => [
					'type'              => 'string',
					'required'          => true,
					'validate_callback' => array( $this, 'validate_plugin_slug_callback' ),
				],
				'file' => [
					// This field won't actually be used, this is just a placeholder to encourage including a file.
					'required' => false,
				],
				'set_as_stable' => [
					'type'     => 'boolean',
					'required' => false,
					'default'  => true,
				]
			],
		) );
	}

	public function permission_check( $request ) {
		/**
		 * Auth should be a 2FA'd user.
		 */
		if ( ! is_user_logged_in() ) {
			return false;
		}

		// Check the current user is 2FA'd.
		$status = get_revalidation_status();
		if ( ! $status->last_validated ) {
			return new WP_Error( 'not_2fa', 'The authorized user does not have 2FA enabled.', 403 );
		}

		// TODO: This API endpoint should not be interactive, it should be a async job creator.
		if ( $status->needs_revalidate ) {
			// TODO Uhhhh... We kinda need to revalidate, yet we need the ZIP file that they've submitted.. Store it somewhere?
			wp_redirect( get_revalidate_url( /* TODO, current rest-api-endpoint url here... */ ) );
			die();
		}

		// User must have confirmed 2FA to get here.
		$user = wp_get_current_user();

		// If no user, bail.
		if ( ! $user || ! $user->exists() ) {
			return false;
		}

		// Check if the user is a committer.
		$committers = Tools::get_plugin_committers( $request['plugin_slug'], false );
		if ( $user && in_array( $user->user_login, $committers, true ) ) {
			return true;
		}

		return new WP_Error( 'not_a_committer', 'The authorized user is not a committer.', 403 );
	}

	/**
	 * Process a ZIP upload and commit it to SVN.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function upload( $request ) {
		global $post;
		$post = Plugin_Directory::get_plugin_post( $request['plugin_slug'] );

		// Validate that we expected a ZIP to be uploaded.
		$file = reset( $_FILES );
		if ( ! $file ) {
			return new WP_Error( 'no_file', 'No file was uploaded.', 400 );
		}

		// Start the automated SVN process.
		$svn_automations = new SVN_Automations( $post );

		// Import the ZIP to the SVN repositories trunk folder.
		$result = $svn_automations->import_zip_to_trunk( $file['tmp_name'] );
		if ( ! $result || is_wp_error( $result ) ) {
			return $result;
		}

		// Tag it, and set as stable.
		if ( $request['set_as_stable'] ) {
			$svn_automations->create_tag_from_trunk( true );
		}

		// Commit the new version.
		$result = $svn_automations->commit();
		if ( ! $result ) {
			return new WP_Error( 'commit_failed', 'An error occured during the SVN commit.', 500 );
		}

		return true;
	}

}
