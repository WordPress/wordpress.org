<?php
/**
 * Plugin name: GlotPress: Plugin Directory Bridge
 * Description: Clears the content translation cache for plugins (readme/code) hosted on wordpress.org based on actions taken withing translate.wordpress.org.
 * Version:     1.0
 * Author:      WordPress.org
 * Author URI:  http://wordpress.org/
 * License:     GPLv2 or later
 */

class WPorg_GP_Plugin_Directory {
	public $master_project   = 'wp-plugins';
	public $i18n_cache_group = 'plugins-i18n';

	public function __construct() {
		add_action( 'init', array( $this, 'add_global_cache_group' ) );
		add_action( 'originals_imported', array( $this, 'originals_imported' ) );
		add_action( 'translation_created', array( $this, 'translation_created' ) );
		add_action( 'translation_saved', array( $this, 'translation_saved' ) );
	}

	/**
	 * Registers the global cache group wordpress.org/plugins/ uses for its display cache.
	 */
	public function add_global_cache_group() {
		wp_cache_add_global_groups( $this->i18n_cache_group );
	}

	/**
	 * Triggers a cache purge when a new originals were imported.
	 *
	 * @param int $project_id The project ID.
	 */
	public function originals_imported( $project_id ) {
		$project = GP::$project->get( $project_id );
		if ( empty( $project->path ) || ! $this->project_is_plugin( $project->path ) ) {
			return;
		}

		$this->delete_plugin_i18n_cache_keys_for_project( $project_id );
	}

	/**
	 * Triggers a cache purge when a new translation was created.
	 *
	 * @param GP_Translation $translation Created translation.
	 */
	public function translation_created( $translation ) {
		if ( ! $this->project_is_plugin( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}

		$this->delete_plugin_i18n_cache_on_translation_edit( $translation );
	}

	/**
	 * Triggers a cache purge when a translation was updated.
	 *
	 * @param GP_Translation $translation Updated translation.
	 */
	public function translation_saved( $translation ) {
		if ( ! $this->project_is_plugin( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}

		$this->delete_plugin_i18n_cache_on_translation_edit( $translation );
	}

	/**
	 * Returns whether a project path belongs to the plugins project.
	 *
	 * @param string $path Path of a project.
	 *
	 * @return bool True if it's a plugin, false if not.
	 */
	private function project_is_plugin( $path ) {
		if ( empty( $path ) ) {
			return false;
		}

		$path = '/' . trim( $path, '/' ) . '/';
		if ( false === strpos( $path, "/{$this->master_project}/" ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Builds the cache prefix for a plugin.
	 *
	 * @param int $project_id The project ID.
	 *
	 * @return string The cache prefix, empty if project isn't a plugin.
	 */
	private function get_plugin_i18n_cache_prefix( $project_id ) {
		$project = GP::$project->get( $project_id );
		if ( empty( $project->path ) || !$this->project_is_plugin( $project->path ) ) {
			return '';
		}

		$project_dirs = explode( '/', trim( $project->path, '/' ) );
		if ( empty( $project_dirs ) || 3 !== count( $project_dirs ) || $project_dirs[0] !== $this->master_project ) {
			return '';
		}

		return "{$this->master_project}:{$project_dirs[1]}:{$project_dirs[2]}";
	}

	/**
	 * Deletes a set of known cache keys for a plugin.
	 *
	 * @param string $prefix Cache key prefix, such as 'plugin:livejournal-importer:readme-stable'.
	 * @param string $set    Set, such as 'original', 'fr', 'de'.
	 */
	private function delete_plugin_i18n_cache_keys_for( $prefix, $set ) {
		$suffixes = array(
			'translation_set_id', 'title', 'short_description', 'installation', 'description',
			'faq', 'screenshots', 'changelog', 'other_notes',
		);
		foreach ( $suffixes as $suffix ) {
			$cache_key = "{$prefix}:{$set}:{$suffix}";
			wp_cache_delete( $cache_key, $this->i18n_cache_group );
		}
	}

	/**
	 * Deletes the cached originals of a plugin.
	 *
	 * @param int $project_id The project ID.
	 */
	private function delete_plugin_i18n_cache_keys_for_project( $project_id ) {
		$prefix = $this->get_plugin_i18n_cache_prefix( (int) $project_id );
		if ( ! $prefix ) {
			return;
		}

		wp_cache_delete( "{$prefix}:originals", $this->i18n_cache_group );
		wp_cache_delete( "{$prefix}:branch_id", $this->i18n_cache_group );
		$this->delete_plugin_i18n_cache_keys_for( $prefix, 'original' );

		$translation_sets = (array) GP::$translation_set->by_project_id( $project_id );
		foreach ( $translation_sets as $translation_set ) {
			$this->delete_plugin_i18n_cache_keys_for( $prefix, $translation_set->locale );
		}
	}

	/**
	 * Deletes a cache keys of a locale for a plugin.
	 *
	 * @param int    $project_id The project ID.
	 * @param string $locale     GlotPress slug of a locale.
	 */
	private function delete_plugin_i18n_cache_keys_for_locale( $project_id, $locale ) {
		$prefix = $this->get_plugin_i18n_cache_prefix( (int) $project_id );
		if ( ! $prefix ) {
			return;
		}

		$this->delete_plugin_i18n_cache_keys_for( $prefix, $locale );
	}

	/**
	 * Deletes the cache on a translation edit.
	 *
	 * @param GP_Translation $translation The edited translation.
	 */
	private function delete_plugin_i18n_cache_on_translation_edit( $translation ) {
		$original = GP::$original->get( $translation->original_id );
		if ( ! $original ) {
			return;
		}

		$translation_set = GP::$translation_set->get( $translation->translation_set_id );
		if ( ! $translation_set ) {
			return;
		}

		$this->delete_plugin_i18n_cache_keys_for_locale( $original->project_id, $translation_set->locale );
	}
}

function wporg_gp_plugin_directory() {
	global $wporg_gp_plugin_directory;

	if ( ! isset( $wporg_gp_plugin_directory ) ) {
		$wporg_gp_plugin_directory = new WPorg_GP_Plugin_Directory();
	}

	return $wporg_gp_plugin_directory;
}
add_action( 'plugins_loaded', 'wporg_gp_plugin_directory' );
