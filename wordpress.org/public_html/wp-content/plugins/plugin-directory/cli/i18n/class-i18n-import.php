<?php
namespace WordPressdotorg\Plugin_Directory\CLI\I18N;

use WordPressdotorg\Plugin_Directory\CLI\Import;
use WordPressdotorg\Plugin_Directory\Plugin_I18n;
use WordPressdotorg\Plugin_Directory\Tools\Filesystem;
use WP_Error;

/**
 * Class to handle plugin readme imports GlotPress.
 *
 * @package WordPressdotorg\Plugin_Directory\CLI\I18N
 */
abstract class I18n_Import {

	/**
	 * Slug of the plugin.
	 *
	 * @var string
	 */
	protected $plugin;

	/**
	 * Constructor.
	 *
	 * @param string $plugin The plugin slug.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Imports a specific tag to GlotPress.
	 *
	 * @param string $tag SVN tag of the import.
	 *
	 * @throws Exception
	 */
	abstract public function import_from_tag( $tag );

	/**
	 * Returns the SVN URL for a plugins tag.
	 *
	 * @param string $tag SVN tag or 'trunk'.
	 * @return string Plugins SVN URL.
	 */
	public function get_plugin_svn_url( $tag ) {
		if ( 'trunk' === $tag ) {
			return Import::PLUGIN_SVN_BASE . "/{$this->plugin}/trunk/";
		} else {
			return Import::PLUGIN_SVN_BASE . "/{$this->plugin}/tags/{$tag}/";
		}
	}

	/**
	 * Sets the required GlotPress environment for a plugin
	 * See translate/bin/projects/set-wp-plugin-project.php
	 *
	 * @param string $plugin_slug
	 * @param string $process_type code|readme
	 * @return string|WP_Error Status of the project setup:
	 *  - 'created' if project was initially created
	 *  - 'updated' if an existing project was updated
	 *  - WP_Error on failures.
	 */
	public function set_glotpress_for_plugin( $plugin_slug, $process_type ) {
		if ( empty( $plugin_slug ) ) {
			return;
		}

		if ( 'code' !== $process_type ) {
			$process_type = 'readme';
		}

		$cmd = WPORGTRANSLATE_WPCLI . ' wporg-translate set-plugin-project ' . escapeshellarg( $plugin_slug ) . ' ' . escapeshellarg( $process_type );

		$return_code = 0;
		$output      = [];
		exec( $cmd, $output, $return_code );
		$last_line = array_pop( $output );

		if ( 0 === $return_code ) {
			// Get the first word of the last line and return it as the status.
			$return = strtok( $last_line, ' ' );
			$return = strtolower( $return );
			if ( ! in_array( $return, array( 'created', 'updated' ) ) ) {
				$return = new WP_Error( 'undefined', 'An undefined error occurred while setting the GlotPress projects.' );
			}
		} else {
			switch ( $return_code ) {
				case 1:
					$return = new WP_Error( 'wrong-usage', 'The script for setting GlotPress projects was called incorrectly.' );
					break;
				case 2:
					$return = new WP_Error( 'master-project', 'The master project for the GlotPress projects couldn\'t be found.' );
					break;
				case 3:
					$return = new WP_Error( 'api-failure', 'The plugin API couldn\'t be reached while setting GlotPress projects.' );
					break;
				case 4:
					$return = new WP_Error( 'no-readme', 'The readme of the plugin couldn\'t be found while setting GlotPress projects.' );
					break;
				case 5:
					$return = new WP_Error( 'create-failure', 'An undefined error occurred while creating the main GlotPress project.' );
					break;
				default:
					$return = new WP_Error( 'undefined', 'An undefined error occurred while setting the GlotPress projects.' );
			}
		}

		return $return;
	}

	/**
	 * Import generated POT file to GlotPress.
	 *
	 * @param string $project       GP project slug to import to
	 * @param string $branch        GP project branch to import to (dev|stable)-readme
	 * @param string $file          Path to POT file
	 * @param array  $str_priorities GP string priorities
	 */
	public function import_pot_to_glotpress_project( $project, $branch, $file, $str_priorities = array() ) {
		global $wpdb;

		// Note: this will only work if the GlotPress project/sub-projects exist.
		$cmd = WPORGTRANSLATE_WPCLI . ' glotpress import-originals ' . escapeshellarg( "wp-plugins/{$project}/{$branch}" ) . ' ' . escapeshellarg( $file );
		exec( $cmd );

		if ( empty( $str_priorities ) ) {
			return;
		}

		// @todo: Make the `glotpress import-originals` CLI command handle this.
		$gp_branch_id = Plugin_I18n::instance()->get_gp_branch_id( $project, $branch );
		if ( $gp_branch_id ) {
			foreach ( $str_priorities as $str => $prio ) {
				if ( 1 !== $prio && -1 !== $prio ) {
					$prio = 0;
				}

				$wpdb->query( $wpdb->prepare(
					'UPDATE ' . GLOTPRESS_TABLE_PREFIX . 'originals SET priority = %d WHERE project_id = %d AND status = %s AND singular = %s',
					$prio, $gp_branch_id, '+active', $str
				) );
			}
		}
	}

	/**
	 * Import existing plugin translations to GlotPress.
	 * See translate/bin/translations/import-plugin-translations.php
	 *
	 * @param string $export_directory Relative path as provided by Dotorg_Plugins_Tracker.
	 * @param string $project          GP project slug to import to
	 * @param string $branch           GP project branch to import to (dev|stable)
	 */
	public function import_translations_to_glotpress_project( $export_directory, $project, $branch ) {
		$files = Filesystem::list_files( $export_directory, true, '/\.po$/' );

		if ( empty( $files ) ) {
			$files = Filesystem::list_files( $export_directory, true, '/\.mo$/' );
		}

		foreach ( $files as $file ) {
			$filename = basename( $file );
			if ( ! preg_match( '/(?:(.+)-)?([a-z]{2,3}(?:_[A-Z]{2})?(?:_[a-z0-9]+)?).(po|mo)$/', $filename, $match ) ) {
				continue;
			}

			list( , , $language, $ext ) = $match;

			// Fix some locales.
			switch ( $language ) {
				case 'ga_IR':
					$language = 'ga';
					break;
				case 'ca_ES':
					$language = 'ca';
					break;
				case 'el_GR':
					$language = 'el';
					break;
				case 'af_ZA':
					$language = 'af';
					break;
				case 'zh_cn':
				case 'zh_ZH':
					$language = 'zh_CN';
					break;
				case 'uk_UA':
					$language = 'uk';
					break;
				case 'sq_AL':
					$language = 'sq';
					break;
				case 'ga_IE':
					$language = 'ga';
					break;
				case 'gu_IN':
					$language = 'gu';
					break;
				case 'hy_AM':
					$language = 'hy';
					break;
				case 'eo_EO':
					$language = 'eo';
					break;
				case 'ar_AR':
					$language = 'ar';
					break;
				case 'hr_HR':
					$language = 'hr';
					break;
				case 'cs_CS':
					$language = 'cs_CZ';
					break;
				case 'vi_VN':
					$language = 'vi';
					break;
				case 'ja_JP':
					$language = 'ja';
					break;
				case 'tr':
					$language = 'tr_TR';
					break;
				case 'be_BY':
				case 'be':
					$language = 'bel';
					break;
			}

			$cmd = WPORGTRANSLATE_WPCLI . ' wporg-translate import-plugin-translations ' . escapeshellarg( "wp-plugins/{$project}/{$branch}" ) . ' ' . escapeshellarg( $language ) . ' ' . escapeshellarg( $file ) . ' --format=' . escapeshellarg( $ext );
			exec( $cmd );
		}
	}
}
