<?php
namespace WordPressdotorg\Plugin_Directory\CLI\I18N;

use WordPressdotorg\Plugin_Directory\Tools\SVN;
use WordPressdotorg\Plugin_Directory\Readme\Parser;
use WordPressdotorg\Plugin_Directory\Tools\Filesystem;
use Exception;

/**
 * Class to handle plugin readme imports GlotPress.
 *
 * @package WordPressdotorg\Plugin_Directory\CLI\I18N
 */
class Readme_Import extends I18n_Import {
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
		$files = SVN::ls( self::PLUGIN_SVN_BASE . "/{$this->plugin}/{$tag}" );
		if ( ! $files ) {
			throw new Exception( "Plugin has no files in {$tag}." );
		}

		$readme_files = preg_grep( '!^readme.(txt|md)$!i', $files );
		if ( ! $readme_files ) {
			throw new Exception( 'Plugin has no readme file.' );
		}

		$readme_file = reset( $readme_files );
		foreach ( $readme_files as $f ) {
			if ( '.txt' == strtolower( substr( $f, - 4 ) ) ) {
				$readme_file = $f;
				break;
			}
		}

		$readme_file = self::PLUGIN_SVN_BASE . "/{$this->plugin}/{$tag}/{$readme_file}";
		$readme      = new Parser( $readme_file );

		if ( ! class_exists( '\PO' ) ) {
			require_once ABSPATH . '/wp-includes/pomo/po.php';
		}

		$pot = new \PO;
		$pot->set_header( 'MIME-Version', '1.0' );
		$pot->set_header( 'Content-Type', 'text/plain; charset=UTF-8' );
		$pot->set_header( 'Content-Transfer-Encoding', '8bit' );

		$str_priorities = [];

		if ( $readme->name ) {
			$pot->add_entry( new \Translation_Entry( [
				'singular'           => $readme->name,
				'extracted_comments' => 'Plugin name.',
			] ) );

			$str_priorities[ $readme->name ] = 1;
		}

		if ( $readme->short_description ) {
			$pot->add_entry( new \Translation_Entry( [
				'singular'           => $readme->short_description,
				'extracted_comments' => 'Short description.',
			] ) );

			$str_priorities[ $readme->name ] = 1;
		}

		if ( $readme->screenshots ) {
			foreach ( $readme->screenshots as $screenshot ) {
				$pot->add_entry( new \Translation_Entry( [
					'singular'           => $screenshot,
					'extracted_comments' => 'Screenshot description.',
				] ) );
			}
		}

		$section_strings = [];

		foreach ( $readme->sections as $section_key => $section_text ) {
			if ( 'changelog' !== $section_key ) { // No need to scan non-translatable version headers in changelog.
				if ( preg_match_all( '|<h[3-4]+[^>]*>(.+)</h[3-4]+>|', $section_text, $matches ) ) {
					if ( ! empty( $matches[1] ) ) {
						foreach ( $matches[1] as $text ) {
							$section_strings = $this->handle_translator_comment( $section_strings, $text, "{$section_key} header" );
						}
					}
				}
			}

			if ( preg_match_all( '|<li>([\s\S]*?)</li>|', $section_text, $matches ) ) {
				if ( ! empty( $matches[1] ) ) {
					foreach ( $matches[1] as $text ) {
						$section_strings = $this->handle_translator_comment( $section_strings, $text, "{$section_key} list item" );
						if ( 'changelog' === $section_key ) {
							$str_priorities[ $text ] = -1;
						}
					}
				}
			}

			if ( preg_match_all( '|<p>([\s\S]*?)</p>|', $section_text, $matches ) ) {
				if ( ! empty( $matches[1] ) ) {
					foreach ( $matches[1] as $text ) {
						$section_strings = $this->handle_translator_comment( $section_strings, trim( $text ), "{$section_key} paragraph" );
						if ( 'changelog' === $section_key ) {
							$str_priorities[ $text ] = - 1;
						}
					}
				}
			}
		}

		foreach ( $section_strings as $text => $comments ) {
			$pot->add_entry( new \Translation_Entry( [
				'singular'           => $text,
				'extracted_comments' => 'Found in ' . implode( $comments, ', ' ) . '.',
			] ) );
		}

		$tmp_directory = Filesystem::temp_directory( $this->plugin . '-readme-' . $tag );
		$pot_file = "{$tmp_directory}/{$this->plugin}-readme.pot";

		$exported = $pot->export_to_file( $pot_file );
		if ( ! $exported ) {
			throw new Exception( 'POT file not extracted.' );
		}

		$result = $this->set_glotpress_for_plugin( $this->plugin, 'readme' );
		if ( is_wp_error( $result ) ) {
			throw new Exception( $result->get_error_message() );
		}

		$branch = ( 'trunk' === $tag ) ? 'dev-readme' : 'stable-readme';
		$this->import_pot_to_glotpress_project( $this->plugin, $branch, $pot_file, $str_priorities );
	}

	/**
	 * Handles GlotPress "extracted comments" for translators to get context for each string translations.
	 *
	 * @param array $array Empty or existing arrays of string and comments
	 * @param string $key Unique key
	 * @param string $val Comment value
	 *
	 * @return array
	 */
	private function handle_translator_comment( $array, $key, $val ) {
		$val = trim( preg_replace( '/[^a-z0-9]/i', ' ', $val ) ); // cleanup key names for display.

		if ( empty( $array[ $key ] ) ) {
			$array[ $key ] = array( $val );
		} elseif ( ! in_array( $val, $array[ $key ] ) ) {
			$array[ $key ][] = $val;
		}

		return $array;
	}
}
