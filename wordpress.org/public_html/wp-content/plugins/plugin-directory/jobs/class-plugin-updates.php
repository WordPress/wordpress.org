<?php
/**
 * Coordinates plugin update scan jobs.
 *
 * @package WordPressdotorg\Plugin_Directory\Jobs
 */

namespace WordPressdotorg\Plugin_Directory\Jobs;

use WordPressdotorg\Plugin_Directory\Plugin_Directory;

/**
 * Coordinates plugin update scan jobs.
 *
 * @package WordPressdotorg\Plugin_Directory\Jobs
 */
class Plugin_Updates {

	/**
	 * Watch for plugin imports and queue update scans if needed.
	 *
	 * @param \WP_Post $plugin           The plugin post.
	 * @param string   $stable_tag       The new stable tag.
	 * @param string   $old_stable_tag   The old stable tag.
	 * @param array    $changed_svn_tags The SVN tags that were changed.
	 * @param int      $svn_revision     The SVN revision number.
	 * @param array    $warnings         The import warnings.
	 */
	public static function wporg_plugins_imported( $plugin, $stable_tag, $old_stable_tag, $changed_svn_tags, $svn_revision, $warnings = array() ) {
		$to_scan = [];
		foreach ( (array) $changed_svn_tags as $tag ) {
			if (
				// Always scan trunk if it was touched.
				'trunk' === $tag ||
				// Only scan tags that are > current stable, avoids scanning old tags when deleted.
				version_compare( $tag, $stable_tag, '>=' )
			) {
				$to_scan[] = $tag;
			}
		}

		// If only old tags were affected, we don't need to scan anything.
		if ( ! $to_scan ) {
			return;
		}

		// Always scan the current stable release.
		$to_scan[] = $stable_tag;

		$to_scan = array_unique( $to_scan );

		self::queue(
			$plugin->post_name,
			$to_scan,
			array(
				'stable_tag'       => $stable_tag,
				'old_stable_tag'   => $old_stable_tag,
				'changed_svn_tags' => array_values( array_map( 'strval', (array) $changed_svn_tags ) ),
				'svn_revision'     => (int) $svn_revision,
				'warnings'         => is_array( $warnings ) ? $warnings : array(),
			)
		);
	}

	/**
	 * Actually queue a scan job.
	 *
	 * @param string $plugin_slug The plugin slug.
	 * @param array  ...$args     The data to pass to the job.
	 */
	public static function queue( $plugin_slug, ...$args ) {
		// If one scan is already scheduled, run this one later so concurrent imports serialize.
		$when_to_run = time() + 5;
		$next_scheduled = Manager::get_scheduled_time( "scan_plugin:{$plugin_slug}", 'last' );
		if ( $next_scheduled ) {
			$when_to_run = $next_scheduled + HOUR_IN_SECONDS;
		}

		wp_schedule_single_event(
			$when_to_run,
			"scan_plugin:{$plugin_slug}",
			array_merge( array( $plugin_slug ), $args ),
		);
	}

	/**
	 * Cron callback to scan a plugin update.
	 *
	 * @param string     $plugin_slug     The plugin slug.
	 * @param array      $to_scan         The tags to scan with PCP.
	 * @param array|bool $gandalf_context The import context for Gandalf.
	 */
	public static function cron_trigger( $plugin_slug, $to_scan, $gandalf_context = false ) {
		Plugin_Updates_PCP::cron_trigger( $plugin_slug, $to_scan );

		$plugin = Plugin_Directory::get_plugin_post( $plugin_slug );

		if ( $plugin && $gandalf_context ) {
			Plugin_Updates_Gandalf::dispatch_from_import_context( $plugin, $gandalf_context );
		}
	}
}
