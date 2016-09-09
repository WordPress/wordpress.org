<?php

namespace WordPressdotorg\GlotPress\Plugin_Directory\Language_Pack;

use GP;
use WordPressdotorg\GlotPress\Plugin_Directory\Plugin;

class Build_Trigger {

	/**
	 * The name of the schedule hook.
	 */
	const HOOK = 'wporg_translate_build_plugin_language_pack';

	/**
	 * The time delay in minutes before a build is triggered.
	 */
	const TIME_DELAY = 30;

	/**
	 * Array of plugin slugs.
	 *
	 * @var array
	 */
	private $queue = [];

	/**
	 * Registers actions and filters.
	 */
	public function register_events() {
		add_action( 'gp_translation_created', [ $this, 'queue_project_on_translation_edit' ] );
		add_action( 'gp_translation_saved', [ $this, 'queue_project_on_translation_edit' ] );
		add_action( 'gp_originals_imported', [ $this, 'queue_project_on_originals_import' ], 10, 5 );

		add_filter( 'schedule_event', [ $this, 'limit_duplicate_events' ] );

		add_action( 'shutdown', [ $this, 'trigger_build' ] );
	}

	/**
	 * Adds a project to a queue when a translation was created
	 * or updated.
	 *
	 * @param \GP_Translation $translation Created/updated translation.
	 */
	public function queue_project_on_translation_edit( $translation ) {
		global $wpdb;

		// Only current translations without warnings.
		if ( 'current' !== $translation->status || ! empty( $translation->warnings ) ) {
			return;
		}

		$project = GP::$project->one(
			"SELECT p.* FROM {$wpdb->gp_projects} AS p JOIN {$wpdb->gp_originals} AS o ON o.project_id = p.id WHERE o.id = %d",
			$translation->original_id
		);

		if ( ! $project || ! Plugin::project_is_plugin( $project->path ) ) {
			return;
		}

		$project_parts = explode( '/', $project->path ); // wp-plugins/$plugin_slug/$branch

		// No language packs for readmes.
		if ( ! isset( $project_parts[2] ) || 'dev-readme' === $project_parts[2] || 'stable-readme' === $project_parts[2] ) {
			return;
		}

		if ( isset( $project_parts[1] ) ) {
			$this->queue[ $project_parts[1] ] = true;
		}
	}

	/**
	 * Adds a project to a queue when originals get imported.
	 *
	 * @param string $project_id          Project ID the import was made to.
	 * @param int    $originals_added     Number or total originals added.
	 * @param int    $originals_existing  Number of existing originals updated.
	 * @param int    $originals_obsoleted Number of originals that were marked as obsolete.
	 * @param int    $originals_fuzzied   Number of originals that were close matches of old ones and thus marked as fuzzy.
	 */
	public function queue_project_on_originals_import( $project_id, $originals_added, $originals_existing, $originals_obsoleted, $originals_fuzzied ) {
		if ( ! $originals_added && ! $originals_existing && ! $originals_fuzzied && ! $originals_obsoleted ) {
			return;
		}

		$project = GP::$project->get( $project_id );

		if ( ! $project || ! Plugin::project_is_plugin( $project->path ) ) {
			return;
		}

		$project_parts = explode( '/', $project->path ); // wp-plugins/$plugin_slug/$branch

		// No language packs for readmes.
		if ( ! isset( $project_parts[2] ) || 'dev-readme' === $project_parts[2] || 'stable-readme' === $project_parts[2] ) {
			return;
		}

		if ( isset( $project_parts[1] ) ) {
			$this->queue[ $project_parts[1] ] = true;
		}
	}

	/**
	 * Schedules a build for a plugin language pack.
	 */
	public function trigger_build() {
		if ( empty( $this->queue ) ) {
			return;
		}

		foreach ( array_keys( $this->queue ) as $plugin_slug ) {
			wp_schedule_single_event( time() + self::TIME_DELAY * MINUTE_IN_SECONDS, self::HOOK, [
				[
					'plugin' => $plugin_slug,
				],
			] );
		}
	}

	/**
	 * Prevents scheduling a duplicate if there's already an identical event due within 30 minutes of it.
	 *
	 * @param stdClass $event {
	 *     An object containing an event's data.
	 *
	 *     @type string       $hook      Action hook to execute when event is run.
	 *     @type int          $timestamp Unix timestamp (UTC) for when to run the event.
	 *     @type string|false $schedule  How often the event should recur. See `wp_get_schedules()`.
	 *     @type array        $args      Arguments to pass to the hook's callback function.
	 * }
	 * @return bool|stdClass False if there is already an event, otherwise the original event data.
	 */
	public function limit_duplicate_events( $event ) {
		if ( self::HOOK !== $event->hook ) {
			return $event;
		}

		$next = wp_next_scheduled( $event->hook, $event->args );
		if ( $next && abs( $next - $event->timestamp ) <= self::TIME_DELAY * MINUTE_IN_SECONDS ) {
			return false;
		}

		return $event;
	}
}
