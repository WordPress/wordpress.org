<?php

namespace WordPressdotorg\GlotPress\Customizations\CLI;

use GP;
use GP_Locales;
use WP_CLI;
use WP_CLI_Command;

class Export_JSON extends WP_CLI_Command {

	/**
	 * Export plugin/themes JSON translation files for a project into a WPLANGDIR type directory.
	 *
	 * ## OPTIONS
	 *
	 * <project>
	 * : The GlotPress project to export.
	 *
	 * <locale>
	 * : Locale to export json translations for.
	 * 
	 * <type>
	 * : The type of the project. 'plugin', 'theme', or 'both'.
	 * 
	 * <textdomain>
	 * : The textdomain of the project.
	 * 
	 * <export-directory>
	 * : The directory to export the JSON files into. Must contain `plugins` and `themes` sub-folders.
	 * 
	 * [--locale-slug]
	 * : Slug of the locale. Default: 'default'.
	 * 
	 * [--strip-path-prefix]
	 * : Path prefix to strip from the file prefix. Pass 'guess' to strip all plugin/theme looking prefixes. Default: false.
	 */
	public function __invoke( $args, $assoc_args ) {
		$project_slug = $args[0];
		$locale       = $args[1];
		$type         = $args[2] === 'both' ? false : $args[2];
		$textdomain   = $args[3];
		$export_dir   = $args[4];

		$args = wp_parse_args( $assoc_args, [
			'locale-slug'       => 'default',
			'strip-path-prefix' => false,
		] );

		$gp_project = GP::$project->by_path( $project_slug );
		if ( ! $gp_project ) {
			WP_CLI::error( 'Invalid project slug.' );
		}

		if ( ! is_writable( $export_dir ) || ! is_dir( $export_dir ) ) {
			WP_CLI::error( 'Invalid export directory.' );
		}

		$translation_set = GP::$translation_set->by_project_id_slug_and_locale( $gp_project->id, $args['locale-slug'], $locale );
		if ( ! $translation_set ) {
			WP_CLI::error( 'Invalid translation set.' );
		}

		// Get WP locale.
		$gp_locale = GP_Locales::by_slug( $translation_set->locale );
		if ( ! isset( $gp_locale->wp_locale ) ) {
			WP_CLI::error( 'Invalid translation set.' );
		}

		// Check if any current translations exist.
		if ( 0 === $translation_set->current_count() ) {
			WP_CLI::log( 'No current translations available.' );
			return;
		}

		$entries = GP::$translation->for_export( $gp_project, $translation_set, [ 'status' => 'current' ] );
		if ( ! $entries ) {
			WP_CLI::warning( 'No current translations available.' );
			return;
		}

		// Build a mapping based on where the translation entries occur.
		$plugins = $this->build_mapping( $entries, 'plugins', $type );
		$themes  = $this->build_mapping( $entries, 'themes', $type );

		// Strip the path prefix if required.
		if ( $args['strip-path-prefix'] ) {
			$plugins = $this->strip_path_prefix( $plugins, $args['strip-path-prefix'] );
			$themes  = $this->strip_path_prefix( $themes, $args['strip-path-prefix'] );
		}

		// Create JED json files for each JS file.
		$plugin_json_files = $this->build_json_files( $gp_project, $gp_locale, $translation_set, $plugins, "{$export_dir}/plugins/{$textdomain}-{$gp_locale->wp_locale}" );
		$theme_json_files  = $this->build_json_files( $gp_project, $gp_locale, $translation_set, $themes, "{$export_dir}/themes/{$textdomain}-{$gp_locale->wp_locale}" );

		WP_CLI::success( "JSON Files for {$project_slug} {$locale} generated." );
	}

	/**
	 * Build a mapping of JS files to translation entries occurring in those files.
	 * Translation entries occurring in other files are skipped.
	 *
	 * @param Translation_Entry[] $entries      The translation entries to map.
	 * @param string              $type_limiter Limit to this kind of string. 'themes', 'plugins'.
	 * @param string              $type         The type of the project. false to guess.
	 * @return array The mapping of sources to translation entries.
	 */
	private function build_mapping( $entries, $type_limiter, $type ) {
		$mapping = [];

		foreach ( $entries as $entry ) {
			/** @var Translation_Entry $entry */

			// Find all unique sources this translation originates from.
			if ( ! empty( $entry->references ) ) {
				$sources = array_map(
					function ( $reference ) {
						$parts = explode( ':', $reference );
						$file  = $parts[0];

						if ( substr( $file, -7 ) === '.min.js' ) {
							return substr( $file, 0, -7 ) . '.js';
						}

						if ( substr( $file, -3 ) === '.js' ) {
							return $file;
						}

						return false;
					},
					$entry->references
				);

				$sources = array_unique( $sources );
			}

			foreach ( array_filter( $sources ) as $source ) {
				if ( $type_limiter ) {
					$filetype = $type;
					if ( $type ) {
						$filetype = $type;
					} elseif ( preg_match( '!wp-content/(themes|plugins)/!', $source, $m ) ) {
						$filetype = $m[1];
					} elseif ( preg_match( '!^/?(themes|plugins)/!', $source, $m ) ) {
						$filetype = $m[1];
					}

					if ( $filetype !== $type_limiter ) {
						continue;
					}
				}

				$mapping[ $source ][] = $entry;
			}
		}

		return $mapping;
	}

	/**
	 * Strip a leading path off the keys of the mapping array.
	 * 
	 * @param array  $mapping The mapping array returned by build_mapping.
	 * @param string $prefix  The prefix to strip, or 'magic' to strip all plugin/theme-looking paths.
	 * @return array The $mapping object with paths striped.
	 */
	private function strip_path_prefix( $mapping, $prefix ) {
		$result = [];

		foreach ( $mapping as $file => $entries ) {
			if ( 'guess' === $prefix ) {
				$file = preg_replace( '!^(.*wp-content)?/?(plugins|themes)/[^/]+/!i', '', $file );
			} else {
				if ( $prefix === substr( $file, 0, strlen( $prefix ) ) ) {
					$file = substr( $file, strlen( $prefix ) );
				}
			}

			$result[ $file ] = $entries;
		}

		return $result;
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
			// Don't create JSON files for source files.
			if ( 0 === strpos( $file, 'src/' ) || false !== strpos( $file, '/src/' ) ) {
				continue;
			}

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

}
