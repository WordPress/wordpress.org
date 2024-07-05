<?php
namespace WordPressdotorg\Plugin_Directory\API\Routes;

use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\CLI\Import;
use WordPressdotorg\Plugin_Directory\Tools;
use WordPressdotorg\Plugin_Directory\Tools\SVN;
use WordPressdotorg\Plugin_Directory\Tools\Filesystem;
use WordPressdotorg\Plugin_Directory\API\Base;
use WordPressdotorg\Plugin_Directory\Shortcodes\Upload_Handler;
use WordPressdotorg\Plugin_Directory\Readme\Validator as Readme_Validator;
use WP_REST_Server;
use WP_Error;

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
		// Auth should have been done by BASIC Auth.
		if ( empty( $_SERVER['PHP_AUTH_USER'] ) || empty( $_SERVER['PHP_AUTH_PW'] ) ) {
			return false;
		}

		/**
		 * Disable 2FA requirement for application passwords for this request.
		 *
		 * This should be temporary, until such a time as our SVN authentication
		 * can be updated to support Application Passwords.
		 *
		 * TODO: The earlier auth hook will have ended with the following error if 2FA was required:
		 * `{"code":"incorrect_password","message":"The provided password is an invalid application password."}`
		 */
		add_filter( 'two_factor_user_api_login_enable', '__return_true' );

		// TODO: This will need to change if SVN Auth changes to a dedicated password. This should be done by attaching a new auth filter for it.
		$user = wp_authenticate( $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'] );
		if ( ! $user || is_wp_error( $user ) ) {
			return $user;
		}

		// Check if the user is a committer.
		$committers = Tools::get_plugin_committers( $request['plugin_slug'], false );
		if ( in_array( $user->user_login, $committers, true ) ) {

			// 2FA will have prevented this happening earlier.
			wp_set_current_user( $user );

			return true;
		}

		return new WP_Error( 'not_a_committer', 'The authorized user is not a committer.', 403 );
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

		// Validate that the version is greater than the existing version.
		if ( ! version_compare( $version, $post->version, '>' ) ) {
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
		SVN::up( $svn_tmp . '/trunk/', [ 'depth' => 'files' ] );
		SVN::up( $svn_tmp . '/tags/', [ 'depth' => 'immediates' ] );

		$new_tag_folder = $svn_tmp . '/tags/' . $version;
		if ( is_dir( $new_tag_folder ) ) {
			return new WP_Error(
				'version_exists',
				sprintf(
					'The version %s already exists in SVN.',
					esc_html( $version )
				),
				400
			);
		}

		// Determine the latest (yet older) version of the plugin tags.
		$latest_tag = (static function() use ( $svn_tmp, $version ) {
			// GitHub versions often have a leading `v`, remove it.
			$version = preg_replace( '/^v(\d+)/', '$1', $version );

			$tags = glob( $svn_tmp . '/tags/*', GLOB_ONLYDIR );
			$tags = array_map( 'basename', $tags );

			// GitHub versions often have a leading `v`, remove it.
			$tags = preg_replace( '/^v(\d+)/', '$1', $tags );

			$tags = array_filter( $tags, function( $tag ) use ( $version ) {
				return version_compare( $tag, $version, '<' );
			} );

			usort( $tags, 'version_compare' );

			return array_pop( $tags );
		})();

		// TODO: This should probably always overwrite trunk, and copy that to the tag.

		// If we have a latest tag, use it as the base for the new tag.
		if ( $latest_tag ) {
			$result = SVN::up( $svn_tmp . '/tags/' . $latest_tag, [ 'set-depth' => 'infinity' ] );
			if ( $result['result'] ) {
				$result = SVN::copy( $svn_tmp . '/tags/' . $latest_tag, $new_tag_folder );
			}
			if ( ! $result['result'] ) {
				return new WP_Error( 'copy_failed', 'Failed to copy the new tag directory.', 500 );
			}

			// Remove all files from the new tag folder, in prep for overwriting, now that the SVN metadata is set.
			Filesystem::rmdir( $new_tag_folder );
		}
		if ( ! mkdir( $new_tag_folder ) ) {
			return new WP_Error( 'mkdir_failed', 'Failed to create the new tag directory.', 500 );
		} else {
			SVN::add( $new_tag_folder ); // May fail, if the copy was used.
		}

		// Copy plugin files into the new tag.
		Filesystem::copy( $plugin_root, $new_tag_folder, true );
		SVN::add_remove( $new_tag_folder );

		/**
		 * When we're setting the upload as stable, we'll do some additional things:
		 *  - Overwrite the trunk readme with the version supplied.
		 *  - Ensure that the Stable Tag in the trunk readme is updated to the new tag.
		 *
		 * TODO: This should be a separate step that can be run individually as well, for https://meta.trac.wordpress.org/ticket/5484
		 */
		if ( $request['set_as_stable'] ) {
			$readme = Import::find_readme_file( $svn_tmp . '/trunk/' );
			// Overwrite the trunk readme with the new version.
			$plugin_readme = Import::find_readme_file( $plugin_root );
			if ( $plugin_readme ) {
				copy( $plugin_readme, $readme ); // Will overwrite any existing.

				SVN::add( $readme ); // May fail if a readme was already in place.
			}

			if ( ! $readme ) {
				return new WP_Error( 'no_readme', 'Unable to find a readme.txt file.', 500 );
			}

			// Update Stable Tag with the new version number, in the event it wasn't already.
			$readme_contents = file_get_contents( $readme );
			$new_contents    = preg_replace( '/^([\s*]*Stable Tag):\s*.+(\r)?$/mi', "\\1: $version\\2", $readme_contents, 1 );
			if ( $readme_contents === $new_contents ) {
				// TODO: Can we add the header if required?
				return new WP_Error( 'stable_tag_not_updated', 'The Stable Tag was not able to be updated in the readme. Please ensure a "Stable Tag: x.y" header exists in your readme.', 500 );
			}
			file_put_contents( $readme, $new_contents );
		}

		$commit = SVN::commit(
			$svn_tmp,
			sprintf(
				'Adding version %s of %s',
				$version,
				$post->post_name
			),
			[
				'username' => $_SERVER['PHP_AUTH_USER'],
				'password' => $_SERVER['PHP_AUTH_PW'] . 'nonono'
			]
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
