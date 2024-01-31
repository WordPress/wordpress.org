<?php

namespace WordPressdotorg\GlotPress\Customizations\CLI;

use GP;
use GP_Locales;
use stdClass;
use WP_CLI;
use WP_CLI\Utils;
use WP_CLI_Command;
use WP_Error;

class Language_Pack extends WP_CLI_Command {

	const TMP_DIR = '/tmp/language-packs';
	const BUILD_DIR = '/nfs/rosetta/builds';
	const SVN_URL = 'https://i18n.svn.wordpress.org';
	const PACKAGE_THRESHOLD = 90;

	/**
	 * Whether a language pack should be enforced.
	 *
	 * @var bool
	 */
	private $force = false;

	/**
	 * Generates a language pack.
	 *
	 * ## OPTIONS
	 *
	 * <type>
	 * : Type of the language pack. 'plugin' or 'theme'.
	 *
	 * <slug>
	 * : Slug of the theme or plugin.
	 *
	 * [--locale]
	 * : Locale the language pack is for.
	 *
	 * [--locale-slug]
	 * : Slug of the locale. Default: 'default'.
	 *
	 * [--version]
	 * : Current version of the theme or plugin.
	 *
	 * [--force]
	 * : Generate language pack even when threshold is not reached or no updates exist.
	 */
	public function generate( $args, $assoc_args ) {
		$type = $args[0];
		$slug = $args[1];

		$this->force = Utils\get_flag_value( $assoc_args, 'force' );

		$args = wp_parse_args( $assoc_args, [
			'locale'      => false,
			'locale-slug' => 'default',
			'version'     => false,
		] );

		switch ( $type ) {
			case 'plugin' :
				$this->generate_plugin( $slug, $args );
				break;
			case 'theme' :
				$this->generate_theme( $slug, $args );
				break;
			default :
				WP_CLI::error( 'Invalid type.' );
		}
	}

	/**
	 * Generates a language pack for a plugin.
	 *
	 * Examples:
	 *   wp @translate wporg-translate language-pack generate plugin nothing-much
	 *   wp @translate wporg-translate language-pack generate plugin nothing-much --locale=de
	 *
	 * @param string $slug Slug of the plugin.
	 * @param array  $args Extra arguments.
	 */
	private function generate_plugin( $slug, $args ) {
		$gp_project = GP::$project->by_path( "wp-plugins/$slug" );
		if ( ! $gp_project ) {
			WP_CLI::error( 'Invalid plugin slug.' );
		}

		$stable_tag = $this->get_plugin_stable_tag( $slug );
		$branch = ( 'trunk' !== $stable_tag ) ? 'stable' : 'dev';

		$gp_project = GP::$project->by_path( "wp-plugins/$slug/$branch" );
		if ( ! $gp_project ) {
			WP_CLI::error( 'Invalid plugin branch.' );
		}

		$translation_sets = GP::$translation_set->by_project_id( $gp_project->id );
		if ( ! $translation_sets ) {
			WP_CLI::error( 'No translation sets available.' );
		}

		/**
		 * Filters the arguments passed to the WP-CLI command.
		 *
		 * @param array  $args CLI arguments.
		 * @param string $slug Slug of the theme.
		 */
		$args = apply_filters( 'wporg_translate_language_pack_plugin_args', $args, $slug );

		if ( $args['locale'] ) {
			$translation_sets = wp_list_filter( $translation_sets, [
				'locale' => $args['locale'],
				'slug'   => $args['locale-slug'],
			] );
		}

		if ( ! $translation_sets ) {
			WP_CLI::error( 'No translation sets available.' );
		}

		$version = $args['version'];
		if ( ! $version ) {
			$version = $this->get_latest_plugin_version( $slug );
		}

		if ( ! $version ) {
			WP_CLI::error( 'No version available.' );
		}

		$svn_command  = $this->get_svn_command();
		$svn_checkout = self::get_temp_directory( $slug );

		$result = $this->execute_command( sprintf(
			'%s checkout --quiet --depth=empty %s %s 2>&1',
			$svn_command,
			escapeshellarg( self::SVN_URL . '/plugins' ),
			escapeshellarg( $svn_checkout )
		) );

		if ( is_wp_error( $result ) ) {
			WP_CLI::error_multi_line( $result->get_error_data() );
			WP_CLI::error( 'SVN export failed.' );
		}

		$data                   = new stdClass();
		$data->type             = 'plugin';
		$data->domain           = $slug;
		$data->version          = $version;
		$data->translation_sets = $translation_sets;
		$data->gp_project       = $gp_project;
		$data->svn_checkout     = $svn_checkout;
		$this->build_language_packs( $data );
	}

	/**
	 * Generates a language pack for a theme.
	 *
	 * Examples:
	 *   wp @translate wporg-translate language-pack generate theme twentyeleven
	 *   wp @translate wporg-translate language-pack generate theme twentyeleven --locale=de
	 *
	 * @param string $slug Slug of the theme.
	 * @param array  $args Extra arguments.
	 */
	private function generate_theme( $slug, $args ) {
		$gp_project = GP::$project->by_path( "wp-themes/$slug" );
		if ( ! $gp_project ) {
			WP_CLI::error( 'Invalid theme slug.' );
		}

		$translation_sets = GP::$translation_set->by_project_id( $gp_project->id );
		if ( ! $translation_sets ) {
			WP_CLI::error( 'No translation sets available.' );
		}

		/**
		 * Filters the arguments passed to the WP-CLI command.
		 *
		 * @param array  $args CLI arguments.
		 * @param string $slug Slug of the theme.
		 */
		$args = apply_filters( 'wporg_translate_language_pack_theme_args', $args, $slug );

		if ( $args['locale'] ) {
			$translation_sets = wp_list_filter( $translation_sets, [
				'locale' => $args['locale'],
				'slug'   => $args['locale-slug'],
			] );
		}

		if ( ! $translation_sets ) {
			WP_CLI::error( 'No translation sets available.' );
		}

		$version = $args['version'];
		if ( ! $version ) {
			$version = $this->get_latest_theme_version( $slug );
		}

		if ( ! $version ) {
			WP_CLI::error( 'No version available.' );
		}

		$svn_command  = $this->get_svn_command();
		$svn_checkout = self::get_temp_directory( $slug );

		$result = $this->execute_command( sprintf(
			'%s checkout --quiet --depth=empty %s %s 2>&1',
			$svn_command,
			escapeshellarg( self::SVN_URL . '/themes' ),
			escapeshellarg( $svn_checkout )
		) );

		if ( is_wp_error( $result ) ) {
			WP_CLI::error_multi_line( $result->get_error_data() );
			WP_CLI::error( 'SVN export failed.' );
		}

		$data                   = new stdClass();
		$data->type             = 'theme';
		$data->domain           = $slug;
		$data->version          = $version;
		$data->translation_sets = $translation_sets;
		$data->gp_project       = $gp_project;
		$data->svn_checkout     = $svn_checkout;
		$this->build_language_packs( $data );
	}

	/**
	 * Creates a unique temporary directory.
	 *
	 * The temporary directory returned will be removed upon script termination.
	 *
	 * @param string $prefix Optional. The prefix for the directory, 'hello-dolly' for example.
	 * @return string The temporary directory.
	 */
	public static function get_temp_directory( $prefix = '' ) {
		if ( ! is_dir( self::TMP_DIR ) ) {
			mkdir( self::TMP_DIR, 0777 );
		}

		// Generate a unique filename
		$tmp_dir = tempnam( self::TMP_DIR, $prefix );

		// Replace that filename with a directory
		unlink( $tmp_dir );
		mkdir( $tmp_dir, 0777 );

		// Automatically remove temporary directories on shutdown.
		register_shutdown_function( [ __CLASS__, 'remove_temp_directory' ], $tmp_dir );

		return $tmp_dir;
	}

	/**
	 * Removes a directory.
	 *
	 * @param string $dir The directory which should be removed.
	 * @return bool False if directory is removed, false otherwise.
	 */
	public static function remove_temp_directory( $dir ) {
		if ( trim( $dir, '/' ) ) {
			exec( 'rm -rf ' . escapeshellarg( $dir ) );
		}

		return is_dir( $dir );
	}

	/**
	 * Retrieves the stable tag of a plugin.
	 *
	 * @param string $plugin_slug Slug of a plugin.
	 * @return false|string False on failure, stable tag on success.
	 */
	private function get_plugin_stable_tag( $plugin_slug ) {
		$plugin = @file_get_contents( "https://api.wordpress.org/plugins/info/1.0/{$plugin_slug}.json?fields=stable_tag" );
		if ( ! $plugin ) {
			return false;
		}

		$plugin = json_decode( $plugin );

		return $plugin->stable_tag;
	}

	/**
	 * Retrieves the current stable version of a theme.
	 *
	 * @param string $theme_slug Slug of a theme.
	 * @return false|string False on failure, version on success.
	 */
	private function get_latest_theme_version( $theme_slug ) {
		$theme = @file_get_contents( "https://api.wordpress.org/themes/info/1.1/?action=theme_information&request[slug]={$theme_slug}" );
		if ( ! $theme ) {
			return false;
		}

		$theme = json_decode( $theme );

		return $theme->version;
	}

	/**
	 * Retrieves the current stable version of a plugin.
	 *
	 * @param string $plugin_slug Slug of a plugin.
	 * @return false|string False on failure, version on success.
	 */
	private function get_latest_plugin_version( $plugin_slug ) {
		$plugin = @file_get_contents( "https://api.wordpress.org/plugins/info/1.0/{$plugin_slug}.json" );
		if ( ! $plugin ) {
			return false;
		}

		$plugin = json_decode( $plugin );

		return $plugin->version;
	}

	/**
	 * Retrieves active language packs for a theme/plugin of a version.
	 *
	 * @param string $type    Type of the language pack. Supports 'plugin' or 'theme'.
	 * @param string $domain  Slug of a theme/plugin.
	 * @param string $version Version of a theme/plugin.
	 * @return array Array of active language packs.
	 */
	private function get_active_language_packs( $type, $domain, $version ) {
		global $wpdb;

		$active_language_packs = $wpdb->get_results( $wpdb->prepare(
			'SELECT language, updated FROM language_packs WHERE type = %s AND domain = %s AND version = %s AND active = 1',
			$type,
			$domain,
			$version
		), OBJECT_K );

		if ( ! $active_language_packs ) {
			return [];
		}

		return $active_language_packs;
	}

	/**
	 * Whether there's at least one active language pack for a theme/plugin of any version.
	 *
	 * @param string $type   Type of the language pack. Supports 'plugin' or 'theme'.
	 * @param string $domain Slug of a theme/plugin.
	 * @param string $locale WordPress locale.
	 * @return boolean True if theme/plugin has an active language pack, false if not.
	 */
	private function has_active_language_pack( $type, $domain, $locale ) {
		global $wpdb;

		return (bool) $wpdb->get_var( $wpdb->prepare(
			'SELECT updated FROM language_packs WHERE type = %s AND domain = %s AND language = %s AND active = 1 LIMIT 1',
			$type,
			$domain,
			$locale
		) );
	}

	/**
	 * Retrieves the basic SVN command with authentication data.
	 *
	 * @return string The SVN command.
	 */
	private function get_svn_command() {
		return sprintf(
			'svn --no-auth-cache --non-interactive --username=%s --password=%s',
			escapeshellarg( POT_SVN_USER ),
			escapeshellarg( POT_SVN_PASS )
		);
	}

	/**
	 * Updates a SVN directory.
	 *
	 * Creates directories if they don't exist yet.
	 *
	 * @param string $svn_directory The SVN directory.
	 */
	private function update_svn_directory( $svn_directory ) {
		$svn_command = $this->get_svn_command();

		// Update existing files.
		$this->execute_command( sprintf(
			'%s up --quiet --parents %s 2>/dev/null',
			$svn_command,
			escapeshellarg( $svn_directory )
		) );

		// Create missing SVN directories.
		$this->execute_command( sprintf(
			'%s mkdir --quiet --parents %s 2>/dev/null',
			$svn_command,
			escapeshellarg( $svn_directory )
		) );
	}

	/**
	 * Build a mapping of JS files to translation entries occurring in those files.
	 * Translation entries occurring in other files are added to the 'po' key.
	 *
	 * @param Translation_Entry[] $entries The translation entries to map.
	 * @return array The mapping of sources to translation entries.
	 */
	private function build_mapping( $entries ) {
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
						return 'po';
					},
					$entry->references
				);

				$sources = array_unique( $sources );
			} else {
				$sources = [ 'po' ];
			}

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

	/**
	 * Builds a PO file for translations.
	 *
	 * @param GP_Project          $gp_project The GlotPress project.
	 * @param GP_Locale           $gp_locale  The GlotPress locale.
	 * @param GP_Translation_Set  $set        The translation set.
	 * @param Translation_Entry[] $entries    The translation entries.
	 * @param string              $dest       Destination file name.
	 * @return string|WP_Error Last updated date on success, WP_Error on failure.
	 */
	private function build_po_file( $gp_project, $gp_locale, $set, $entries, $dest ) {
		$format     = gp_array_get( GP::$formats, 'po' );
		$po_content = $format->print_exported_file( $gp_project, $gp_locale, $set, $entries );

		// Get last updated.
		preg_match( '/^"PO-Revision-Date: (.*)\+\d+\\\n/m', $po_content, $match );
		if ( empty( $match[1] ) ) {
			return new WP_Error( 'invalid_format', 'Date could not be parsed.' );
		}

		file_put_contents( $dest, $po_content );

		return $match[1];
	}

	/**
	 * Builds a PHP file for translations.
	 *
	 * @param GP_Project          $gp_project The GlotPress project.
	 * @param GP_Locale           $gp_locale  The GlotPress locale.
	 * @param GP_Translation_Set  $set        The translation set.
	 * @param Translation_Entry[] $entries    The translation entries.
	 * @param string              $dest       Destination file name.
	 * @return bool True on success, false on error.
	 */
	private function build_php_file( $gp_project, $gp_locale, $set, $entries, $dest ) {
		$format  = gp_array_get( GP::$formats, 'php' );
		$content = $format->print_exported_file( $gp_project, $gp_locale, $set, $entries );
		return false !== file_put_contents( $dest, $content );
	}

	/**
	 * Executes a command via exec().
	 *
	 * @param string $command The escaped command to execute.
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	private function execute_command( $command ) {
		exec( $command, $output, $return_var );

		if ( $return_var ) {
			return new WP_Error( $return_var, 'Error while executing the command.', $output );
		}

		return true;
	}

	/**
	 * Inserts a language pack into database.
	 *
	 * @param string $type     Type of the language pack.
	 * @param string $domain   Slug of the theme/plugin.
	 * @param string $language Language the language pack is for.
	 * @param string $version  Version of the theme/plugin.
	 * @param string $updated  Last updated.
	 * @return true|WP_Error true when language pack was updated, WP_Error on failure.
	 */
	private function insert_language_pack( $type, $domain, $language, $version, $updated ) {
		global $wpdb;

		$existing = $wpdb->get_var( $wpdb->prepare(
			'SELECT id FROM language_packs WHERE type = %s AND domain = %s AND language = %s AND version = %s AND updated = %s AND active = 1',
			$type,
			$domain,
			$language,
			$version,
			$updated
		) );

		if ( $existing ) {
			return true;
		}

		$now = current_time( 'mysql', 1 );
		$inserted = $wpdb->insert( 'language_packs', [
			'type'          => $type,
			'domain'        => $domain,
			'language'      => $language,
			'version'       => $version,
			'updated'       => $updated,
			'active'        => 1,
			'date_added'    => $now,
			'date_modified' => $now,
		] );

		if ( ! $inserted ) {
			return new WP_Error( 'language_pack_not_inserted', 'The language pack was not inserted.' );
		}

		// Mark old language packs for the same version as inactive.
		$wpdb->query( $wpdb->prepare(
			'UPDATE language_packs SET active = 0, date_modified = %s WHERE type = %s AND domain = %s AND language = %s AND version = %s AND id <> %d',
			$now,
			$type,
			$domain,
			$language,
			$version,
			$wpdb->insert_id
		) );

		// Clear the API update-check translation caches. See api.wordpress.org/translations/lib.php
		wp_cache_add_global_groups( [ 'update-check-translations', 'translations-query' ] );
		wp_cache_delete( "{$type}:{$language}:{$domain}", 'update-check-translations' );
		wp_cache_delete( "{$type}:{$domain}:{$version}", 'translations-query' );
		wp_cache_delete( "{$type}:{$domain}", 'translations-query' );

		return true;
	}

	/**
	 * Builds a language pack.
	 *
	 * @param object $data The data of a language pack.
	 */
	private function build_language_packs( $data ) {
		$existing_packs = $this->get_active_language_packs( $data->type, $data->domain, $data->version );
		$svn_command    = $this->get_svn_command();

		foreach ( $data->translation_sets as $set ) {
			// Get WP locale.
			$gp_locale = GP_Locales::by_slug( $set->locale );
			if ( ! isset( $gp_locale->wp_locale ) ) {
				continue;
			}

			// Change wp_locale until GlotPress returns the correct wp_locale for variants.
			// https://meta.trac.wordpress.org/changeset/12176/
			$wp_locale = $gp_locale->wp_locale;
			if ( 'default' !== $set->slug && ! str_contains( $wp_locale, $set->slug ) ) {
				$wp_locale = $wp_locale . '_' . $set->slug;
			}

			// Check if any current translations exist.
			if ( 0 === $set->current_count() ) {
				WP_CLI::log( "Skip {$wp_locale}, no translations." );
				continue;
			}

			// Check if percent translated is above threshold for initial language pack.
			$has_existing_pack = $this->has_active_language_pack( $data->type, $data->domain, $wp_locale );
			if ( ! $has_existing_pack ) {
				$percent_translated = $set->percent_translated();
				if ( ! $this->force && $percent_translated < self::PACKAGE_THRESHOLD ) {
					WP_CLI::log( "Skip {$wp_locale}, translations below threshold ({$percent_translated}%)." );
					continue;
				}
			}

			// Check if new translations are available since last build.
			if ( ! $this->force && isset( $existing_packs[ $wp_locale ] ) ) {
				$pack_time = strtotime( $existing_packs[ $wp_locale ]->updated );
				$glotpress_time = strtotime( $set->last_modified() );

				if ( $pack_time >= $glotpress_time ) {
					WP_CLI::log( "Skip {$wp_locale}, no new translations." );
					continue;
				}
			}

			$entries = GP::$translation->for_export( $data->gp_project, $set, [ 'status' => 'current' ] );
			if ( ! $entries ) {
				WP_CLI::warning( "No current translations available for {$wp_locale}." );
				continue;
			}

			$working_directory = "{$data->svn_checkout}/{$data->domain}";
			$export_directory  = "{$working_directory}/{$data->version}/{$wp_locale}";
			$build_directory   = self::BUILD_DIR . "/{$data->type}s/{$data->domain}/{$data->version}";

			$filename       = "{$data->domain}-{$wp_locale}";
			$json_file_base = "{$export_directory}/{$filename}";
			$po_file        = "{$export_directory}/{$filename}.po";
			$mo_file        = "{$export_directory}/{$filename}.mo";
			$php_file       = "{$export_directory}/{$filename}.l10n.php";
			$zip_file       = "{$export_directory}/{$filename}.zip";
			$build_zip_file = "{$build_directory}/{$wp_locale}.zip";
			$build_sig_file = "{$build_zip_file}.sig";

			// Update/create directories.
			$this->update_svn_directory( $export_directory );

			// Build a mapping based on where the translation entries occur and separate the po entries.
			$mapping    = $this->build_mapping( $entries );
			$po_entries = array_key_exists( 'po', $mapping ) ? $mapping['po'] : [];

			unset( $mapping['po'] );

			// Create JED json files for each JS file.
			$additional_files = $this->build_json_files( $data->gp_project, $gp_locale, $set, $mapping, $json_file_base );

			// Create PHP file.
			$php_file_written = $this->build_php_file( $data->gp_project, $gp_locale, $set, $po_entries, $php_file );
			if ( $php_file_written ) {
				$additional_files[] = $php_file;
			}

			// Create PO file.
			$last_modified = $this->build_po_file( $data->gp_project, $gp_locale, $set, $po_entries, $po_file );

			if ( is_wp_error( $last_modified ) ) {
				WP_CLI::warning( sprintf( "PO generation for {$wp_locale} failed: %s", $last_modified->get_error_message() ) );

				// Clean up.
				$this->execute_command( sprintf( 'rm -rf %s', escapeshellarg( $working_directory ) ) );

				continue;
			}

			// Create MO file.
			$result = $this->execute_command( sprintf(
				'msgfmt %s -o %s 2>&1',
				escapeshellarg( $po_file ),
				escapeshellarg( $mo_file )
			) );

			if ( is_wp_error( $result ) ) {
				WP_CLI::error_multi_line( $result->get_error_data() );
				WP_CLI::warning( "MO generation for {$wp_locale} failed." );

				// Clean up.
				$this->execute_command( sprintf( 'rm -rf %s', escapeshellarg( $working_directory ) ) );

				continue;
			}

			// Create ZIP file.
			$result = $this->execute_command( sprintf(
				'zip -9 -j %s %s %s %s 2>&1',
				escapeshellarg( $zip_file ),
				escapeshellarg( $po_file ),
				escapeshellarg( $mo_file ),
				implode( ' ', array_map( 'escapeshellarg', $additional_files ) )
			) );

			if ( is_wp_error( $result ) ) {
				WP_CLI::error_multi_line( $result->get_error_data() );
				WP_CLI::warning( "ZIP generation for {$wp_locale} failed." );

				// Clean up.
				$this->execute_command( sprintf( 'rm -rf %s', escapeshellarg( $working_directory ) ) );

				continue;
			}

			// Create build directories.
			$result = $this->execute_command( sprintf(
				'mkdir -p %s 2>&1',
				escapeshellarg( $build_directory )
			) );

			if ( is_wp_error( $result ) ) {
				WP_CLI::error_multi_line( $result->get_error_data() );
				WP_CLI::warning( "Creating build directories for {$wp_locale} failed." );

				// Clean up.
				$this->execute_command( sprintf( 'rm -rf %s', escapeshellarg( $working_directory ) ) );

				continue;
			}

			// Move ZIP file to build directory.
			$result = $this->execute_command( sprintf(
				'mv %s %s 2>&1',
				escapeshellarg( $zip_file ),
				escapeshellarg( $build_zip_file )
			) );

			if ( is_wp_error( $result ) ) {
				WP_CLI::error_multi_line( $result->get_error_data() );
				WP_CLI::warning( "Moving ZIP file for {$wp_locale} failed." );

				// Clean up.
				$this->execute_command( sprintf( 'rm -rf %s', escapeshellarg( $working_directory ) ) );

				continue;
			}

			// Generate a signature for the ZIP file.
			if ( false && function_exists( 'wporg_sign_file' ) ) {
				$signatures = wporg_sign_file( $build_zip_file, 'translation' );
				if ( $signatures ) {
					file_put_contents( $build_sig_file, implode( "\n", $signatures ) );
				}
			}

			// Insert language pack into database.
			$result = $this->insert_language_pack( $data->type, $data->domain, $wp_locale, $data->version, $last_modified );

			if ( is_wp_error( $result ) ) {
				WP_CLI::warning( sprintf( "Language pack for {$wp_locale} failed: %s", $result->get_error_message() ) );

				// Clean up.
				$this->execute_command( sprintf( 'rm -rf %s', escapeshellarg( $working_directory ) ) );

				continue;
			}

			// Add PO file to SVN.
			$this->execute_command( sprintf(
				'%s add --quiet --force --parents %s 2>/dev/null',
				$svn_command,
				escapeshellarg( $po_file )
			) );

			// Commit PO file.
			$result = $this->execute_command( sprintf(
				'%s commit --quiet %s -m %s 2>&1',
				$svn_command,
				escapeshellarg( $data->svn_checkout ),
				escapeshellarg( "Update PO file for {$data->type} {$data->domain} {$data->version} {$wp_locale}." )
			) );

			if ( is_wp_error( $result ) ) {
				WP_CLI::error_multi_line( $result->get_error_data() );
				WP_CLI::warning( "SVN commit for {$wp_locale} failed." );

				// Clean up.
				$this->execute_command( sprintf( 'rm -rf %s', escapeshellarg( $working_directory ) ) );

				continue;
			}

			// Clean up.
			$this->execute_command( sprintf( 'rm -rf %s', escapeshellarg( $working_directory ) ) );

			WP_CLI::success( "Language pack for {$wp_locale} generated." );
		}
	}
}
