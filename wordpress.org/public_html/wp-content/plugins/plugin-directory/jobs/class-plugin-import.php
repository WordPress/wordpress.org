<?php
namespace WordPressdotorg\Plugin_Directory\Jobs;

use Exception;
use WordPressdotorg\Plugin_Directory\CLI;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\Readme\Parser as Readme_Parser;

/**
 * Import plugin changes into WordPress.
 *
 * @package WordPressdotorg\Plugin_Directory\Jobs
 */
class Plugin_Import {

	/**
	 * Queue an import job for a plugin, merging into any pending future event for the
	 * same plugin so a single import covers all the SVN changes seen so far.
	 *
	 * @param string $plugin_slug The plugin slug.
	 * @param array  $plugin_data Data about the SVN change (tags_touched, revisions, etc).
	 */
	public static function queue( $plugin_slug, $plugin_data ) {
		$new_args = array_merge( array( 'plugin' => $plugin_slug ), $plugin_data );

		/*
		 * If there's already a future-scheduled import for this plugin and nothing
		 * is currently running, fold the new request into it. This handles two
		 * cases under one rule:
		 *
		 *  - A bulk batch re-index queued an event far in the future; a fresh
		 *    commit should pull that forward to the usual import time.
		 *  - The 15-minute trunk grace window (see queue_run_time()): an author
		 *    commits to /trunk first and then `svn cp trunk tags/X.Y` a moment
		 *    later. The first commit's event is delayed; the second merges into
		 *    it so a single import publishes from the tag, not from a trunk
		 *    fallback that the tag commit then has to overwrite.
		 *
		 * Skip the merge for events due in the next 5 seconds: a Cavalcade
		 * runner could claim the row between our status check and the row write,
		 * and the resulting save would clobber the runner's `status='running'`
		 * transition. 5s assumes the status read at the top of this function
		 * is still fresh by the time `update_scheduled_event` writes.
		 */
		$next_scheduled = Manager::get_scheduled_time( "import_plugin:{$plugin_slug}", 'next' );
		if ( $next_scheduled && $next_scheduled > time() + 5 && ! Manager::is_event_running( "import_plugin:{$plugin_slug}" ) ) {
			$existing      = Manager::get_scheduled_events( "import_plugin:{$plugin_slug}", $next_scheduled );
			$existing_args = $existing[0]['args'][0] ?? array();
			$merged_args   = self::merge_plugin_data( $existing_args, $new_args );

			$updated = Manager::update_scheduled_event(
				"import_plugin:{$plugin_slug}",
				$next_scheduled,
				array(
					'nextrun' => min( $next_scheduled, self::queue_run_time( $plugin_slug, $merged_args ) ),
					'args'    => array( $merged_args ),
				)
			);

			if ( $updated ) {
				return;
			}
		}

		$when_to_run = self::queue_run_time( $plugin_slug, $new_args );

		// To avoid a situation where two imports run concurrently, if one is already scheduled or in flight, run it 1hr later (we'll trigger it after the current one finishes).
		$last_scheduled = Manager::get_scheduled_time( "import_plugin:{$plugin_slug}", 'last' );
		if ( $last_scheduled ) {
			$when_to_run = $last_scheduled + HOUR_IN_SECONDS;
		} elseif ( Manager::is_event_running( "import_plugin:{$plugin_slug}" ) ) {
			$when_to_run = time() + HOUR_IN_SECONDS;
		}

		wp_schedule_single_event(
			$when_to_run,
			"import_plugin:{$plugin_slug}",
			array( $new_args )
		);
	}

	/**
	 * Decide when an import job should run, based on the SVN changes it covers.
	 *
	 * Authors that release from a tag commonly commit the version bump to /trunk
	 * first and then `svn cp trunk tags/X.Y` a moment later. Running the import
	 * immediately on the trunk commit publishes a trunk-fallback release that
	 * the follow-up tag commit then re-publishes from the tag. To collapse the
	 * two into one import, trunk-only updates are deferred by 15 minutes — that
	 * gives the follow-up tag commit time to merge into the same job (see
	 * queue()). Tag-touching changes (additions or deletions) run immediately.
	 *
	 * The grace window is bypassed when the only change is a `Stable Tag` flip
	 * in /trunk/readme.txt pointing at a tag that already exists in /tags/:
	 * there's no follow-up tag to wait for in that case.
	 *
	 * @param string $plugin_slug The plugin slug.
	 * @param array  $args        The args for the import job (post-merge where applicable).
	 * @return int Unix timestamp for when the event should run.
	 */
	protected static function queue_run_time( $plugin_slug, $args ) {
		if ( ! self::is_trunk_only_update( $args ) ) {
			return time() + 5;
		}

		if ( ! empty( $args['readme_touched'] ) && self::trunk_stable_tag_flip_to_existing_tag( $plugin_slug ) ) {
			return time() + 5;
		}

		return time() + 15 * MINUTE_IN_SECONDS;
	}

	/**
	 * Whether an import only covers /trunk (no tags added or removed).
	 *
	 * @param array $args The args for the import job.
	 * @return bool
	 */
	protected static function is_trunk_only_update( $args ) {
		$tags_touched = (array) ( $args['tags_touched'] ?? array() );
		$tags_deleted = (array) ( $args['tags_deleted'] ?? array() );

		return $tags_touched && array( 'trunk' ) === array_values( array_unique( $tags_touched ) ) && ! $tags_deleted;
	}

	/**
	 * Whether /trunk/readme.txt's Stable Tag points to a tag that's already in
	 * /tags/ AND differs from the directory's current stable_tag.
	 *
	 * Indicates a release-by-readme-flip — the author isn't going to follow up
	 * with `svn cp trunk tags/X.Y` because the tag already exists. Used to skip
	 * the 15-minute trunk grace window in that case.
	 *
	 * @param string $plugin_slug The plugin slug.
	 * @return bool
	 */
	protected static function trunk_stable_tag_flip_to_existing_tag( $plugin_slug ) {
		$plugin = Plugin_Directory::get_plugin_post( $plugin_slug );
		if ( ! $plugin ) {
			return false;
		}

		$readme         = new Readme_Parser( "https://plugins.svn.wordpress.org/{$plugin_slug}/trunk/readme.txt" );
		$new_stable_tag = $readme->stable_tag;

		if ( ! $new_stable_tag || 'trunk' === $new_stable_tag ) {
			return false;
		}

		if ( get_post_meta( $plugin->ID, 'stable_tag', true ) === $new_stable_tag ) {
			return false;
		}

		$tagged_versions = (array) get_post_meta( $plugin->ID, 'tagged_versions', true );

		return in_array( $new_stable_tag, $tagged_versions, true );
	}

	/**
	 * Merge two plugin_data payloads into a single import-job payload.
	 *
	 * Used when folding an already-scheduled future event into a newer request so
	 * neither set of changes is lost.
	 *
	 * @param array $existing The args from the currently-scheduled event.
	 * @param array $incoming The args from the new request.
	 * @return array Merged args ready to pass to the import job.
	 */
	protected static function merge_plugin_data( array $existing, array $incoming ) {
		$merged = array_merge( $existing, $incoming );

		foreach ( array( 'tags_touched', 'tags_deleted', 'revisions' ) as $key ) {
			$existing_values = (array) ( $existing[ $key ] ?? array() );
			$incoming_values = (array) ( $incoming[ $key ] ?? array() );

			$merged[ $key ] = array_values( array_unique( array_merge( $existing_values, $incoming_values ) ) );
		}

		foreach ( array( 'readme_touched', 'code_touched', 'assets_touched' ) as $key ) {
			$merged[ $key ] = ! empty( $existing[ $key ] ) || ! empty( $incoming[ $key ] );
		}

		return $merged;
	}

	/**
	 * The cron trigger for the import job.
	 */
	public static function cron_trigger( $plugin_data ) {
		$plugin_slug  = $plugin_data['plugin'];

		// Set some default values if not included from the caller.
		$plugin_data['tags_touched']   ??= array( 'trunk' );
		$plugin_data['tags_deleted']   ??= array();
		$plugin_data['revisions']      ??= [ 0 ];
		$plugin_data['readme_touched'] ??= true;
		$plugin_data['code_touched']   ??= true;
		$plugin_data['assets_touched'] ??= true;

		$tags_touched = $plugin_data['tags_touched'];
		$tags_deleted = $plugin_data['tags_deleted'];
		$revision     = max( (array) $plugin_data['revisions'] );

		$importer = new CLI\Import();
		try {
			$importer->import_from_svn( $plugin_slug, $tags_touched, $tags_deleted, $revision );

			// Schedule a job to import any i18n changes from this commit
			Plugin_i18n_Import::queue( $plugin_slug, $plugin_data );
		} catch ( Exception $e ) {
			fwrite( STDERR, "[{$plugin_slug}] Plugin Import Failed: " . $e->getMessage() . "\n" );
		} finally {
			if ( $importer->plugin ) {
				update_post_meta( $importer->plugin->ID, '_last_import', time() );
				update_post_meta( $importer->plugin->ID, '_import_warnings', $importer->warnings );
			}
		}

		// Re-schedule any other jobs for this plugin to NOW()
		$hook = current_filter();
		if ( $next_timestamp = Manager::get_scheduled_time( $hook, 'next' ) ) {
			Manager::reschedule_event(
				$hook,
				time() + 10,
				$next_timestamp
			);
		}
	}

}
