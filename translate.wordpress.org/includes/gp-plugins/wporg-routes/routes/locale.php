<?php
/**
 * Locale Route Class.
 *
 * Provides the route for translate.wordpress.org/locale/$locale.
 */
class GP_WPorg_Route_Locale extends GP_Route {

	/**
	 * Prints projects/translation sets of a top level project.
	 *
	 * @param string $locale_slug      Slug of the locale.
	 * @param string $set_slug         Slug of the translation set.
	 * @param string $project_path     Path of a project.
	 */
	public function get_locale_projects( $locale_slug, $set_slug = 'default', $project_path = 'wp' ) {
		global $gpdb;

		$per_page = 20;
		$page = (int) gp_get( 'page', 1 );
		$search = gp_get( 's', '' );

		$locale = GP_Locales::by_slug( $locale_slug );
		if ( ! $locale ) {
			return $this->die_with_404();
		}

		$project = GP::$project->by_path( $project_path );
		if ( ! $project ) {
			return $this->die_with_404();
		}

		$paged_sub_projects = $this->get_paged_active_sub_projects(
			$project,
			array(
				'page' => $page,
				'per_page' => $per_page,
				'orderby' => 'name',
				'search' => $search,
				'set_slug' => $set_slug,
				'locale' => $locale_slug,
			)
		);

		if ( ! $paged_sub_projects ) {
			return $this->die_with_404();
		}

		$sub_projects   = $paged_sub_projects['projects'];
		$pages          = $paged_sub_projects['pages'];
		unset( $paged_sub_projects );

		$project_status = $project_icons = array();
		foreach ( $sub_projects as $key => $sub_project ) {
			$project_status[ $sub_project->id ] = $this->get_project_status( $sub_project, $locale_slug, $set_slug );
			$project_icons[ $sub_project->id ] = $this->get_project_icon( $project, $sub_project );
		}

		$project_ids = array_keys( $project_status );
		$project_ids[] = $project->id;
		$project_ids = array_merge(
			$project_ids,
			$gpdb->get_col( "SELECT id FROM {$gpdb->projects} WHERE parent_project_id IN(" . implode(', ', $project_ids  ) . ")" )
		);

		$contributors_count = wp_cache_get( 'contributors-count', 'wporg-translate' );
		if ( false === $contributors_count ) {
			$contributors_count = array();
		}

		$top_level_projects = GP::$project->top_level();
		usort( $top_level_projects, array( $this, '_sort_reverse_name_callback' ) );

		$variants = $this->get_locale_variants( $locale_slug, $project_ids );
		// If there were no results for the current variant in the current project branch, it should still show it.
		if ( ! in_array( $set_slug, $variants, true ) ) {
			$variants[] = $set_slug;
		}

		$this->tmpl( 'locale-projects', get_defined_vars() );
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
				$status = $this->get_project_status( $_sub_project, $locale_slug, $set_slug, null, false );

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
			case 'meta':
				switch( $sub_project->slug ) {
					case 'forums':
						return '<div class="default-icon"><span class="dashicons dashicons-format-chat"></span></div>';
					case 'rosetta':
						return '<div class="default-icon"><span class="dashicons dashicons-admin-site"></span></div>';
					case 'plugins':
						return '<div class="default-icon"><span class="dashicons dashicons-admin-plugins"></span></div>';
					case 'themes':
						return '<div class="default-icon"><span class="dashicons dashicons-admin-appearance"></span></div>';
					case 'browsehappy':
						return '<div class="icon"><img src="/gp-templates/images/browsehappy.png" width="' . $size . '" height="' . $size . '"></div>';
					default:
						return '<div class="default-icon"><span class="dashicons dashicons-networking"></span></div>';
				}
			case 'wp-themes':
				$screenshot = gp_get_meta( 'wp-themes', $sub_project->id, 'screenshot' );
				if ( $screenshot ) {
					return '<div class="theme icon"><img src="https://i0.wp.com/' . $screenshot . '?w=' . ( $size * 2 ) . '&amp;strip=all" width="' . $size . '" height="' . $size . '"></div>';
				} else {
					return '<div class="default-icon"><span class="dashicons dashicons-admin-appearance"></span></div>';
				}
			case 'bbpress':
			case 'buddypress':
				require_once WPORGPATH . 'extend/plugins-plugins/_plugin-icons.php';
				if ( function_exists( 'wporg_get_plugin_icon' ) ) {
					return wporg_get_plugin_icon( $project->slug, $size );
				} else {
					return '<div class="default-icon"><span class="dashicons dashicons-admin-plugins"></span></div>';
				}
			case 'wp-plugins':
				require_once WPORGPATH . 'extend/plugins-plugins/_plugin-icons.php';
				if ( function_exists( 'wporg_get_plugin_icon' ) ) {
					return wporg_get_plugin_icon( $sub_project->slug, $size );
				} else {
					return '<div class="default-icon"><span class="dashicons dashicons-admin-plugins"></span></div>';
				}
			case 'glotpress':
				return '<div class="icon"><img src="/gp-templates/images/glotpress.png" width="' . $size . '" height="' . $size . '"></div>';
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
			$status->percent_complete   = 0;
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

			if ( $status->all_count ) {
				$status->percent_complete = ceil( $status->current_count / $status->all_count * 100 );
			}
		}

		if ( $calc_sub_projects ) {
			$sub_projects = $this->get_active_sub_projects( $project, true );
			if ( $sub_projects ) {
				foreach ( $sub_projects as $sub_project ) {
					$this->get_project_status( $sub_project, $locale, $set_slug, $status, false );
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

	/**
	 * Retrieves active sub projects with paging.
	 *
	 * This method is horribly inefficient when there exists many sub-projects, as it can't use SQL.
	 *
	 * @param GP_Project $project           The parent project
	 * @param array $args {
	 *	@type int    $per_page Number of items per page. Default 20
	 *	@type int    $page     The page of results to view. Default 1.
	 *	@type string $orderby  The field to order by, id or name. Default id.
	 *	@type string $order    The sorting order, ASC or DESC. Default ASC.
	 *	@type string $search   The search string
	 *	@type string $set_slug The translation set to view.
	 *	@type string $locale   The locale of the translation set to view.
	 * }
	 * @return array List of sub projects.
	 */
	private function get_paged_active_sub_projects( $project, $args = array() ) {
		global $gpdb;

		$defaults = array(
			'per_page' => 20,
			'page'     => 1,
			'orderby'  => 'id',
			'order'    => 'ASC',
			'search'   => false,
			'set_slug' => '',
			'locale'   => '',
		);
		$r = wp_parse_args( $args, $defaults );
		extract( $r, EXTR_SKIP );

		$order = ( 'ASC' === $order ) ? 'ASC' : 'DESC';
		$orderby = ( in_array( $orderby, array( 'id', 'name' ) ) ? $orderby : 'id' );

		$limit_sql = '';
		if ( $per_page ) {
			$limit_sql = $gpdb->prepare( 'LIMIT %d, %d', ( $page - 1 ) * $per_page, $per_page );
		}
		$search_sql = '';
		if ( $search ) {
			$esc_search = '%%' . like_escape( $search ) . '%%';
			$search_sql = $gpdb->prepare( 'AND ( tp.name LIKE %s OR tp.slug LIKE %s )', $esc_search, $esc_search );
		}

		/*
		 * Find all child projects with translation sets that match the current locale/slug.
		 *
		 * 1. We need to fetch all sub-projects of the current project (so, if we're at wp-plugins, we want akismet, debug bar, importers, etc)
		 * 2. Next, we fetch the sub-projects of those sub-projects, that gets us things like Development, Readme, etc.
		 * 3. Next, we fetch the translation sets of both the sub-projects(1), and any sub-sub-projects(2).
		 * Once we have the sets in 3, we can then check to see if there exists any translation sets for the current (locale, slug) (ie. en-au/default)
		 * If not, we can simply filter them out, so that paging only has items returned that actually exist.
		 */
		$translation_sets_table = GP::$translation_set->table;
		$_projects = $project->many( "
			SELECT SQL_CALC_FOUND_ROWS tp.*
				FROM {$project->table} tp
				LEFT JOIN {$project->table} tp_sub ON tp.id = tp_sub.parent_project_id AND tp_sub.active = 1
				LEFT JOIN {$translation_sets_table} sets ON sets.project_id = tp.id AND sets.locale = %s AND sets.slug = %s
				LEFT JOIN {$translation_sets_table} sets_sub ON sets_sub.project_id = tp_sub.id AND sets_sub.locale = %s AND sets_sub.slug = %s
			WHERE
				tp.parent_project_id = %d
				AND tp.active = 1
				AND ( sets.id IS NOT NULL OR sets_sub.id IS NOT NULL )
				$search_sql
			GROUP BY tp.id
			ORDER BY tp.$orderby $order
			$limit_sql
		", $locale, $set_slug, $locale, $set_slug, $project->id  );

		$results = (int) $project->found_rows();
		$pages = (int)ceil( $results / $per_page );

		$projects = array();
		foreach ( $_projects as $project ) {
			$projects[ $project->id ] = $project;
		}

		return array(
			'pages' => compact( 'pages', 'page', 'per_page', 'results' ),
			'projects' => $projects,
		);
	}

	private function _sort_reverse_name_callback( $a, $b ) {
		return - strcasecmp( $a->name, $b->name );
	}

	private function _sort_name_callback( $a, $b ) {
		return strcasecmp( $a->name, $b->name );
	}
}
