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
		 * the Author byline is added to the commit message to show the actual actor.
		 */
		$result = SVN::commit(
			$this->svn_tmp,
			"{$this->plugin->post_name}: {$message}\nAuthor: {$username}"
		);

		return (bool) $result['result'];
	}
}