<?php
/**
 * This plugin clears the content translation cache for plugins (readme/code) hosted on wp.org based on actions taken withing GlotPress.
 *
 * @author stephdau
 */
class GP_WPorg_Plugins extends GP_Plugin {
	var $master_project   = 'wp-plugins';
	var $i18n_cache_group = 'plugins-i18n';

	function __construct() {
		parent::__construct();
		$this->add_action( 'init' );
		$this->add_action( 'originals_imported' );
		$this->add_action( 'post_tmpl_load' );
	}

	/**
	 * Making sure to register the global cache group wordpress.org/plugins/ uses for its display cache.
	 */
	function init() {
		wp_cache_add_global_groups( $this->i18n_cache_group );
	}

	/**
	 * @param $project_id
	 */
	function originals_imported( $project_id ) {
		$project = GP::$project->get( $project_id );
		if ( empty( $project->path ) || !$this->project_is_plugin( $project->path ) )
			return;
		$this->delete_plugin_i18n_cache_keys_for_project( $project_id );
	}

	/**
	 * @param $action
	 */
	function post_tmpl_load( $action ) {
		if ( !$this->project_is_plugin( $_SERVER["REQUEST_URI"] ) )
			return;
		switch( $action ) {
			case 'translation-row':
				if ( ! empty( $_POST['translation'] ) )
					$this->delete_plugin_i18n_cache_on_translation_edit();
				break;
		}
	}

	/**
	 * @param $path
	 *
	 * @return bool
	 */
	function project_is_plugin( $path ) {
		if ( empty( $path ) )
			return false;
		$path = '/' . trim( $path, '/' ) . '/';
		if ( false === strpos( $path, "/{$this->master_project}/" ) )
			return false;
		return true;
	}

	/**
	 * @param $project_id int
	 *
	 * @return string
	 */
	function get_plugin_i18n_cache_prefix( $project_id ) {
		$project = GP::$project->get( $project_id );
		if ( empty( $project->path ) || !$this->project_is_plugin( $project->path ) )
			return '';
		$project_dirs = explode( '/', trim( $project->path, '/' ) );
		if ( empty( $project_dirs ) || 3 !== count( $project_dirs ) || $project_dirs[0] !== $this->master_project )
			return '';
		return "{$this->master_project}:{$project_dirs[1]}:{$project_dirs[2]}";
	}

	/**
	 * Deletes a set of known cache keys for a plugin
	 *
	 * @param $prefix string Cache key prefix, such as 'plugin:livejournal-importer:readme-stable'
	 * @param $set string Set, such as 'original', 'fr', 'de'.
	 */
	function delete_plugin_i18n_cache_keys_for( $prefix, $set ) {
		$suffixes = array(
			'translation_set_id', 'title', 'short_description', 'installation', 'description',
			'faq', 'screenshots', 'changelog', 'other_notes',
		);
		foreach ( $suffixes as $suffix ) {
			$cache_key = "{$prefix}:{$set}:{$suffix}";
			// error_log( serialize( array( $cache_key, $this->i18n_cache_group ) ) );
			wp_cache_delete( $cache_key, $this->i18n_cache_group );
		}
		// Deal with fr also existing as fr_FR, etc.
		if ( 2 === strlen( $set ) )
			$this->delete_plugin_i18n_cache_keys_for( $prefix, strtolower( $set ) . '_' . strtoupper( $set ) );
	}

	/**
	 * @param $project_id int
	 */
	function delete_plugin_i18n_cache_keys_for_project( $project_id ) {
		$prefix = $this->get_plugin_i18n_cache_prefix( (int) $project_id );
		if ( empty( $prefix ) )
			return;
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
	function delete_plugin_i18n_cache_keys_for_locale( $project_id, $locale ) {
		$prefix = $this->get_plugin_i18n_cache_prefix( (int) $project_id );
		if ( empty( $prefix ) )
			return;
		$this->delete_plugin_i18n_cache_keys_for( $prefix, $locale );
	}

	function delete_plugin_i18n_cache_on_translation_edit() {
		if ( empty( $_POST['translation'] ) )
			return;
		$tmp = array_keys( (array) $_POST['translation'] );
		if ( empty( $tmp[0] ) || !is_numeric( $tmp[0] ) )
			return;
		$original = GP::$original->get( (int) $tmp[0] );
		if ( empty( $original ) )
			return;
		$tmp = explode( '/', $_SERVER[ 'REQUEST_URI' ] );
		if ( empty( $tmp ) || count( $tmp ) < 2 )
			return;
		$this->delete_plugin_i18n_cache_keys_for_locale( $original->project_id, gp_sanitize_meta_key( $tmp[ count( $tmp ) - 2 ] ) );
	}
}
GP::$plugins->wporg_plugins = new GP_WPorg_Plugins;
