<?php
namespace WordPressdotorg\Plugin_Directory\Tools;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\CLI\Import;
use WordPressdotorg\Plugin_Directory\Tools;
use WordPressdotorg\Plugin_Directory\Tools\SVN;
use WordPressdotorg\Plugin_Directory\Tools\Filesystem;
use WordPressdotorg\Plugin_Directory\Readme\Parser;
use WP_Error;

/**
 * Automations for using SVN
 *
 * @package WordPressdotorg\Plugin_Directory\Tools
 */
class SVN_Automation {

	/**
	 * The plugin post object.
	 *
	 * @var WP_Post.
	 */
	protected $plugin  = false;

	/**
	 * The temporary SVN directory.
	 *
	 * @var string
	 */
	protected $svn_tmp = '';

	/**
	 * The default commit message.
	 *
	 * @var string
	 */
	public $default_commit_message = 'Automated SVN commit.';

	/**
	 * Constructor.
	 *
	 * @param WP_Post|string $plugin The plugin post object, or the plugin slug.
	 */
	public function __construct( $plugin ) {
		$this->plugin  = Plugin_Directory::get_plugin_post( $plugin );
		$this->svn_tmp = Filesystem::temp_directory( 'svn-' . $this->plugin->post_name );

		if ( ! $this->svn_tmp ) {
			return;
		}

		$result = SVN::checkout(
			Import::PLUGIN_SVN_BASE . '/' . $this->plugin->post_name,
			$this->svn_tmp,
			[ 'depth' => 'empty' ]
		);
		if ( ! $result['result'] ) {
			$this->svn_tmp = false;
			return;
		}

		// We've created an empty checkout, populate it with trunk files and available tags.
		// Error handling is skipped as this will be caught in the main methods.
		SVN::up( $this->svn_tmp . '/trunk/', [ 'set-depth' => 'infinity' ] );
		SVN::up( $this->svn_tmp . '/tags/',  [ 'depth' => 'immediates' ] );
	}

	/**
	 * Import a ZIP file to the SVN repository.
	 *
	 * @param string $zip_path The path to the ZIP file.
	 * @return bool|WP_Error true on success, WP_Error on failure.
	 */
	public function import_zip_to_trunk( $zip_path ) {
		if ( ! $this->svn_tmp ) {
			return new WP_Error( 'svn_tmp_not_found', 'SVN temp directory not found.' );
		}

		$trunk_path = $this->svn_tmp . '/trunk/';

		// Create a temporary folder for the ZIP, and unzip.
		$zip_temp = Filesystem::temp_directory( 'zip-' . $this->plugin->post_name . '-' . basename( $zip_path ) );
		$zip_file = Filesystem::unzip( $zip_path, $zip_temp );

		// Validate the values are expected
		$headers  = Import::find_plugin_headers( $zip_temp, 2 );

		if ( ! $headers ) {
			return new WP_Error( 'no_plugin', 'No plugin was detected in your ZIP file.', 400 );
		}
		$version = $headers->Version ?? '0.0';

		/*
		 * Validate that the version is greater than the existing version.
		 *
		 * Note: This prevents uploading a security release for a previous branch. Those should be done via SVN directly.
		 */
		if ( ! $version || ! version_compare( $version, $this->plugin->version, '>' ) ) {
			return new WP_Error(
				'version_not_newer',
				sprintf(
					'The version in the ZIP file is not newer than the existing version. Please upload a version greater than %s, found %s.',
					esc_html( $this->plugin->version ),
					esc_html( $headers->Version )
				),
				400
			);
		}

		$this->default_commit_message = "Importing version {$version}.";

		// Find the base directory of the ZIP
		$plugin_root = dirname( $headers->PluginFile );

		// Remove all files from the SVN folder
		Filesystem::rmdir( $trunk_path );

		// Copy the ZIP files to trunk
		Filesystem::copy( $plugin_root, $trunk_path, true );

		// Add new files to SVN, remove the old ones.
		SVN::add_remove( $trunk_path );

		return true;
	}

	/**
	 * Create a tag from the current version in trunk.
	 *
	 * @param bool $update_stable_tag Whether to update the stable tag.
	 * @return bool|WP_Error true on success, WP_Error on failure.
	 */
	public function create_tag_from_trunk( $update_stable_tag = true ) {
		if ( ! $this->svn_tmp ) {
			return new WP_Error( 'svn_tmp_not_found', 'SVN temp directory not found.' );
		}

		// Determine version of trunk
		$trunk_path = $this->svn_tmp . '/trunk/';
		$headers    = Import::find_plugin_headers( $trunk_path, 2 );
		$version    = $headers->Version ?? '';

		if ( empty( $version ) ) {
			return new WP_Error( 'no_plugin', 'No plugin was detected, or an invalid version is specified.', 400 );
		}

		// check no tag exists for that version
		$new_tag_path = $this->svn_tmp . '/tags/' . $version . '/';
		if ( is_dir( $new_tag_path ) ) {
			return new WP_Error( 'tag_exists', 'A tag already exists for this version.', 400 );
		}

		$this->default_commit_message = "Creating {$version} tag.";

		// update the stable_tag in the readme.xxx file.
		if ( $update_stable_tag ) {
			$this->default_commit_message = "Creating {$version} tag and marking as stable.";

			$readme_file = Import::find_readme_file( $trunk_path );
			if ( ! $readme_file ) {
				return new WP_Error( 'no_readme', 'Unable to find a readme file.', 500 );
			}

			$readme_contents = file_get_contents( $readme_file );
			$readme_parsed   = new Parser( $readme_contents );

			// If there's no stable tag present in the readme, add it.
			if (
				'' === $readme_parsed->stable_tag &&
				! preg_match( '!^[\s*]*Stable Tag:!i', $readme_contents )
			) {
				// Find the first header..
				$valid_headers = array_keys( $readme_parsed->valid_headers );
				$valid_headers = array_map( function( $header ) {
					return preg_quote( $header, '!' );
				}, $valid_headers );
				$valid_headers = implode( '|', $valid_headers );

				$readme_contents = preg_replace(
					$regex = '/^(([\s*]*)(' . $valid_headers . '):.+([\r\n]+))/mi',
					// Prepend the Stable Tag line to the first header, using the same line-ending and prefix.
					"\\2Stable Tag: {$version}\\4\\1",
					$readme_contents,
					1 // Only replace the first header.
				);
			}

			// If the version is different, update the stable tag.
			if ( $version !== $readme_parsed->stable_tag ) {
				$new_contents = preg_replace(
					'/^([\s*]*Stable Tag):\s*.+(\r)?$/mi',
					"\\1: $version\\2",
					$readme_contents,
					1
				);
				file_put_contents( $readme_file, $new_contents );
			}

			// Again, check the readme has the expected version.
			$readme_parsed = new Parser( $readme_file );
			if ( $readme_parsed->stable_tag !== $version ) {
				return new WP_Error(
					'stable_tag_not_updated',
					'The Stable Tag was not able to be updated in the readme. Please ensure a "Stable Tag: x.y" header exists in your readme.',
					500
				);
			}
		}

		// copy trunk to tags
		$result = SVN::copy( $trunk_path, $new_tag_path );
		if ( ! $result['result'] ) {
			return new WP_Error( 'copy_failed', 'Failed to copy trunk to the tag.', 500 );
		}

		return true;
	}

	/**
	 * Commit the changes to SVN.
	 *
	 * @param string $message Optional. The commit message.
	 * @return bool
	 */
	public function commit( $message = null ) {
		// Write the changes to SVN.
		$message ??= $this->default_commit_message;
		$username  = wp_get_current_user()->user_login;

		/*
		 * NOTE: This commits as the plugin management user.
		 * The Author byline is added to the commit message to show the actual actor.
		 */
		$result = SVN::commit(
			$this->svn_tmp,
			"{$this->plugin->post_name}: {$message}\nAuthor: {$username}."
		);

		return (bool) $result['result'];
	}
}