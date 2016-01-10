<?php
/**
 * This plugin clears the content translation cache for plugins (readme/code) hosted on wp.org based on actions taken withing GlotPress.
 *
 * @author stephdau
 */
class GP_WPorg_Plugins extends GP_Plugin {
	public $master_project   = 'wp-plugins';
	public $i18n_cache_group = 'plugins-i18n';

	public function __construct() {
		parent::__construct();
		$this->add_action( 'init' );
		$this->add_action( 'originals_imported' );

		$this->add_action( 'translation_created' );
		$this->add_action( 'translation_saved' );
	}

	/**
	 * Making sure to register the global cache group wordpress.org/plugins/ uses for its display cache.
	 */
	public function init() {
		wp_cache_add_global_groups( $this->i18n_cache_group );
	}

	/**
	 * @param $project_id
	 */
	public function originals_imported( $project_id ) {
		$project = GP::$project->get( $project_id );
		if ( empty( $project->path ) || !$this->project_is_plugin( $project->path ) ) {
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
	 * @param $path
	 *
	 * @return bool
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
	 * @param $project_id int
	 *
	 * @return string
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
	 * Deletes a set of known cache keys for a plugin
	 *
	 * @param $prefix string Cache key prefix, such as 'plugin:livejournal-importer:readme-stable'
	 * @param $set string Set, such as 'original', 'fr', 'de'.
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
	 * @param $project_id int
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
	 * @param $project_id int
	 * @param $locale string
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
GP::$plugins->wporg_plugins = new GP_WPorg_Plugins;
