<?php
/**
 * This script updates cached data for some heavy queries.
 */
require_once dirname( dirname( dirname( __DIR__ ) ) ) . '/glotpress/gp-load.php';

class WPorg_Update_Cached_Data extends GP_CLI {

	public $cache_group = 'wporg-translate';

	private $locales = array();

	public function run() {
		$this->locales = GP::$translation_set->existing_locales();

		$this->update_contributors_count();
		$this->update_translation_status();
	}

	/**
	 * Fetches contributors per locale.
	 */
	private function update_contributors_count() {
		global $gpdb;

		$counts = array();

		$db_counts = $gpdb->get_results( "SELECT locale, COUNT( distinct user_id ) as count FROM translate_user_translations_count WHERE accepted > 0 GROUP BY locale" );
		foreach ( $db_counts as $row ) {
			$counts[ $row->locale ] = $row->count;
		}

		foreach ( $this->locales as $locale ) {
			if ( ! isset( $counts[ $locale ] ) ) {
				$counts[ $locale ] = 0;
			}
		}

		wp_cache_set( 'contributors-count', $counts, $this->cache_group );
	}

	/**
	 * Calculates the translation status of the WordPress project per locale.
	 */
	private function update_translation_status() {
		global $gpdb;

		$projects = GP::$project->many( "
			SELECT *
			FROM {$gpdb->projects}
			WHERE
				path LIKE 'wp/dev%'
				AND active = '1'
		" );
		$translation_status = array();
		foreach ( $projects as $project ) {
			foreach ( $this->locales as $locale ) {
				$set = GP::$translation_set->by_project_id_slug_and_locale(
					$project->id,
					'default',
					$locale
				);

				if ( ! $set ) {
					continue;
				}

				if ( ! isset( $translation_status[ $locale ] ) ) {
					$translation_status[ $locale ] = new StdClass;
					$translation_status[ $locale ]->waiting_count = $set->waiting_count();
					$translation_status[ $locale ]->current_count = $set->current_count();
					$translation_status[ $locale ]->fuzzy_count   = $set->fuzzy_count();
					$translation_status[ $locale ]->all_count     = $set->all_count();
				} else {
					$translation_status[ $locale ]->waiting_count += $set->waiting_count();
					$translation_status[ $locale ]->current_count += $set->current_count();
					$translation_status[ $locale ]->fuzzy_count   += $set->fuzzy_count();
					$translation_status[ $locale ]->all_count     += $set->all_count();
				}
			}
		}

		wp_cache_set( 'translation-status', $translation_status, $this->cache_group );
	}
}

$wporg_update_cached_data = new WPorg_Update_Cached_Data;
$wporg_update_cached_data->run();
