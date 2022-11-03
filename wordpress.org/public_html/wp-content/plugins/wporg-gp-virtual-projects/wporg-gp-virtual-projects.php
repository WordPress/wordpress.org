<?php
/**
 * Plugin Name: WPORG Virtual projects for patterns.
 * Description: Creates virtual projects for the patterns.
 * Author: the WordPress team.
 */
class WPorg_Virtual_Projects {

	/**
	 * Adds filters to subprojects and to project status.
	 */
	public function __construct() {
		add_filter( 'gp_subprojects', array( $this, 'add_virtual_projects' ), 10, 1 );
		add_filter( 'gp_get_project_status', array( $this, 'gp_get_project_status' ), 10, 4 );
	}

	/**
	 * Adds virtual projects for the patterns, so each pattern is shown as a different project.
	 *
	 * The received and returned data is an array with:
	 * - $data['project']       The main project.
	 * - $data['sub_projects']  The subprojects.
	 * - $data['pages']         Pagination information.
	 *
	 * @param array $data The project, subprojects and pages.
	 *
	 * @return array The project, subprojects (or virtual projects) and pages.
	 */
	public function add_virtual_projects( $data ) {
		global $wpdb;
		if ( 'Patterns' != $data['project']->name ) {
			return $data;
		}

		$url_projects      = array();
		$pattern_originals = $wpdb->get_results(
			"SELECT `references` 
			FROM {$wpdb->gp_originals}  
			WHERE `project_id` = 473698 
				AND `status` = '+active'"
		);
		foreach ( $pattern_originals as $row ) {
			$row->references = trim( preg_replace( '/\s+/', ' ', $row->references ) );
			$urls            = explode( ' ', $row->references );
			foreach ( $urls as $url ) {
				$url_projects[] = $url;
			}
		}
		$url_projects = array_unique( $url_projects );
		sort( $url_projects );

		$pages['pages'] = (int) ceil( count( $url_projects ) / 21 );
		if ( null == $data['pages']['page'] ) {
			$pages['page'] = 1;
		} else {
			$pages['page'] = $data['pages']['page'];
		}
		if ( null == $data['pages']['per_page'] ) {
			$pages['per_page'] = 21;
		} else {
			$pages['per_page'] = $data['pages']['per_page'];
		}
		$pages['results'] = count( $url_projects );

		$url_projects = array_slice( $url_projects, ( $pages['page'] - 1 ) * $pages['per_page'], $pages['per_page'], true );

		$url_prefix = 'https://wordpress.org/patterns/pattern/';

		foreach ( $url_projects as $row ) {
			if ( substr( $row, 0, strlen( $url_prefix ) ) == $url_prefix ) {
				$name = substr( $row, strlen( $url_prefix ) );
			} else {
				$name = $row;
			}
			$subproject                      = new GP_Project();
			$name                            = rtrim( $name, '/' );
			$subproject->slug                = $name;
			$subproject->id                  = $name;
			$name                            = ucfirst( str_replace( '-', ' ', $name ) );
			$subproject->name                = $name;
			$subproject->description         = $name;
			$subproject->path                = 'patterns/' . $subproject->slug;
			$subproject->parent_project_id   = 473698;
			$subproject->active              = 1;
			$subproject->source_url_template = '';
			$virtual_subprojects[]           = $subproject;
		}
		$data['sub_projects'] = $virtual_subprojects;
		$data['pages']        = $pages;

		return $data;
	}

	/**
	 * Gets the strings in each status for a virtual project.
	 *
	 * @param array      $status  The project status.
	 * @param GP_Project $project The project to analyze.
	 * @param string     $locale  The locale to analyze.
	 * @param string     $slug    The locale slug.
	 *
	 * @return array The project status.
	 */
	public function gp_get_project_status( $status, $project, $locale, $slug ) {
		global $wpdb;

		if ( 473698 != $project->parent_project_id ) {
			return $status;
		}

		$status->sub_projects_count = 1;
		$status->waiting_count      = 0;
		$status->current_count      = 0;
		$status->fuzzy_count        = 0;
		$status->untranslated_count = 0;
		$status->all_count          = 0;
		$status->percent_complete   = 0;

		$url = 'https://wordpress.org/patterns/pattern/' . $project->id . '/';

		$original_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT `id`  
				FROM {$wpdb->gp_originals} 
				WHERE `project_id` = 473698 
					AND `status` = '+active' 
					AND `references` like %s;",
				'%' . $url . '%'
			)
		);

		$translation_set_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT `id` 
				FROM {$wpdb->gp_translation_sets}
				WHERE `project_id` = 473698
					AND `locale` = %s
					AND `slug` = %s",
				$locale,
				$slug
			)
		);

		$status->waiting_count      = $this->get_count( $original_ids, $translation_set_id, 'waiting' );
		$status->current_count      = $this->get_count( $original_ids, $translation_set_id, 'current' );
		$status->fuzzy_count        = $this->get_count( $original_ids, $translation_set_id, 'fuzzy' );
		$status->untranslated_count = $status->all_count - $status->current_count;
		$status->all_count          = count( $original_ids );
		$status->percent_complete   = round( $status->current_count / $status->all_count * 100 );

		return $status;
	}

	/**
	 * Gets teh number of translations in a status for a set of original ids.
	 *
	 * @param array  $original_ids       The original id for this pattern.
	 * @param int    $translation_set_id The translation set for the pattern project and locale.
	 * @param string $status             The query type: waiting, current or fuzzy.
	 *
	 * @return string|null
	 */
	private function get_count( $original_ids, $translation_set_id, $status ) {
		global $wpdb;

		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT count(id) 
				FROM {$wpdb->gp_translations} 
				WHERE `original_id` IN (%1s) 
				  AND `translation_set_id` = %d 
				  AND `status` = %s;",
				implode( ', ', $original_ids ),
				$translation_set_id,
				$status
			)
		);
	}
}

new WPorg_Virtual_Projects();
