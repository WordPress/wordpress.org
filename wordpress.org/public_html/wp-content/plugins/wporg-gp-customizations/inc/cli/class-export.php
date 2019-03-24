<?php

namespace WordPressdotorg\GlotPress\Customizations\CLI;

use GP;
use GP_Locales;
use WP_CLI;
use WP_CLI_Command;

class Export extends WP_CLI_Command {

	/**
	 * Exports core's translations.
	 *
	 * ## OPTIONS
	 *
	 * <version>
	 * : Version to export.
	 *
	 * <locale>
	 * : Locale to export.
	 *
	 * <dest>
	 * : Path to export to.
	 *
	 * [--locale-slug]
	 * : Slug of the locale. Default: 'default'.
	 */
	public function __invoke( $args, $assoc_args ) {
		$version = $args[0];
		$locale  = $args[1];
		$dest    = realpath( $args[2] );

		$args = wp_parse_args( $assoc_args, [
			'locale-slug' => 'default',
		] );

		// Get WP locale.
		$gp_locale = GP_Locales::by_slug( $locale );
		if ( ! isset( $gp_locale->wp_locale ) ) {
			WP_CLI::error( "Locale does not exist for $locale." );
		}

		// Change wp_locale until GlotPress returns the correct wp_locale for variants.
		$wp_locale = $gp_locale->wp_locale;
		if ( 'default' !== $args['locale-slug'] ) {
			$wp_locale = $wp_locale . '_' . $args['locale-slug'];
		}

		$projects = [
			"wp/$version"               => '',
			"wp/$version/admin"         => 'admin',
			"wp/$version/admin/network" => 'admin-network',
			"wp/$version/cc"            => 'continents-cities',
		];

		$files = [];

		foreach ( $projects as $path => $context ) {
			$gp_project = GP::$project->by_path( $path );
			if ( ! $gp_project ) {
				WP_CLI::error( "Invalid project path: $path." );
			}

			$translation_set = GP::$translation_set->by_project_id_slug_and_locale( $gp_project->id, $args['locale-slug'], $locale );
			if ( ! $translation_set ) {
				WP_CLI::warning( "No translation set available for $path." );
				continue;
			}

			$entries = GP::$translation->for_export( $gp_project, $translation_set, [ 'status' => 'current' ] );
			if ( ! $entries ) {
				WP_CLI::warning( "No current translations available for {$path}/{$translation_set->locale}/{$translation_set->slug}." );
				continue;
			}

			// Build a mapping based on where the translation entries occur and separate the po entries.
			$mapping    = $this->build_mapping( $entries );
			$po_entries = array_key_exists( 'po', $mapping ) ? $mapping['po'] : array();

			unset( $mapping['po'] );

			// Create JED json files for each JS file.
			$json_file_base = "{$dest}/{$wp_locale}";
			$jed_files      = $this->build_json_files( $gp_project, $gp_locale, $translation_set, $mapping, $json_file_base );

			$files = array_merge( $files, $jed_files );

			// Create PO file.
			$po_file = "{$wp_locale}.po";
			if ( $context ) {
				$po_file = "$context-{$po_file}";
			}
			$po_file = "{$dest}/{$po_file}";
			$result  = $this->build_po_file( $gp_project, $gp_locale, $translation_set, $po_entries, $po_file );

			if ( ! $result ) {
				WP_CLI::error( "Failure while creating $po_file." );
			}

			array_push( $files, $po_file );

			// Create MO file.
			$mo_file = "{$wp_locale}.mo";
			if ( $context ) {
				$mo_file = "$context-{$mo_file}";
			}
			$mo_file = "{$dest}/{$mo_file}";
			exec( sprintf(
				'msgfmt %s -o %s 2>&1',
				escapeshellarg( $po_file ),
				escapeshellarg( $mo_file )
			), $output, $return_var );

			if ( $return_var ) {
				WP_CLI::error( "Failure while creating $mo_file." );
			}

			array_push( $files, $mo_file );
		}

		WP_CLI::success( "Created the following files:\n" . implode( "\n", $files ) );
	}

	/**
	 * Build a mapping of JS files to translation entries occurring in those files.
	 * Translation entries occurring in other files are added to the 'po' key.
	 *
	 * @param Translation_Entry[] $entries The translation entries to map.
	 *
	 * @return array The mapping of sources to translation entries.
	 */
	private function build_mapping( $entries ) {
		$mapping = array();

		foreach ( $entries as $entry ) {
			/** @var Translation_Entry $entry */

			// Find all unique sources this translation originates from.
			$sources = array_map( function ( $reference ) {
				$parts = explode( ':', $reference );
				$file  = $parts[0];

				if ( substr( $file, -7 ) === '.min.js' ) {
					return substr( $file, 0, -7 ) . '.js';
				}

				if ( substr( $file, -3 ) === '.js' ) {
					return $file;
				}
				return 'po';
			}, $entry->references );

			// Always add all entries to the PO file.
			$sources[] = 'po';

			$sources = array_unique( $sources );

			foreach ( $sources as $source ) {
				$mapping[ $source ][] = $entry;
			}
		}

		return $mapping;
	}

	/**
	 * Builds a a separate JSON file with translations for each JavaScript file.
	 *
	 * @param GP_Project          $gp_project The GlotPress project.
	 * @param GP_Locale           $gp_locale  The GlotPress locale.
	 * @param GP_Translation_Set  $set        The translation set.
	 * @param array               $mapping    A mapping of files to translation entries.
	 * @param string              $base_dest  Destination file name.
	 * @return array An array of translation files built, may be empty if no translations in JS files exist.
	 */
	private function build_json_files( $gp_project, $gp_locale, $set, $mapping, $base_dest ) {
		$files  = array();
		$format = gp_array_get( GP::$formats, 'jed1x' );

		foreach ( $mapping as $file => $entries ) {
			// Get the translations in Jed 1.x compatible JSON format.
			$json_content = $format->print_exported_file( $gp_project, $gp_locale, $set, $entries );

			// Decode and add comment with file reference for debugging.
			$json_content_decoded          = json_decode( $json_content );
			$json_content_decoded->comment = [ 'reference' => $file ];

			$json_content = wp_json_encode( $json_content_decoded );

			$hash = md5( $file );
			$dest = "{$base_dest}-{$hash}.json";

			file_put_contents( $dest, $json_content );

			$files[] = $dest;
		}

		return $files;
	}

	/**
	 * Builds a PO file for translations.
	 *
	 * @param GP_Project          $gp_project The GlotPress project.
	 * @param GP_Locale           $gp_locale  The GlotPress locale.
	 * @param GP_Translation_Set  $set        The translation set.
	 * @param Translation_Entry[] $entries    The translation entries.
	 * @param string              $dest       Destination file name.
	 * @return boolean True on success, false on failure.
	 */
	private function build_po_file( $gp_project, $gp_locale, $set, $entries, $dest ) {
		$format     = gp_array_get( GP::$formats, 'po' );
		$po_content = $format->print_exported_file( $gp_project, $gp_locale, $set, $entries );

		file_put_contents( $dest, $po_content );

		return true;
	}
}
