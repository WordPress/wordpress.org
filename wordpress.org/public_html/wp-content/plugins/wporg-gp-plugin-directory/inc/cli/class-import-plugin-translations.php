<?php

namespace WordPressdotorg\GlotPress\Plugin_Directory\CLI;

use GP;
use GP_Locales;
use WP_CLI;
use WP_CLI_Command;

class Import_Plugin_Translations extends WP_CLI_Command {

	/**
	 * Import plugin translations.
	 *
	 * ## OPTIONS
	 *
	 * <project>
	 * : Project path
	 *
	 * <locale>
	 * : Locale to export
	 *
	 * <file>
	 * : File to import
	 *
	 * [--format=<format>]
	 * : Accepted values: po, mo. Default: po
	 *
	 * [--set=<set>]
	 * : Translation set slug: Default: "default"
	 *
	 * [--disable-propagating]
	 * : If set, propagation will be disabled.
	 */
	public function __invoke( $args, $assoc_args ) {
		$file = basename( $args[2] );

		// Sanitize arguments.
		$project = GP::$project->by_path( $args[0] );
		if ( ! $project ) {
			WP_CLI::error( "Project not found! [$file]" );
		}

		$locale = GP_Locales::by_field( 'wp_locale', $args[1] );
		if ( ! $locale ) {
			$locale = GP_Locales::by_field( 'slug', $args[1] );
		}

		if ( ! $locale ) {
			WP_CLI::error( "Locale not found! [$file]" );
		}

		$format = gp_array_get( GP::$formats, isset( $assoc_args['format'] ) ?  $assoc_args['format'] : 'po', null );
		if ( ! $format ) {
			WP_CLI::error( "No such format! [$file]" );
		}

		$set_slug = isset( $assoc_args['set'] ) ? $assoc_args['set'] : 'default';
		$translation_set = GP::$translation_set->by_project_id_slug_and_locale( $project->id, $set_slug, $locale->slug );
		if ( ! $translation_set ) {
			WP_CLI::error( "Translation set not found! [$file]" );
		}

		// Load the translations into memory.
		$translations = $format->read_translations_from_file( $args[2], $project );
		if ( ! $translations ) {
			WP_CLI::error( "Couldn't load translations from file! [$file]" );
		}

		$disable_propagating = isset( $assoc_args['disable-propagating'] );

		if ( $disable_propagating ) {
			add_filter( 'enable_propagate_translations_across_projects', '__return_false' );
		}

		add_filter( 'translation_set_import_over_existing', '__return_false' );
		//add_filter( 'translation_set_import_status', array( $this, '__string_status_waiting' ) );

		// Do the import.
		$imported = $translation_set->import( $translations );

		//remove_filter( 'translation_set_import_status', '__string_status_waiting' );
		remove_filter( 'translation_set_import_over_existing', '__return_false' );

		if ( $disable_propagating ) {
			remove_filter( 'enable_propagate_translations_across_projects', '__return_false' );
		}

		WP_CLI::success( "Imported $imported strings for {$locale->english_name} [$file]" );
	}

	public function __string_status_waiting() {
		return 'waiting';
	}
}
