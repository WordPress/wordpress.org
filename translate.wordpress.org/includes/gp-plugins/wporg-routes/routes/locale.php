<?php
/**
 * Locale Route Class.
 *
 * Provides the route for translate.wordpress.org/languages.
 */
class GP_WPorg_Route_Locale extends GP_Route {

	/**
	 * Prints all exisiting locales as cards.
	 */
	public function get_locales() {
		$locales = array();
		$existing_locales = GP::$translation_set->existing_locales();
		foreach ( $existing_locales as $locale ) {
			$locales[] = GP_Locales::by_slug( $locale );
		}
		usort( $locales, array( $this, '_sort_english_name_callback') );
		unset( $existing_locales );

		$contributors_count = wp_cache_get( 'contributors-count', 'wporg-translate' );
		if ( false === $contributors_count ) {
			$contributors_count = array();
		}

		$translation_status = wp_cache_get( 'translation-status', 'wporg-translate' );
		if ( false === $translation_status ) {
			$translation_status = array();
		}

		$this->tmpl( 'locales', get_defined_vars() );
	}

	/**
	 * Prints projects/translation sets of a top level project.
	 *
	 * @param string $locale_slug      Slug of the locale.
	 * @param string $set_slug         Slug of the translation set.
	 * @param string $project_path     Path of a project.
	 */
	public function get_locale_projects( $locale_slug, $set_slug = 'default', $project_path = 'wp' ) {
		$locale = GP_Locales::by_slug( $locale_slug );
		if ( ! $locale ) {
			return $this->die_with_404();
		}

		$project = GP::$project->by_path( $project_path );
		if ( ! $project ) {
			return $this->die_with_404();
		}

		$sub_projects = $this->get_active_sub_projects( $project );
		if ( ! $sub_projects ) {
			return $this->die_with_404();
		}

		usort( $sub_projects, array( $this, '_sort_name_callback' ) );

		$project_status = $project_icons = array();
		foreach ( $sub_projects as $key => $sub_project ) {
			$status = $this->get_project_status( $sub_project, $locale_slug, $set_slug );
			if ( ! $status->all_count ) {
				unset( $sub_projects[ $key ] );
			}

			$project_status[ $sub_project->id ] = $status;
			$project_icons[ $sub_project->id ] = $this->get_project_icon( $project, $sub_project );
		}

		$contributors_count = wp_cache_get( 'contributors-count', 'wporg-translate' );
		if ( false === $contributors_count ) {
			$contributors_count = array();
		}

		$top_level_projects = GP::$project->top_level();
		usort( $top_level_projects, array( $this, '_sort_reverse_name_callback' ) );

		$variants = $this->get_locale_variants( $locale_slug, array_keys( $project_status ) );

		$this->tmpl( 'locale', get_defined_vars() );
	}

	/**
	 * Prints projects/translation sets of a sub project.
	 *
	 * @param string $locale_slug      Slug of the locale.
	 * @param string $set_slug         Slug of the translation set.
	 * @param string $project_path     Path of a project.
	 * @param string $sub_project_path Path of a sub project.
	 */
	public function get_locale_project( $locale_slug, $set_slug, $project_path, $sub_project_path ) {
		$locale = GP_Locales::by_slug( $locale_slug );
		if ( ! $locale ) {
			return $this->die_with_404();
		}

		$project = GP::$project->by_path( $project_path );
		if ( ! $project ) {
			return $this->die_with_404();
		}

		$sub_project = GP::$project->by_path( $project_path . '/' . $sub_project_path );
		if ( ! $sub_project ) {
			return $this->die_with_404();
		}

		$project_status = $this->get_project_status( $sub_project, $locale_slug, $set_slug );
		$sub_project_status = $this->get_project_status( $sub_project, $locale_slug, $set_slug, null, false );

		$project_icon = $this->get_project_icon( $project, $sub_project, 64 );

		$contributors_count = wp_cache_get( 'contributors-count', 'wporg-translate' );
		if ( false === $contributors_count ) {
			$contributors_count = array();
		}

		$sub_projects = $this->get_active_sub_projects( $sub_project, true );
		if ( $sub_projects ) {
			$sub_project_statuses = array();
			foreach ( $sub_projects as $key => $_sub_project ) {
				$status = $this->get_project_status( $_sub_project, $locale_slug, $set_slug );
				if ( ! $status->all_count ) {
					unset( $sub_projects[ $key ] );
				}

				$sub_project_statuses[ $_sub_project->id ] = $status;
			}

			$variants = $this->get_locale_variants( $locale_slug, array_keys( $sub_project_statuses ) );
		} else {
			$variants = $this->get_locale_variants( $locale_slug, array( $sub_project->id ) );
		}

		$this->tmpl( 'locale-project', get_defined_vars() );
	}

	/**
	 * Returns markup for project icons.
	 *
	 * @param GP_Project $project     A GlotPress project.
	 * @param GP_Project $sub_project A sub project of a GlotPress project.
	 * @param int        $size        Size of icon.
	 * @return string HTML markup of an icon.
	 */
	private function get_project_icon( $project, $sub_project, $size = 128 ) {
		switch( $project->slug ) {
			case 'wp':
				return '<div class="wordpress-icon"><span class="dashicons dashicons-wordpress-alt"></span></div>';
			case 'bbpress':
			case 'buddypress':
				require_once WPORGPATH . 'extend/plugins-plugins/_plugin-icons.php';
				if ( function_exists( 'wporg_get_plugin_icon' ) ) {
					return wporg_get_plugin_icon( $project->slug, $size );
				}
			case 'wp-plugins':
				require_once WPORGPATH . 'extend/plugins-plugins/_plugin-icons.php';
				if ( function_exists( 'wporg_get_plugin_icon' ) ) {
					return wporg_get_plugin_icon( $sub_project->slug, $size );
				}
			default:
				return '<div class="default-icon"><span class="dashicons dashicons-translation"></span></div>';
		}
	}

	/**
	 * Retrieves non-default slugs of translation sets for a list of
	 * project IDs.
	 *
	 * @param string $locale     Slug of a GlotPress locale.
	 * @param array $project_ids List of project IDs.
	 * @return array List of non-default slugs.
	 */
	private function get_locale_variants( $locale, $project_ids ) {
		global $gpdb;

		$table = GP::$translation_set->table;
		$project_ids = implode( ',', $project_ids );
		$slugs = $gpdb->get_col( $gpdb->prepare( "
			SELECT DISTINCT(slug), name
			FROM $table
			WHERE
				project_id IN( $project_ids )
				AND locale = %s
		", $locale ) );

		return $slugs;
	}

	/**
	 * Calculates the status of a project.
	 *
	 * @param GP_Project $project           The GlotPress project.
	 * @param string     $locale            Slug of GlotPress locale.
	 * @param string     $set_slug          Slug of the translation set.
	 * @param object     $status            The status object.
	 * @param bool       $calc_sub_projects Whether sub projects should be calculated too.
	 *                                      Default true.
	 * @return object The status of a project.
	 */
	private function get_project_status( $project, $locale, $set_slug, $status = null, $calc_sub_projects = true ) {
		if ( null === $status ) {
			$status = new stdClass;
			$status->sub_projects_count = 0;
			$status->waiting_count      = 0;
			$status->current_count      = 0;
			$status->fuzzy_count        = 0;
			$status->all_count          = 0;
		}

		$set = GP::$translation_set->by_project_id_slug_and_locale(
			$project->id,
			$set_slug,
			$locale
		);

		if ( $set ) {
			$status->sub_projects_count += 1;
			$status->waiting_count      += (int) $set->waiting_count();
			$status->current_count      += (int) $set->current_count();
			$status->fuzzy_count        += (int) $set->fuzzy_count();
			$status->all_count          += (int) $set->all_count();
		}

		if ( $calc_sub_projects ) {
			$sub_projects = $this->get_active_sub_projects( $project, true );
			if ( $sub_projects ) {
				foreach ( $sub_projects as $sub_project ) {
					$this->get_project_status( $sub_project, $locale, $set_slug, $status );
				}
			}
		}

		return $status;
	}

	/**
	 * Retrieves active sub projects.
	 *
	 * @param  GP_Project $project           The parent project
	 * @param  bool       $with_sub_projects Whether sub projects should be fetched too.
	 *                                       Default false.
	 * @return array List of sub projects.
	 */
	private function get_active_sub_projects( $project, $with_sub_projects = false ) {
		$_projects = $project->many( "
			SELECT *
			FROM $project->table
			WHERE
				parent_project_id = %d AND
				active = 1
			ORDER BY id ASC
		", $project->id );

		$projects = array();
		foreach ( $_projects as $project ) {
			$projects[ $project->id ] = $project;

			if ( $with_sub_projects ) {
				// e.g. wp/dev/admin/network
				$sub_projects = $project->many( "
					SELECT *
					FROM $project->table
					WHERE
						parent_project_id = %d AND
						active = 1
					ORDER BY id ASC
				", $project->id );

				foreach ( $sub_projects as $sub_project ) {
					$projects[ $sub_project->id ] = $sub_project;
				}
				unset( $sub_projects);
			}
		}
		unset( $_projects );

		return $projects;
	}

	private function _sort_reverse_name_callback( $a, $b ) {
		return - strcasecmp( $a->name, $b->name );
	}

	private function _sort_name_callback( $a, $b ) {
		return strcasecmp( $a->name, $b->name );
	}

	private function _sort_english_name_callback( $a, $b ) {
		return $a->english_name > $b->english_name;
	}
}
