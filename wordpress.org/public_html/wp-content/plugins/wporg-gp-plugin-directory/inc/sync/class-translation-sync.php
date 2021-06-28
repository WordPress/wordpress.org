<?php

namespace WordPressdotorg\GlotPress\Plugin_Directory\Sync;

use GP;
use GP_Locales;
use GP_Translation;
use WordPressdotorg\GlotPress\Plugin_Directory\Plugin;

class Translation_Sync {

	private $queue = [];

	public $project_mapping = [
		'dev'           => 'stable',
		'stable'        => 'dev',
		'dev-readme'    => 'stable-readme',
		'stable-readme' => 'dev-readme',
	];

	public function register_events() {
		add_action( 'gp_translation_created', [ $this, 'queue_translation_for_sync' ], 5 );
		add_action( 'gp_translation_saved', [ $this, 'queue_translation_for_sync' ], 5 );
		add_action( 'gp_originals_imported', [ $this, 'trigger_translation_sync_on_originals_import' ], 5, 5 );

		add_action( 'wporg_translate_sync_plugin_translations', [ $this, 'sync_plugin_translations_on_commit' ] );

		add_action( 'shutdown', [ $this, 'sync_translations' ] );
	}

	/**
	 * Triggers a translation sync from dev to stable when originals get imported.
	 *
	 * @param string $project_id          Project ID the import was made to.
	 * @param int    $originals_added     Number or total originals added.
	 * @param int    $originals_existing  Number of existing originals updated.
	 * @param int    $originals_obsoleted Number of originals that were marked as obsolete.
	 * @param int    $originals_fuzzied   Number of originals that were close matches of old ones and thus marked as fuzzy.
	 */
	public function trigger_translation_sync_on_originals_import( $project_id, $originals_added, $originals_existing, $originals_obsoleted, $originals_fuzzied ) {
		if ( ! $originals_added && ! $originals_existing && ! $originals_fuzzied && ! $originals_obsoleted ) {
			return;
		}

		$project = GP::$project->get( $project_id );
		if ( ! $project || ! Plugin::project_is_plugin( $project->path ) ) {
			return;
		}

		// Sync translations only if the stable project was updated.
		if ( false === strpos( $project->path, '/stable' ) ) {
			return;
		}

		$project_parts  = explode( '/', $project->path ); // wp-plugins/$plugin_slug/$branch
		$plugin_slug    = $project_parts[1];
		$plugin_project = $project_parts[1] . '/' . $this->project_mapping[ $project_parts[2] ];

		wp_schedule_single_event( time() + 5 * MINUTE_IN_SECONDS, 'wporg_translate_sync_plugin_translations', [
			[
				'plugin'     => $plugin_slug,
				'gp_project' => $plugin_project,
			],
		] );
	}

	/**
	 * Starts the sync of plugin translations between two projects.
	 *
	 * Gets triggered by the cron API and the hook `wporg_translate_sync_plugin_translations`.
	 *
	 * @param array $args Arguments from the job. Should include the path
	 *                    of the GP project.
	 * @return bool False on failure, true on success.
	 */
	public function sync_plugin_translations_on_commit( $args ) {
		$project = GP::$project->by_path( Plugin::GP_MASTER_PROJECT . '/' . $args['gp_project'] );
		if ( ! $project ) {
			return false;
		}

		$translation_sets = GP::$translation_set->by_project_id( $project->id );
		if ( ! $translation_sets ) {
			return false;
		}

		$sub_project = basename( $args['gp_project'] );
		$sub_project_counterpart = $this->project_mapping[ $sub_project ];

		$timestamp = time();
		$message   = '';
		$updates   = 0;

		foreach ( $translation_sets as $translation_set ) {
			if ( 0 == $translation_set->current_count() ) {
				continue;
			}

			// Sync translations in a separate process.
			$cmd = WPORGTRANSLATE_WPCLI . ' wporg-translate sync-plugin-translations ' . escapeshellarg( $args['gp_project'] ) . ' ' . escapeshellarg( $translation_set->locale ) . ' --set=' . escapeshellarg( $translation_set->slug ) . ' 2>&1';
			$output = '';
			$return_var = 0;
			exec( $cmd, $output, $return_var );
			if ( $return_var ) {
				$message .= "\tFailure: " . implode( "\n\t", $output ) . "\n";
			} else {
				$message .= "\t" . implode( "\n\t", $output ) . "\n";
			}
			$updates += 1;
		}

		if ( ! $updates ) {
			$message .= "\tNo translations are available to sync.\n";
		}

		$message .= 'Translation sync was successfully processed.';

		$attachment = [
			'title'      => "Translation Sync for {$args['plugin']}",
			'title_link' => "https://translate.wordpress.org/projects/wp-plugins/{$args['plugin']}",
			'text'       => $message,
			'fallback'   => "Translations for {$args['plugin']} were synced.",
			'color'      => '#00a0d2',
			'mrkdwn_in'  => [ 'text' ],
			'ts'         => $timestamp,
		];
		$this->slack( $attachment );

		return true;
	}

	/**
	 * Adds a translation to a cache purge queue when a translation was created
	 * or updated.
	 *
	 * @param \GP_Translation $translation Created/updated translation.
	 */
	public function queue_translation_for_sync( $translation ) {
		global $wpdb;

		// Only propagate current translations without warnings.
		if ( 'current' !== $translation->status || ! empty( $translation->warnings ) ) {
			return;
		}

		$project = GP::$project->one(
			"SELECT p.* FROM {$wpdb->gp_projects} AS p JOIN {$wpdb->gp_originals} AS o ON o.project_id = p.id WHERE o.id = %d",
			$translation->original_id
		);

		if ( ! $project ) {
			return;
		}

		if ( ! Plugin::project_is_plugin( $project->path ) ) {
			return;
		}

		$this->queue[ $project->path ][ $translation->id ] = $translation;
	}

	/**
	 * Syncs translations between two plugin projects.
	 */
	public function sync_translations() {
		if ( empty( $this->queue ) ) {
			return;
		}

		// Avoid recursion.
		remove_action( 'gp_translation_created', [ $this, 'queue_translation_for_sync' ], 5 );
		remove_action( 'gp_translation_saved', [ $this, 'queue_translation_for_sync' ], 5 );

		foreach ( $this->queue as $project_path => $translations ) {
			$project = $this->get_dev_or_stable_project( $project_path );
			if ( ! $project ) {
				continue;
			}

			foreach ( $translations as $translation ) {
				$original = GP::$original->get( $translation->original_id );
				if ( ! $original ) {
					continue;
				}

				$translation_set = GP::$translation_set->get( $translation->translation_set_id );
				if ( ! $translation_set ) {
					continue;
				}

				$original_counterpart = GP::$original->by_project_id_and_entry(
					$project->id,
					$original,
					'+active'
				);

				if ( ! $original_counterpart ) {
					continue;
				}

				$translation_set_counterpart = GP::$translation_set->by_project_id_slug_and_locale(
					$project->id,
					$translation_set->slug,
					$translation_set->locale
				);

				if ( ! $translation_set_counterpart ) {
					continue;
				}

				$this->copy_translation_into_set( $translation, $translation_set_counterpart, $original_counterpart );
			}
		}
	}

	/**
	 * Duplicates a translation to another translation set.
	 *
	 * @param \GP_Translation     $translation         The translation which should be duplicated.
	 * @param \GP_Translation_Set $new_translation_set The new translation set.
	 * @param \GP_Original        $new_original        The new original.
	 * @return bool False on failure, true on success.
	 */
	private function copy_translation_into_set( $translation, $new_translation_set, $new_original ) {
		$locale = GP_Locales::by_slug( $new_translation_set->locale );
		$new_translation = [];

		for ( $i = 0; $i < $locale->nplurals; $i++ ) {
			$new_translation[] = $translation->{"translation_{$i}"};
		}

		// Check if the translation already exists.
		$existing_translations = GP::$translation->find( [
			'translation_set_id' => $new_translation_set->id,
			'original_id'        => $new_original->id,
			'status'             => [ 'current', 'waiting', 'fuzzy' ],
		] );

		foreach ( $existing_translations as $_existing_translation ) {
			$existing_translation = [];
			for ( $i = 0; $i < $locale->nplurals; $i++ ) {
				$existing_translation[] = $_existing_translation->{"translation_{$i}"};
			}

			if ( $existing_translation === $new_translation ) {
				// Translations are only synced if they have no warnings.
				// If the existing translation still has warnings discard them automatically.
				if ( $_existing_translation->warnings ) {
					$_existing_translation->warnings = null;
					$_existing_translation->save();
				}

				$_existing_translation->set_as_current();
				gp_clean_translation_set_cache( $new_translation_set->id );

				return true;
			}
		}

		// Create a new translation.
		$copy = new GP_Translation( $translation->fields() );
		$copy->original_id = $new_original->id;
		$copy->translation_set_id = $new_translation_set->id;
		$copy->status = 'current';

		$translation = GP::$translation->create( $copy );
		if ( ! $translation ) {
			return false;
		}

		$translation->set_as_current();
		gp_clean_translation_set_cache( $new_translation_set->id );

		return true;
	}

	/**
	 * Retrieves the counterpart of a plugin project.
	 *
	 * @param string $project_path The path of a plugin project.
	 * @return \GP_Project|null A project on success, null on failure.
	 */
	public function get_dev_or_stable_project( $project_path ) {
		static $project_cache;

		if ( null === $project_cache ) {
			$project_cache = [];
		}

		if ( isset( $project_cache[ $project_path ] ) ) {
			return $project_cache[ $project_path ];
		}

		$project = basename( $project_path );
		$counterpart = $this->project_mapping[ $project ];
		$new_project_path = preg_replace( "#/{$project}$#", "/$counterpart", $project_path, 1 );

		$project = GP::$project->by_path( $new_project_path );
		$project_cache[ $project_path ] = $project;

		return $project;
	}

	/**
	 * Sends a notifcation to the Slack channel.
	 *
	 * @param array $attachment The attachment of a notification.
	 */
	private function slack( $attachment ) {
		if ( ! defined( 'GLOTPRESS_SLACK_WEBHOOK' ) ) {
			return;
		}

		require_once API_WPORGPATH . 'includes/slack-config.php';
		$send = new \Dotorg\Slack\Send( GLOTPRESS_SLACK_WEBHOOK );
		$send->add_attachment( $attachment );
		$send->set_username( 'Plugin Translation Sync' );
		$send->set_icon( ':repeat:' );
		$send->send( '#meta-language-packs' );
	}
}
