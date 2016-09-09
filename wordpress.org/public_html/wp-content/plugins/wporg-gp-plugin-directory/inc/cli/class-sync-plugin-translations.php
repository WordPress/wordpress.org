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
	 * Delete a plugin project and its translations.
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
			WP_CLI::error( 'Source translation set not found!' );
		}

		$current_translations_list = GP::$translation->for_translation( $project_src, $translation_set_src, 'no-limit', array( 'status' => 'current' ) );
		if ( ! $current_translations_list ) {
			WP_CLI::log( 'No translations available.' );
			return;
		}

		$current_translations = new Translations();
		$user_id_map = [];
		foreach ( $current_translations_list as $translation ) {
			$current_translations->add_entry( $translation );
			$user_id_map[ $translation->key() ] = $translation->user_id;
		}
		unset( $current_translations_list );

		add_action( 'gp_translation_created', function( $translation ) use ( $user_id_map ) {
			$original = GP::$original->get( $translation->original_id );
			if ( ! $original ) {
				return;
			}

			$translation_entry = new Translation_Entry( [
				'singular' => $original->singular,
				'plural'   => $original->plural,
				'context'  => $original->context,
			] );
			$key = $translation_entry->key();
			if ( isset( $user_id_map[ $key ] ) && $user_id_map[ $key ] !== $translation->user_id ) {
				$translation->update( [ 'user_id' => $user_id_map[ $key ] ] );
			}
		} );

		$synced = $translation_set_dest->import( $current_translations );

		WP_CLI::line( sprintf( '%s translations were synced for %s/%s.', $synced, $locale->slug, $set_slug ) );
	}
}
