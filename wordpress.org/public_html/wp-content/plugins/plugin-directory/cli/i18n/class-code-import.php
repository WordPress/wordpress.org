<?php
namespace WordPressdotorg\Plugin_Directory\CLI\I18N;

use Exception;
use WordPressdotorg\Plugin_Directory\Readme\Parser;
use WordPressdotorg\Plugin_Directory\Tools\Filesystem;
use WordPressdotorg\Plugin_Directory\Tools\SVN;
use WP_Error;

/**
 * Class to handle plugin code imports GlotPress.
 *
 * @package WordPressdotorg\Plugin_Directory\CLI\I18N
 */
class Code_Import extends I18n_Import {

	/**
	 * Imports the readme of a specific tag to GlotPress.
	 *
	 * @param string $tag SVN tag of the readme.
	 *
	 * @throws Exception
	 */
	public function import_from_tag( $tag ) {
		$svn_url = $this->get_plugin_svn_url( $tag );

		$files = SVN::ls( $svn_url );
		if ( ! $files ) {
			throw new Exception( "Plugin has no files in {$tag}." );
		}

		$tmp_directory = Filesystem::temp_directory( $this->plugin . '-code-' . $tag );
		$export_directory = $tmp_directory . '/export';

		$res = SVN::export( $svn_url, $export_directory, [ 'ignore-externals' ] );
		if ( ! $res['result'] ) {
			throw new Exception( 'Plugin export failed.' );
		}

		$valid = $this->is_plugin_valid( $export_directory );
		if ( is_wp_error( $valid ) ) {
			throw new Exception( 'Plugin is not compatible with language packs: ' . $valid->get_error_message() );
		}

		if ( ! class_exists( '\PotExtMeta' ) ) {
			require_once plugin_dir_path( \WordPressdotorg\Plugin_Directory\PLUGIN_FILE ) . 'libs/i18n-tools/makepot.php';
		}

		$pot_file = "{$tmp_directory}/{$this->plugin}-code.pot";
		$makepot  = new \MakePOT;

		if ( ! $makepot->wp_plugin( $export_directory, $pot_file, $this->plugin ) || ! file_exists( $pot_file ) ) {
			throw new Exception( "POT file couldn't be created." );
		}

		$result = $this->set_glotpress_for_plugin( $this->plugin, 'code' );
		if ( is_wp_error( $result ) ) {
			throw new Exception( $result->get_error_message() );
		}

		$branch = ( 'trunk' === $tag ) ? 'dev' : 'stable';
		$this->import_pot_to_glotpress_project( $this->plugin, $branch, $pot_file );

		// Import translations on initial import.
		if ( 'created' === $result ) {
			$this->import_translations_to_glotpress_project( $export_directory, $this->plugin, $branch );
		}
	}

	/**
	 * Checks if a plugin has the correct text domain declarations.
	 *
	 * @todo This can be removed/changed if WordPress 4.6 is released.
	 *
	 * @param string $export_directory
	 * @return true|\WP_Error True if valid, WP_Error in case of failures.
	 */
	private function is_plugin_valid( $export_directory ) {
		$readme = new Parser( $this->find_readme_file( $export_directory ) );

		// Whether plugin files should be checked for valid text domains.
		if ( empty( $readme->requires ) || version_compare( $readme->requires, '4.6', '<' ) ) {
			$error  = new WP_Error();
			$esc_export_directory = escapeshellarg( $export_directory );

			// Check for a plugin text domain declaration and loading, grep recursively, not necessarily in plugin.php.
			$has_textdomain_header      = shell_exec( 'grep -r -i --include "*.php" "Text Domain:" ' . $esc_export_directory );
			$has_load_plugin_textdomain = shell_exec( 'grep -r --include "*.php" "\bload_plugin_textdomain\b" ' . $esc_export_directory );

			if ( $has_textdomain_header ) {
				$has_slug_as_textdomain_header = shell_exec( 'grep -r -i --include "*.php" "Text Domain:[[:blank:]]*' . trim( escapeshellarg( $this->plugin ), '\'' ) . '" ' . $esc_export_directory );

				if ( ! $has_slug_as_textdomain_header ) {
					$error->add( 'wrong_textdomain', 'Wrong text domain in header.' );
				}
			} else {
				$error->add( 'missing_textdomain', 'Missing text domain in header.' );
			}

			if ( ! $has_load_plugin_textdomain ) {
				$error->add( 'missing_load_plugin_textdomain', 'Missing load_plugin_textdomain().' );
			}

			if ( $error->get_error_codes() ) {
				return $error;
			}
		}

		return true;
	}

	/**
	 * Find the plugin readme file.
	 *
	 * Looks for either a readme.txt or readme.md file, prioritizing readme.txt.
	 *
	 * @param string $directory
	 * @return string The plugin readme.txt or readme.md filename.
	 */
	private function find_readme_file( $directory ) {
		$files = Filesystem::list_files( $directory, false /* non-recursive */, '!^readme\.(txt|md)$!i' );

		// Prioritize readme.txt
		foreach ( $files as $file ) {
			if ( '.txt' === strtolower( substr( $file, -4 ) ) ) {
				return $file;
			}
		}

		return reset( $files );
	}
}
