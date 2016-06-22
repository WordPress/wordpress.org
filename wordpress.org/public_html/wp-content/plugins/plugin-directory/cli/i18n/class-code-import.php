<?php
namespace WordPressdotorg\Plugin_Directory\CLI\I18N;

use WordPressdotorg\Plugin_Directory\Tools\SVN;
use WordPressdotorg\Plugin_Directory\Tools\Filesystem;
use Exception;
use WP_Error;

/**
 * Class to handle plugin code imports GlotPress.
 *
 * @package WordPressdotorg\Plugin_Directory\CLI\I18N
 */
class Code_Import extends I18n_Import {
	const PLUGIN_SVN_BASE = 'https://plugins.svn.wordpress.org';

	/**
	 * Slug of the plugin.
	 *
	 * @var string
	 */
	private $plugin;

	/**
	 * Constructor.
	 *
	 * @param string $plugin The plugin slug.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Imports the readme of a specific tag to GlotPress.
	 *
	 * @param string $tag SVN tag of the readme.
	 *
	 * @throws Exception
	 */
	public function import_from_tag( $tag ) {
		$plugin_url = self::PLUGIN_SVN_BASE . "/{$this->plugin}/{$tag}";

		$files = SVN::ls( $plugin_url );
		if ( ! $files ) {
			throw new Exception( "Plugin has no files in {$tag}." );
		}

		$tmp_directory = Filesystem::temp_directory( $this->plugin . '-code-' . $tag );
		$export_directory = $tmp_directory . '/export';

		$res = SVN::export( $plugin_url, $export_directory, [ 'ignore-externals' ] );
		if ( ! $res['result'] ) {
			throw new Exception( 'Plugin export failed.' );
		}

		$valid = $this->is_plugin_valid( $export_directory );
		if ( is_wp_error( $valid ) ) {
			throw new Exception( 'Plugin is not compatible with language packs.' );
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
		$error = new WP_Error();
		$esc_export_directory = escapeshellarg( $export_directory );

		// Check for a plugin text domain declaration and loading, grep recursively, not necessarily in plugin.php.
		$has_textdomain_header = shell_exec( 'grep -r -i --include "*.php" "Text Domain:" ' . $esc_export_directory );
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

		return true;
	}
}
