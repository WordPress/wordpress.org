<?php

namespace WordPressdotorg\GlotPress\Customizations\CLI;

use GP;
use GP_Locales;
use stdClass;
use WP_CLI;
use WP_CLI_Command;
use WP_Error;

class Language_Pack extends WP_CLI_Command {

	const TMP_DIR = '/tmp/language-packs';
	const BUILD_DIR = '/nfs/rosetta/builds';
	const SVN_URL = 'https://i18n.svn.wordpress.org';
	const PACKAGE_THRESHOLD = 95;

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
	 */
	public function generate( $args, $assoc_args ) {
		$type = $args[0];
		$slug = $args[1];

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
	 *   wp --url=translate.wordpress.org wporg-translate language-pack generate plugin nothing-much
	 *   wp --url=translate.wordpress.org wporg-translate language-pack generate plugin nothing-much --locale=de
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
	 *   wp --url=translate.wordpress.org wporg-translate language-pack generate theme twentyeleven
	 *   wp --url=translate.wordpress.org wporg-translate language-pack generate theme twentyeleven --locale=de
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
	 *
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
	 *
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
	 *
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
	 *
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
	 *
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
	 * Retrieves activate language packs for a theme/plugin of a version.
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
	 * Builds a PO file for translations.
	 *
	 * @param GP_Project         $gp_project The GlotPress project.
	 * @param GP_Locale          $gp_locale  The GlotPress locale.
	 * @param GP_Translation_Set $set        The translation set.
	 * @param string             $dest       Destination file name.
	 * @return string|WP_Error Last updated date on success, WP_Error on failure.
	 */
	private function build_po_file( $gp_project, $gp_locale, $set, $dest ) {
		$entries = GP::$translation->for_export( $gp_project, $set, [ 'status' => 'current' ] );
		if ( ! $entries ) {
			return new WP_Error( 'no_translations', 'No current translations available.' );
		}

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
	 * Executes a command via exec().
	 *
	 * @param string $command The escaped command to execute.
	 *
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
	 * @return string|WP_Error 'updated' when language pack was updated, 'inserted' if it's a new
	 *                         language pack. WP_Error on failure.
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
			return new WP_Error( 'language_pack_exists', 'The language pack is already imported for this version.' );
		}

		$inserted = $wpdb->insert( 'language_packs', [
			'type'     => $type,
			'domain'   => $domain,
			'language' => $language,
			'version'  => $version,
			'updated'  => $updated,
			'active'   => 1,
		] );

		if ( ! $inserted ) {
			return new WP_Error( 'language_pack_not_inserted', 'The language pack was not inserted.' );
		}

		// Set old language packs for the same version as inactive.
		$wpdb->query( $wpdb->prepare(
			'UPDATE language_packs SET active = 0 WHERE type = %s AND domain = %s AND language = %s AND version = %s AND id <> %d',
			$type,
			$domain,
			$language,
			$version,
			$wpdb->insert_id
		) );

		if ( $wpdb->rows_affected ) {
			return 'updated';
		} else {
			return 'inserted';
		}
	}

	/**
	 * Builds a language pack.
	 *
	 * @param object $data The data of a language pack.
	 */
	private function build_language_packs( $data ) {
		$existing_packs = $this->get_active_language_packs( $data->type, $data->domain, $data->version );
		$svn_command = $this->get_svn_command();

		foreach ( $data->translation_sets as $set ) {
			// Get WP locale.
			$gp_locale = GP_Locales::by_slug( $set->locale );
			if ( ! isset( $gp_locale->wp_locale ) ) {
				continue;
			}

			// Change wp_locale until GlotPress returns the correct wp_locale for variants.
			$wp_locale = $gp_locale->wp_locale;
			if ( 'default' !== $set->slug ) {
				$wp_locale = $wp_locale . '_' . $set->slug;
			}

			// Check if percent translated is above threshold.
			$percent_translated = $set->percent_translated();
			if ( $percent_translated < self::PACKAGE_THRESHOLD ) {
				WP_CLI::log( "Skip {$wp_locale}, translations below threshold ({$percent_translated}%)." );
				continue;
			}

			// Check if new translations are available since last build.
			if ( isset( $existing_packs[ $wp_locale ] ) ) {
				$pack_time = strtotime( $existing_packs[ $wp_locale ]->updated );
				$glotpress_time = strtotime( $set->last_modified() );

				if ( $pack_time >= $glotpress_time ) {
					WP_CLI::log( "Skip {$wp_locale}, no new translations." );
					continue;
				}
			}

			$export_directory = "{$data->svn_checkout}/{$data->domain}/{$data->version}/{$wp_locale}";
			$build_directory  = self::BUILD_DIR . "/{$data->type}s/{$data->domain}/{$data->version}";
			$filename         = "{$data->domain}-{$wp_locale}";
			$po_file          = "{$export_directory}/{$filename}.po";
			$mo_file          = "{$export_directory}/{$filename}.mo";
			$zip_file         = "{$export_directory}/{$filename}.zip";
			$build_zip_file   = "{$build_directory}/{$wp_locale}.zip";

			// Update/create directories.
			$this->update_svn_directory( $export_directory );

			// Create PO file.
			$last_modified = $this->build_po_file( $data->gp_project, $gp_locale, $set, $po_file );

			if ( is_wp_error( $last_modified ) ) {
				WP_CLI::warning( sprintf( "PO generation for {$wp_locale} failed: %s", $last_modified->get_error_message() ) );

				// Clean up.
				$this->execute_command( "rm -rf {$data->svn_checkout}/{$data->domain}" );

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
				$this->execute_command( "rm -rf {$data->svn_checkout}/{$data->domain}" );

				continue;
			}

			// Create ZIP file.
			$result = $this->execute_command( sprintf(
				'zip -9 -j %s %s %s 2>&1',
				escapeshellarg( $zip_file ),
				escapeshellarg( $po_file ),
				escapeshellarg( $mo_file )
			) );

			if ( is_wp_error( $result ) ) {
				WP_CLI::error_multi_line( $result->get_error_data() );
				WP_CLI::warning( "ZIP generation for {$wp_locale} failed." );

				// Clean up.
				$this->execute_command( "rm -rf {$data->svn_checkout}/{$data->domain}" );

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
				$this->execute_command( "rm -rf {$data->svn_checkout}/{$data->domain}" );

				continue;
			}

			// Insert language pack into database.
			$result = $this->insert_language_pack( $data->type, $data->domain, $wp_locale, $data->version, $last_modified );

			if ( is_wp_error( $result ) ) {
				WP_CLI::warning( sprintf( "Language pack for {$wp_locale} failed: %s", $result->get_error_message() ) );

				// Clean up.
				$this->execute_command( "rm -rf {$data->svn_checkout}/{$data->domain}" );

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
				$this->execute_command( "rm -rf {$data->svn_checkout}/{$data->domain}" );

				continue;
			}

			// Clean up.
			$this->execute_command( "rm -rf {$data->svn_checkout}/{$data->domain}" );

			WP_CLI::success( "Language pack for {$wp_locale} generated." );
		}
	}
}
