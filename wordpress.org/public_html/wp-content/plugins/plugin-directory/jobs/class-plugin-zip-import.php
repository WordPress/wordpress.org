<?php
namespace WordPressdotorg\Plugin_Directory\Jobs;

use Exception;
use WordPressdotorg\Plugin_Directory\Tools\SVN_Automation;

/**
 * Import a ZIP into plugins.svn.
 *
 * @package WordPressdotorg\Plugin_Directory\Jobs
 */
class Plugin_ZIP_Import {

	public static function queue( $plugin_slug, $zip_reference, $set_as_stable = true, $author_id = null ) {
		// If there's another ZIP import already scheduled, abort.
		if ( Manager::get_scheduled_time( "import_zip:{$plugin_slug}", 'last' ) ) {
			return false;
		}

		$author_id ??= get_current_user_id();

		wp_schedule_single_event(
			time() + 5,
			"import_zip:{$plugin_slug}",
			array(
				$plugin_slug,
				$zip_reference,
				$set_as_stable,
				$author_id,
			)
		);
	}

	/**
	 * The cron trigger for the import job.
	 *
	 * @param string     $plugin_slug   The plugin slug.
	 * @param int|string $zip_reference The ZIP post ID, or URL to ZIP.
	 * @param bool       $set_as_stable Whether to set the imported ZIP as the stable version.
	 * @param int        $author_id     The author ID for the import.
	 */
	public static function cron_trigger( $plugin_slug, $zip_reference, $set_as_stable, $author_id ) {
		$plugin = Plugin_Directory::get_plugin_post( $plugin_slug );

		if ( is_numeric( $zip_reference ) ) {
			// Fetch the ZIP details.
			$zip = get_post( $zip_post_id );
			if ( ! $zip ) {
				fwrite( STDERR, "[{$plugin_slug}] ZIP Import Failed: ZIP post not found.\n" );
				return false;
			}

			// Use the ZIP post author if no author ID is provided.
			if ( ! $author_id ) {
				$author_id = $zip->post_author;
			}

			// Local path to the ZIP.
			$zip_filepath = get_attached_file( $zip->ID );
		} elseif ( $zip_reference && preg_match( '/^https?:\/\//', $zip_reference ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';

			// Download the ZIP.
			$zip_filepath = download_url( $zip_reference );
			if ( is_wp_error( $zip_filepath ) ) {
				fwrite( STDERR, "[{$plugin_slug}] ZIP Import Failed: " . $zip_filepath->get_error_message() . "\n" );
				return false;
			}

			// Cleanup the ZIP on shutdown.
			add_action( 'shutdown', function() use ( $zip_filepath ) {
				unlink( $zip_filepath );
			} );

		} else {
			fwrite( STDERR, "[{$plugin_slug}] ZIP Import Failed: Invalid ZIP reference.\n" );
			return false;
		}

		// Start the automated SVN process.
		$svn_automations = new SVN_Automations( $plugin );

		// Import the ZIP to the SVN repositories trunk folder.
		$result = $svn_automations->import_zip_to_trunk( $zip_filepath );
		if ( is_wp_error( $result ) ) {
			fwrite( STDERR, "[{$plugin_slug}] ZIP Import Failed: " . $result->get_error_message() . "\n" );
			return false;
		}

		// Tag it, and set as stable.
		if ( $set_as_stable ) {
			$result = $svn_automations->create_tag_from_trunk( true );
			if ( is_wp_error( $result ) ) {
				fwrite( STDERR, "[{$plugin_slug}] ZIP Import Failed: " . $result->get_error_message() . "\n" );
				return false;
			}
		}

		// Commit the new version.
		$result = $svn_automations->commit();
		if ( ! $result ) {
			fwrite( STDERR, "[{$plugin_slug}] ZIP Import Failed: An error occured during the SVN commit.\n" );
			return false;
		}

		return true;
	}

}
