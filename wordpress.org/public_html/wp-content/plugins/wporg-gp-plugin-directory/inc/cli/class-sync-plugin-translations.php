<?php

namespace WordPressdotorg\GlotPress\Plugin_Directory\CLI;

use GP;
use GP_Locales;
use Translation_Entry;
use Translations;
use WordPressdotorg\GlotPress\Plugin_Directory\Plugin as Plugin;
use WP_CLI;
use WP_CLI_Command;

class Sync_Plugin_Translations extends WP_CLI_Command {

	/**
	 * Sync plugin translations.
	 *
	 * ## OPTIONS
	 *
	 * <slug>
	 * : Slug of a plugin
	 *
	 * <locale>
	 * : Locale to export
	 *
	 * [--set=<set>]
	 * : Translation set slug; default is "default"
	 */
	public function __invoke( $args, $assoc_args ) {
		$plugin = Plugin::get_instance();
		$translation_sync = $plugin->translation_sync;

		// Avoid recursion.
		remove_action( 'gp_translation_created', array( $translation_sync, 'queue_translation_for_sync' ), 5 );
		remove_action( 'gp_translation_saved', array( $translation_sync, 'queue_translation_for_sync' ), 5 );

		$project_path = Plugin::GP_MASTER_PROJECT . '/' . $args[0];
		$project_src = GP::$project->by_path( $project_path );
		if ( ! $project_src ) {
			WP_CLI::error( 'Source project not found!' );
		}

		$project_dest = $translation_sync->get_dev_or_stable_project( $project_path );
		if ( ! $project_dest ) {
			WP_CLI::error( 'Destination project not found!' );
		}

		$locale = GP_Locales::by_slug( $args[1] );
		if ( ! $locale ) {
			WP_CLI::error( 'Locale not found!' );
		}

		$set_slug = isset( $assoc_args['set'] ) ? $assoc_args['set'] : 'default';


		$translation_set_src = GP::$translation_set->by_project_id_slug_and_locale( $project_src->id, $set_slug, $locale->slug );
		if ( ! $translation_set_src ) {
			WP_CLI::error( 'Source translation set not found!' );
		}

		$translation_set_dest = GP::$translation_set->by_project_id_slug_and_locale( $project_dest->id, $set_slug, $locale->slug );
		if ( ! $translation_set_dest ) {
			WP_CLI::error( 'Destination translation set not found!' );
		}

		$current_translations_list = GP::$translation->for_translation( $project_src, $translation_set_src, 'no-limit', array( 'status' => 'current' ) );
		if ( ! $current_translations_list ) {
			WP_CLI::log( 'No translations available.' );
			return;
		}

		$current_translations     = new Translations();
		$current_translations_map = [];
		foreach ( $current_translations_list as $translation ) {
			$current_translations->add_entry( $translation );
			$current_translations_map[ $translation->key() ] = [
				'user_id'  => $translation->user_id,
				'status'   => $translation->translation_status,
				'warnings' => $translation->warnings,
			];

		}
		unset( $current_translations_list );

		add_filter( 'gp_translation_prepare_for_save', function( $args, $translation ) use ( $current_translations_map ) {
			$original = GP::$original->get( $args['original_id'] ?? $translation->original_id );
			if ( ! $original ) {
				return $args;
			}

			$translation_entry = new Translation_Entry( [
				'singular' => $original->singular,
				'plural'   => $original->plural,
				'context'  => $original->context,
			] );
			$key = $translation_entry->key();

			if ( ! isset( $current_translations_map[ $key ] ) ) {
				return $args;
			}

			$current_translation = $current_translations_map[ $key ];

			$args['user_id']  = $current_translation['user_id'];
			$args['status']   = $current_translation['status'];
			$args['warnings'] = $current_translation['warnings'];

			return $args;
		}, 50, 2 );

		// GP_Translation_Set::import() calls `$translation->set_status()` which may reset the status set above.
		add_filter( 'gp_translation_set_import_status', function( $status, $entry ) use ( $current_translations_map ) {
			if ( ! isset( $current_translations_map[ $entry->key() ] ) ) {
				return $status;
			}

			$current_translation = $current_translations_map[ $entry->key() ];

			return $current_translation['status'];
		}, 50, 2 );

		$synced = $translation_set_dest->import( $current_translations );

		WP_CLI::line( sprintf( '%s translations were synced for %s/%s.', $synced, $locale->slug, $set_slug ) );
	}
}
