<?php
namespace WordPressdotorg\Plugin_Directory\API\Routes;

use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\CLI\Import;
use WordPressdotorg\Plugin_Directory\Tools;
use WordPressdotorg\Plugin_Directory\Tools\SVN;
use WordPressdotorg\Plugin_Directory\Tools\Filesystem;
use WordPressdotorg\Plugin_Directory\API\Base;
use WP_REST_Server;
use WP_Error;
use WP_User;
use function WordPressdotorg\Security\SVNPasswords\check_svn_password;

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
		 * Auth should be either:
		 * a) SVN Password provided via BASIC Auth.
		 * b) 2FA'd user.
		 */

		$user = false;
		if ( ! empty( $_SERVER['PHP_AUTH_USER'] ) && empty( $_SERVER['PHP_AUTH_PW'] ) ) {
			/*
			 * Check the given credentials against SVN auth.
			 *
			 * Add a callback to auth by SVN password, this ensures that existing rate limits are applied.
			 *
			 * The Two-Factor plugin blocks all API auth if 2FA is required, so we need to bypass that.
			 */
			add_filter( 'two_factor_user_api_login_enable', '__return_true' );
			remove_filter( 'authenticate', 'wp_authenticate_cookie', 30 );
			remove_filter( 'authenticate', 'wp_authenticate_email_password', 20 );
			remove_filter( 'authenticate', 'wp_authenticate_username_password', 20 );
			remove_filter( 'authenticate', 'wp_authenticate_application_password', 20 );

			add_filter( 'authenticate', static function( $user, $username, $password ) {
				if ( $user instanceof WP_User ) {
					return $user;
				}

				$user = check_svn_password( $username, $password, true /* must be svn password */ );
			}, 20, 3 );

			$user = wp_authenticate( $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'] );
			if ( ! $user || is_wp_error( $user ) ) {
				return $user;
			}

			// 2FA will have prevented this happening earlier.
			wp_set_current_user( $user );

		} else {
			// Check the current user is 2FA'd.
			// TODO ^
			$user = wp_get_current_user();
		}

		// If no user, bail.
		if ( ! $user || ! $user->exists() ) {
			return false;
		}

		// Check if the user is a committer.
		$committers = Tools::get_plugin_committers( $request['plugin_slug'], false );
		if ( ! in_array( $user->user_login, $committers, true ) ) {
			return new WP_Error( 'not_a_committer', 'The authorized user is not a committer.', 403 );
		}

		return true;
	}

	/**
	 * Process a ZIP upload and commit it to SVN.
	 */
	public function upload( $request ) {
		global $post;
		$post = Plugin_Directory::get_plugin_post( $request['plugin_slug'] );

		// Validate that we expected a ZIP to be uploaded.
		$file = reset( $_FILES );
		if ( ! $file ) {
			return new WP_Error( 'no_file', 'No file was uploaded.', 400 );
		}

		$tmp_dir  = Filesystem::temp_directory( 'zip-' . $post->post_name );
		$zip_file = Filesystem::unzip( $file['tmp_name'], $tmp_dir );
		$headers  = Import::find_plugin_headers( $tmp_dir, 2 );

		if ( ! $headers ) {
			return new WP_Error( 'no_plugin', 'No plugin was detected in your ZIP file.', 400 );
		}
		$version = $headers->Version ?? '0.0';

		/*
		 * Validate that the version is greater than the existing version.
		 *
		 * Note: This prevents uploading a security release for a previous branch. Those should be done via SVN directly.
		 */
		if ( ! $version || ! version_compare( $version, $post->version, '>' ) ) {
			return new WP_Error(
				'version_not_newer',
				sprintf(
					'The version in the ZIP file is not newer than the existing version. Please upload a version greater than %s, found %s.',
					esc_html( $post->version ),
					esc_html( $headers->Version )
				),
				400
			);
		}

		// Account for plugins whose ZIPs include nested (or not) files, use the detected plugin file as the root folder.
		$plugin_root = dirname( $headers->PluginFile );

		// Checkout the SVN repository.
		$svn_tmp = Filesystem::temp_directory( 'svn-' . $post->post_name );
		$result  = SVN::checkout(
			Import::PLUGIN_SVN_BASE . '/' . $post->post_name,
			$svn_tmp,
			[ 'depth' => 'empty' ]
		);
		if ( ! $result['result'] ) {
			return new WP_Error( 'svn_failed', 'Failed to checkout the SVN repository.', 500 );
		}

		// Import the files expected. Skip error validation for now, if it fails other steps will catch it.
		SVN::up( $svn_tmp . '/trunk/', [ 'set-depth' => 'infinity' ] );
		SVN::up( $svn_tmp . '/tags/', [ 'depth' => 'immediates' ] );

		$trunk_folder   = $svn_tmp . '/trunk';
		$new_tag_folder = $svn_tmp . '/tags/' . $version;
		if ( is_dir( $new_tag_folder ) ) {
			return new WP_Error(
				'version_exists',
				sprintf(
					'The version %s is already tagged in SVN.',
					esc_html( $version )
				),
				400
			);
		}

		// Empty the trunk folder, as we'll overwrite it with the newly uploaded data.
		Filesystem::rmdir( $trunk_folder );

		// Copy plugin files into trunk.
		Filesystem::copy( $plugin_root, $trunk_folder, true );
		SVN::add_remove( $trunk_folder );

		/*
		 * Ensure the version is set as stable.
		 * 1) Find the readme file in trunk.
		 * 2) Set the value to the new tag we'll create, if it's not already set to that.
		 *
		 * TODO: This should be a separate step that can be run individually as well, for https://meta.trac.wordpress.org/ticket/5484
		 */
		$readme = Import::find_readme_file( $svn_tmp . '/trunk/' );
		if ( ! $readme ) {
			return new WP_Error( 'no_readme', 'Unable to find a readme file.', 500 );
		}

		$readme_contents = file_get_contents( $readme );
		if ( ! preg_match( '!^[\s*]*Stable Tag:\s*' . preg_quote( $version, '!' ) . '(\r)?$!mi' ) ) {
			$new_contents = preg_replace( '/^([\s*]*Stable Tag):\s*.+(\r)?$/mi', "\\1: $version\\2", $readme_contents, 1 );

			// If it's unchanged, can we add the header if required?
			if ( $readme_contents === $new_contents ) {
				return new WP_Error(
					'stable_tag_not_updated',
					'The Stable Tag was not able to be updated in the readme. Please ensure a "Stable Tag: x.y" header exists in your readme.',
					500
				);
			}

			file_put_contents( $readme, $new_contents );
		}

		// Finally, now copy trunk to the tag.
		$result = SVN::copy( $trunk_folder, $new_tag_folder );
		if ( ! $result['result'] ) {
			return new WP_Error( 'copy_failed', 'Failed to create the new tag directory.', 500 );
		}

		// Are we authing by user or the plugin directory?
		$commit_options = [];
		if ( ! empty( $_SERVER['PHP_AUTH_USER'] ) ) {
			$commit_options = [
				'username' => $_SERVER['PHP_AUTH_USER'],
				'password' => $_SERVER['PHP_AUTH_PW'] . 'nonono',
			];

			$message = sprintf(
				'Adding version %s of %s',
				$version,
				$post->post_name
			);
		} else {
			$message = sprintf(
				'Adding version %s of %s by %s',
				$version,
				$post->post_name,
				wp_get_current_user()->user_login
			);
		}

		// Commit the new version.
		$commit = SVN::commit(
			$svn_tmp,
			$message,
			$commit_options
		);
		if ( ! $commit['result'] ) {
			return new WP_Error( 'commit_failed', 'An error occured during the SVN commit.', 500 );
		}

		// DEBUG.
		$svn = [
			'list' => Filesystem::list( $svn_tmp, 'all', true ),
			'diff' => `svn diff $svn_tmp 2>&1`,
			'st'   => `svn st $svn_tmp 2>&1`,
			'ci'   => $commit
		];

		return compact( 'file', 'tmp_dir', 'zip_file', 'headers', 'readme', 'svn' );
	}

}
