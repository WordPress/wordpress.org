<?php

namespace WordPressdotorg\GlotPress\Theme_Directory\CLI;

use GP;
use MakePOT;
use WP_CLI;
use WP_CLI_Command;

class Set_Theme_Project extends WP_CLI_Command {

	/**
	 * Holds the path of the master project.
	 *
	 * @var string
	 */
	private $master_project_path = 'wp-themes';

	/**
	 * Holds the path of the projects to copy translation sets from.
	 *
	 * @var string
	 */
	private $project_path_for_sets = 'wp/dev';

	/**
	 * Holds the path to a temporary directory.
	 *
	 * @var string
	 */
	private $temp_dir;

	/**
	 * MakePot instance.
	 *
	 * @var MakePot
	 */
	private $makepot;

	public function __construct() {
		if ( ! file_exists( '/tmp/wporg-themes-i18n/' ) ) {
			mkdir( '/tmp/wporg-themes-i18n/' );
		}

		$this->temp_dir = tempnam( '/tmp/wporg-themes-i18n/', '' );
		unlink( $this->temp_dir );
		if ( ! mkdir( $this->temp_dir ) ) {
			WP_CLI::error( "Couldn't create temporary directory." );
		}

		$this->checkout_tools();
		if ( ! file_exists( $this->temp_dir . '/i18n-tools/makepot.php' ) ) {
			WP_CLI::error( "Couldn't find MakePot." );
		}
		require_once $this->temp_dir . '/i18n-tools/makepot.php';
		$this->makepot = new MakePot();
	}

	/**
	 * Add/update a theme project.
	 *
	 * ## OPTIONS
	 *
	 * <slug>
	 * : Slug of a theme
	 *
	 * <version>
	 * : Version of a theme
	 *
	 */
	public function __invoke( $args, $assoc_args ) {
		$theme_slug    = $args[0];
		$theme_version = $args[1];

		// We pass 'inactive' as the theme version due to caching issues of fetching the API response.
		if ( 'inactive' === $theme_version ) {
			$this->mark_glotpress_project_inactive( $theme_slug );
			WP_CLI::success( "{$theme_slug} marked as inactive." );
			return;
		}

		$theme_dir = $this->checkout_theme( $theme_slug, $theme_version );
		if ( ! $theme_dir ) {
			WP_CLI::error( "{$theme_slug} {$theme_version} could not be found." );
		}

		$theme_data = $this->get_theme_data( $theme_slug, $theme_dir );

		$project = $this->find_create_update_glotpress_project( $theme_slug, $theme_data );
		$pot = $this->generate_pot( $theme_slug, $theme_dir );
		$this->import_pot_to_glotpress( $pot, $project );

		$this->cleanup_theme( $theme_slug );

		gp_clean_translation_sets_cache( $project->id );
		WP_CLI::success( "{$theme_slug} {$theme_version} imported." );
	}

	/**
	 * Creates the Theme checkout to operate upon
	 */
	private function checkout_theme( $theme_slug, $theme_version ) {
		$theme_dir = "{$this->temp_dir}/{$theme_slug}/";
		$theme_svn = "https://themes.svn.wordpress.org/{$theme_slug}/{$theme_version}/";
		$esc_theme_dir = escapeshellarg( $theme_dir );
		$esc_theme_svn = escapeshellarg( $theme_svn );

		`svn export --non-interactive {$esc_theme_svn} {$esc_theme_dir}`;

		if ( ! file_exists( $theme_dir ) || ! file_exists( "{$theme_dir}style.css" ) ) {
			return false;
		}

		return $theme_dir;
	}

	/**
	 * A cutdown version of core's get_file_data() to return specific headers from a Theme, plus it's screenshot.
	 */
	private function get_theme_data( $theme_slug, $theme_dir ) {
		$style_css = "{$theme_dir}style.css";

		$theme_data = array(
			'name'        => 'Theme Name',
			'version'     => 'Version',
			'description' => 'Description',
		);

		$file_data = file_get_contents( $style_css, false, null, -1, 8192 );

		// Make sure we catch CR-only line endings.
		$file_data = str_replace( "\r", "\n", $file_data );

		foreach ( $theme_data as $field => $regex ) {
			if ( preg_match( '/^[ \t\/*#@]*' . preg_quote( $regex, '/' ) . ':(.*)$/mi', $file_data, $match ) && $match[1] ) {
				$theme_data[ $field ] = strip_tags( _cleanup_header_comment( $match[1] ) );
			} else {
				$theme_data[ $field ] = '';
			}
		}

		// Screenshot
		$theme_data['screenshot'] = '';
		foreach ( array( 'png', 'jpg' ) as $ext ) {
			if ( ! file_exists( "{$theme_dir}screenshot.$ext" ) ) {
				continue;
			}

			$theme_data['screenshot'] = "themes.svn.wordpress.org/{$theme_slug}/{$theme_data['version']}/screenshot.{$ext}";
		}

		return $theme_data;
	}

	/**
	 * Create a temporary pot file for a theme.
	 *
	 * @param string $theme_slug The theme slug to generate the pot for.
	 * @param string $theme_dir  The directory containing the theme.
	 *
	 * @return string The path to the .pot file.
	 */
	private function generate_pot( $theme_slug, $theme_dir ) {
		$pot_file = "{$this->temp_dir}/{$theme_slug}.pot";
		$this->makepot->wp_theme( $theme_dir, $pot_file );
		return $pot_file;
	}

	/**
	 * Finds / Creates / Updates a GlotPress project for a given Theme.
	 *
	 * @param string $theme_slug The theme slug to generate the project from.
	 * @param array  $theme_data The theme data (headers + screenshot) from get_theme_data().
	 * @return \GP_Project|false GP project on success, false on failure.
	 */
	private function find_create_update_glotpress_project( $theme_slug, $theme_data ) {
		$parent_project = GP::$project->by_path( $this->master_project_path );
		$project_path = $parent_project->path . '/' . $theme_slug;

		$project_args = array(
			'name'                => $theme_data['name'],
			'slug'                => $theme_slug,
			'description'         => $theme_data['description'] . "<br><br><a href='https://wordpress.org/themes/{$theme_slug}'>WordPress.org Theme Page</a>",
			'parent_project_id'   => $parent_project->id,
			'source_url_template' => "https://themes.trac.wordpress.org/browser/$theme_slug/{$theme_data['version']}/%file%#L%line%",
			'active'              => 1,
		);

		$project = GP::$project->by_path( $project_path );
		if ( ! $project ) {
			WP_CLI::line( 'Creating project' );
			$project = GP::$project->create_and_select( $project_args );
			if ( ! $project ) {
				return false;
			}
		} else {
			/*
			 * Update the project details if any have changed.
			 * GlotPress doesn't do a check first, so we shall - avoids subsequent reads going to master.
			 */
			foreach ( $project_args as $arg => $value ) {
				if ( $project->$arg != $value ) {
					$project->save( $project_args );
					break;
				}
			}
		}

		$this->create_update_glotpress_translation_sets( $project );

		gp_update_meta( $project->id, 'screenshot', $theme_data['screenshot'], 'wp-themes' );
		gp_update_meta( $project->id, 'version',    $theme_data['version'],    'wp-themes' );

		return $project;
	}

	/**
	 * Marks a theme project as inactive.
	 *
	 * @param string $theme_slug The theme to mark inactive.
	 */
	private function mark_glotpress_project_inactive( $theme_slug ) {
		$project = GP::$project->by_path( "{$this->master_project_path}/{$theme_slug}" );
		if ( $project ) {
			$project->save( array(
				'active' => 0,
			) );
		}
	}

	/**
	 * Creates / Updates the translation sets for a theme project.
	 *
	 * @param GP_Project $project The GlotPress project to create the sets on.
	 */
	private function create_update_glotpress_translation_sets( $project ) {
		$translation_sets = (array) GP::$translation_set->by_project_id( GP::$project->by_path( $this->project_path_for_sets )->id );

		$existing_sets = array();
		foreach ( GP::$translation_set->by_project_id( $project->id ) as $set ) {
			$existing_sets[ $set->locale . ':' . $set->slug ] = true;
		}

		foreach ( $translation_sets as $set ) {
			if ( isset( $existing_sets[ $set->locale . ':' . $set->slug ] ) ) {
				// This translation set already exists.
				continue;
			}

			WP_CLI::line( sprintf( 'Creating translation set %s (%s)', $set->name, $set->locale . ( $set->slug != 'default' ? '/' . $set->slug : '' ) ) );

			GP::$translation_set->create( array(
				'project_id' => $project->id,
				'name'       => $set->name,
				'locale'     => $set->locale,
				'slug'       => $set->slug,
			) );
		}
	}

	/**
	 * Imports a pot file into GlotPress, also alters the priority for various fields.
	 *
	 * @param string     $pot_file The themes pot file to import.
	 * @param \GP_Project $project  The theme project to import into.
	 */
	private function import_pot_to_glotpress( $pot_file, $project ) {
		$format = gp_array_get( GP::$formats, 'po', null );
		$originals = $format->read_originals_from_file( $pot_file, $project );

		add_filter( 'gp_import_original_array', array( $this, 'filter_import_original_priority' ) );

		list( $originals_added, $originals_existing, $originals_fuzzied, $originals_obsoleted ) = GP::$original->import_for_project( $project, $originals );

		remove_filter( 'gp_import_original_array', array( $this, 'filter_import_original_priority' ) );

		WP_CLI::line( sprintf(
			'%1$s new strings added, %2$s updated, %3$s fuzzied, and %4$s obsoleted.',
			$originals_added,
			$originals_existing,
			$originals_fuzzied,
			$originals_obsoleted
		) );
	}


	/**
	 * Sets the priority of a string in GlotPress.
	 * 1 = High, 0 = normal, -1 = low.
	 *
	 * @param array $data The original data.
	 * @return array Original data with priorities.
	 */
	public function filter_import_original_priority( $data ) {
		$priorities = array(
			// These are important strings that we need translated
			'Plugin Name of the plugin/theme' => 1,
			'Theme Name of the plugin/theme'  => 1,
			'Description of the plugin/theme' => 1,
			// Regular strings are more important than these:
			'Plugin URI of the plugin/theme'  => -1,
			'Theme URI of the plugin/theme'   => -1,
			'Author of the plugin/theme'      => -1,
			'Author URI of the plugin/theme'  => -1,
		);
		if ( isset( $priorities[ $data['comment'] ] ) ) {
			$data['priority'] = $priorities[ $data['comment'] ];
		}
		return $data;
	}

	/**
	 * Creates an i18n-tools checkout so we have MakePot available.
	 */
	private function checkout_tools() {
		$tools_dir = "{$this->temp_dir}/i18n-tools/";
		if ( ! file_exists( $tools_dir ) ) {
			$esc_tools_dir = escapeshellarg( $tools_dir );
			`svn export --non-interactive https://i18n.svn.wordpress.org/tools/trunk {$esc_tools_dir}`;
		}
	}

	/**
	 * Cleanup a theme directory, for when this is used in batch mode.
	 */
	private function cleanup_theme( $theme_slug ) {
		if ( $this->temp_dir ) {
			$esc_theme_dir = escapeshellarg( $this->temp_dir . $theme_slug );
			`rm -rf {$esc_theme_dir}`;
			@unlink( $this->temp_dir . $theme_slug . '.pot' );
		}
	}

	/**
	 * Clean up after the theme process is complete.
	 */
	public function __destruct() {
		if ( $this->temp_dir ) {
			$esc_temp_dir = escapeshellarg( $this->temp_dir );
			`rm -rf {$esc_temp_dir}`;
		}
	}
}
