<?php
namespace WordPressdotorg\Plugin_Directory\CLI\I18N;

use Exception;
use WordPressdotorg\Plugin_Directory\CLI\Import;
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
		if ( ! defined( 'WPORGTRANSLATE_WPCLI' ) ) {
			throw new Exception( 'Missing configuration for WPORGTRANSLATE_WPCLI.' );
		}

		$svn_url = $this->get_plugin_svn_url( $tag );

		$files = SVN::ls( $svn_url );
		if ( ! $files ) {
			throw new Exception( "Plugin has no files in {$tag}." );
		}

		$tmp_directory    = Filesystem::temp_directory( $this->plugin . '-code-' . $tag );
		$export_directory = $tmp_directory . '/export';

		$res = SVN::export( $svn_url, $export_directory, [ 'ignore-externals' ] );
		if ( ! $res['result'] ) {
			throw new Exception( 'Plugin export failed.' );
		}

		$valid = $this->is_plugin_valid( $export_directory );
		if ( is_wp_error( $valid ) ) {
			throw new Exception( 'Plugin is not compatible with language packs: ' . $valid->get_error_message() );
		}

		$pot_file = "{$tmp_directory}/{$this->plugin}-code.pot";

		$cmd = sprintf(
			'%s i18n make-pot %s %s --slug=%s --ignore-domain --skip-audit',
			WPORGTRANSLATE_WPCLI,
			escapeshellarg( $export_directory ),
			escapeshellarg( $pot_file ),
			escapeshellarg( $this->plugin )
		);

		exec( $cmd, $output, $return_code );

		if ( 0 !== $return_code || ! file_exists( $pot_file ) ) {
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
		$readme   = new Parser( Import::find_readme_file( $export_directory ) );
		$requires = $readme->requires;

		// Prefer requires field from the plugin headers if set.
		$headers = Import::find_plugin_headers( $export_directory );
		if ( ! empty( $headers->RequiresWP ) && preg_match( '!^[\d.]{3,}$!', $headers->RequiresWP ) ) {
			$requires = $headers->RequiresWP;
		}

		// Whether plugin files should be checked for valid text domain setup.
		if ( empty( $requires ) || version_compare( $requires, '4.6', '<' ) ) {
			$error                = new WP_Error();
			$esc_export_directory = escapeshellarg( $export_directory );

			// Check for a plugin text domain declaration and loading, grep recursively, not necessarily in plugin.php.
			$has_textdomain_header      = shell_exec( 'grep -r -i --include "*.php" "Text Domain:" ' . $esc_export_directory );
			$has_load_plugin_textdomain = shell_exec( 'grep -r --include "*.php" "\bload_plugin_textdomain\b" ' . $esc_export_directory );

			if ( $has_textdomain_header ) {
				$has_slug_as_textdomain_header = shell_exec( 'grep -r -i --include "*.php" "Text Domain:[[:blank:]]*' . trim( escapeshellarg( $this->plugin ), '\'' ) . '" ' . $esc_export_directory );

				if ( ! $has_slug_as_textdomain_header ) {
					$error->add( 'wrong_textdomain', 'Requires WordPress ' . ( $requires ?: 'n/a' ) . '; wrong text domain in header, expected `' . $this->plugin . '`.' );
				}
			} else {
				$error->add( 'missing_textdomain', 'Requires WordPress ' .  ( $requires ?: 'n/a' ) . '; missing text domain in header.' );
			}

			if ( ! $has_load_plugin_textdomain ) {
				$error->add( 'missing_load_plugin_textdomain', 'Requires WordPress ' .  ( $requires ?: 'n/a' ) . '; missing load_plugin_textdomain() call.' );
			}

			if ( $error->get_error_codes() ) {
				return $error;
			}
		}

		return true;
	}
}
