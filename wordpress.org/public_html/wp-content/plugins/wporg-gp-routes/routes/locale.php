<?php

// wporg_get_plugin_icon()
if ( file_exists( WPORGPATH . 'extend/plugins-plugins/_plugin-icons.php' ) ) {
	include_once WPORGPATH . 'extend/plugins-plugins/_plugin-icons.php';
}

/**
 * Locale Route Class.
 *
 * Provides the route for translate.wordpress.org/locale/$locale.
 */
class WPorg_GP_Route_Locale extends GP_Route {

	/**
	 * Prints projects/translation sets of a top level project.
	 *
	 * @param string $locale_slug      Slug of the locale.
	 * @param string $set_slug         Slug of the translation set.
	 * @param string $project_path     Path of a project.
	 */
	public function get_locale_projects( $locale_slug, $set_slug = 'default', $project_path = false ) {
		global $wpdb;

		$per_page = 20;
		$page = (int) gp_get( 'page', 1 );
		$search = gp_get( 's', '' );
		$filter = gp_get( 'filter', false );

		$locale = GP_Locales::by_slug( $locale_slug );
		if ( ! $locale ) {
			return $this->die_with_404();
		}

		// Grab the top level projects to show in the menu first, so as to be able to handle the default Waiting / WP tab selection
		$top_level_projects = $this->get_active_top_level_projects();
		usort( $top_level_projects, array( $this, '_sort_reverse_name_callback' ) );

		// Default to the Waiting or WordPress tabs
		$default_project_tab = 'waiting';
		$user_id = get_current_user_id();
		if (
			! is_user_logged_in() ||
			! function_exists( 'wporg_gp_rosetta_roles' ) || // Rosetta Roles plugin is not enabled
			! (
				wporg_gp_rosetta_roles()->is_global_administrator( $user_id ) || // Not a global admin
				wporg_gp_rosetta_roles()->is_approver_for_locale( $user_id, $locale_slug ) // Doesn't have project-level access either
			)
			// Add check to see if there are any waiting translations for this locale?
			) {
			$default_project_tab = 'wp';
		}

		// Filter out the Waiting Tab if the current user cannot validate strings
		if ( 'waiting' != $default_project_tab ) {
			foreach ( $top_level_projects as $i => $project ) {
				if ( 'waiting' == $project->slug ) {
					unset( $top_level_projects[ $i ] );
					break;
				}
			}
		}

		$project_path = $project_path ?: $default_project_tab;

		$project = GP::$project->by_path( $project_path );
		if ( ! $project ) {
			return $this->die_with_404();
		}


		$paged_sub_projects = $this->get_paged_active_sub_projects(
			$project,
			array(
				'page' => $page,
				'per_page' => $per_page,
				'search' => $search,
				'filter' => $filter,
				'set_slug' => $set_slug,
				'locale' => $locale_slug,
			)
		);

		if ( ! $paged_sub_projects ) {
			return $this->die_with_404();
		}

		$sub_projects = $paged_sub_projects['projects'];
		$pages        = $paged_sub_projects['pages'];
		$filter       = $paged_sub_projects['filter'];
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
			$wpdb->get_col( "SELECT id FROM {$wpdb->gp_projects} WHERE parent_project_id IN(" . implode(', ', $project_ids  ) . ")" )
		);

		$contributors_count = wp_cache_get( 'contributors-count', 'wporg-translate' );
		if ( false === $contributors_count ) {
			$contributors_count = array();
		}

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
		$sub_project_slugs = array();
		if ( $sub_projects ) {
			$sub_project_statuses = array();
			foreach ( $sub_projects as $key => $_sub_project ) {
				$sub_project_slugs[] = $_sub_project->slug;
				$status = $this->get_project_status( $_sub_project, $locale_slug, $set_slug, null, false );

				$sub_project_statuses[ $_sub_project->id ] = $status;
			}

			$variants = $this->get_locale_variants( $locale_slug, array_keys( $sub_project_statuses ) );
		} else {
			$variants = $this->get_locale_variants( $locale_slug, array( $sub_project->id ) );
		}

		$locale_contributors = $this->get_locale_contributors( $sub_project, $locale_slug, $set_slug );

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
		// The Waiting tab will have $sub_project's which are not sub-projects of $project
		if ( $sub_project->parent_project_id && $sub_project->parent_project_id !== $project->id ) {
			$project = GP::$project->get( $sub_project->parent_project_id );
			// In the case of Plugins, we may need to go up another level yet
			if ( $project->parent_project_id ) {
				$sub_project = $project;
				$project = GP::$project->get( $sub_project->parent_project_id );
			}
		}

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
					case 'wordcamp':
						return '<div class="default-icon"><span class="dashicons dashicons-tickets"></span></div>';
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
				if ( function_exists( 'wporg_get_plugin_icon' ) ) {
					return wporg_get_plugin_icon( $project->slug, $size );
				} else {
					return '<div class="default-icon"><span class="dashicons dashicons-admin-plugins"></span></div>';
				}
			case 'wp-plugins':
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
		global $wpdb;

		$project_ids = implode( ',', $project_ids );
		$slugs = $wpdb->get_col( $wpdb->prepare( "
			SELECT DISTINCT slug
			FROM {$wpdb->gp_translation_sets}
			WHERE
				project_id IN( $project_ids )
				AND locale = %s
		", $locale ) );

		return $slugs;
	}

	/**
	 * Retrieves contributors of a project.
	 *
	 * @param GP_Project $project     A GlotPress project.
	 * @param string     $locale_slug Slug of the locale.
	 * @param string     $set_slug    Slug of the translation set.
	 * @return array Contributors.
	 */
	private function get_locale_contributors( $project, $locale_slug, $set_slug ) {
		global $wpdb;

		$locale_contributors = array(
			'editors'      => array(),
			'contributors' => array(),
		);

		// Get the translation editors of the project.
		$editors = $wpdb->get_col( $wpdb->prepare( "
			SELECT
				`user_id`
			FROM {$wpdb->wporg_translation_editors}
			WHERE
				`project_id` = %d
				AND `locale` = %s
		", $project->id, $locale_slug ) );

		// Get the names of the translation editors.
		foreach ( $editors as $editor_id ) {
			$user = get_user_by( 'id', $editor_id );
			if ( ! $user ) {
				continue;
			}

			$locale_contributors['editors'][ $editor_id ] = (object) array(
				'nicename'     => $user->user_nicename,
				'display_name' => $this->_encode( $user->display_name ),
				'email'        => $user->user_email,
			);
		}
		unset( $editors );

		// Get the contributors of the project.
		$contributors = array();

		// In case the project has a translation set, like /wp-themes/twentysixteen.
		$translation_set = GP::$translation_set->by_project_id_slug_and_locale( $project->id, $set_slug, $locale_slug );
		if ( $translation_set ) {
			$contributors = array_merge(
				$contributors,
				$this->get_locale_contributors_by_translation_set( $translation_set )
			);
		}

		// Check if the project has sub-projects, like /wp-plugins/wordpress-importer.
		$sub_projects = $wpdb->get_col( $wpdb->prepare( "
			SELECT id
			FROM {$wpdb->gp_projects}
			WHERE parent_project_id = %d
		", $project->id ) );

		foreach ( $sub_projects as $sub_project ) {
			$translation_set = GP::$translation_set->by_project_id_slug_and_locale( $sub_project, $set_slug, $locale_slug );
			if ( ! $translation_set ) {
				continue;
			}

			$contributors = array_merge(
				$contributors,
				$this->get_locale_contributors_by_translation_set( $translation_set )
			);
		}

		// Get the names of the contributors.
		foreach ( $contributors as $contributor_id ) {
			if ( isset( $locale_contributors['editors'][ $contributor_id ] ) ) {
				continue;
			}

			if ( isset( $locale_contributors['contributors'][ $contributor_id ] ) ) {
				continue;
			}

			$user = get_user_by( 'id', $contributor_id );
			if ( ! $user ) {
				continue;
			}

			$locale_contributors['contributors'][ $contributor_id ] = (object) array(
				'nicename'     => $user->user_nicename,
				'display_name' => $this->_encode( $user->display_name ),
				'email'        => $user->user_email,
			);
		}
		unset( $contributors );

		return $locale_contributors;
	}

	/**
	 * Retrieves contributors of a translation set.
	 *
	 * @param GP_Translation_Set $translation_set A translation set.
	 * @return array List of user IDs.
	 */
	private function get_locale_contributors_by_translation_set( $translation_set ) {
		global $wpdb;

		$user_ids = $wpdb->get_col( $wpdb->prepare( "
			SELECT DISTINCT( `user_id` )
			FROM `{$wpdb->gp_translations}`
			WHERE
				`translation_set_id` = %d
				AND `user_id` IS NOT NULL AND `user_id` != 0
				AND `status` <> 'rejected'
				AND `date_modified` > %s
		", $translation_set->id, date( 'Y-m-d', time() - YEAR_IN_SECONDS ) ) );

		return $user_ids;
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
				/*
				 * > 50% round down, so that a project with all strings except 1 translated shows 99%, instead of 100%.
				 * < 50% round up, so that a project with just a few strings shows 1%, instead of 0%.
				 */
				$percent_complete = ( $status->current_count / $status->all_count * 100 );
				$status->percent_complete = ( $percent_complete > 50 ) ? floor( $percent_complete ) : ceil( $percent_complete );
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
		global $wpdb;

		$_projects = $project->many( "
			SELECT *
			FROM {$wpdb->gp_projects}
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
					FROM {$wpdb->gp_projects}
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
		global $wpdb;

		$defaults = array(
			'per_page' => 20,
			'page'     => 1,
			'search'   => false,
			'set_slug' => '',
			'locale'   => '',
			'filter'   => false,
		);
		$r = wp_parse_args( $args, $defaults );
		extract( $r, EXTR_SKIP );

		$limit_sql = '';
		if ( $per_page ) {
			$limit_sql = $wpdb->prepare( 'LIMIT %d, %d', ( $page - 1 ) * $per_page, $per_page );
		}

		$parent_project_sql = $wpdb->prepare( 'AND tp.parent_project_id = %d', $project->id );

		$search_sql = '';
		if ( $search ) {
			$esc_search = '%%' . like_escape( $search ) . '%%';
			$search_sql = $wpdb->prepare( 'AND ( tp.name LIKE %s OR tp.slug LIKE %s )', $esc_search, $esc_search );
		}

		// Special Waiting Project Tab
		// This removes the parent_project_id restriction and replaces it with all-translation-editer-projects
		if ( 'waiting' == $project->slug && is_user_logged_in() && function_exists( 'wporg_gp_rosetta_roles' ) ) {

			if ( ! $filter ) {
				$filter = 'strings-waiting-and-fuzzy';
			}

			$user_id = get_current_user_id();

			// Global Admin or Locale-specific admin
			$can_approve_for_all = wporg_gp_rosetta_roles()->is_global_administrator( $user_id );

			// Check to see if they have any special approval permissions
			$allowed_projects = array();
			if ( ! $can_approve_for_all && wporg_gp_rosetta_roles()->is_approver_for_locale( $user_id, $locale ) ) {
				$allowed_projects = wporg_gp_rosetta_roles()->get_project_id_access_list( $user_id, $locale, true );

				// Check to see if they can approve for all projects in this locale.
				if ( in_array( 'all', $allowed_projects ) ) {
					$can_approve_for_all = true;
					$allowed_projects = array();
				}
			}

			$parent_project_sql = '';
			if ( $can_approve_for_all ) {
				// The current user can approve for all projects, so just grab all with any waiting strings.
				$parent_project_sql = 'AND ( stats.waiting > 0 OR stats.fuzzy > 0 )';

			} elseif ( $allowed_projects ) {
				// The current user can approve for a small set of projects.
				// We only need to check against tp.id and not tp_sub.id in this case as we've overriding the parent_project_id check
				$ids = implode( ', ', array_map( 'intval', $allowed_projects ) );
				$parent_project_sql = "AND tp.id IN( $ids ) AND stats.waiting > 0";

			} else {
				// The current user can't approve for any locale projects, or is logged out.
				$parent_project_sql = 'AND 0=1';

			}

			// Limit to only showing base-level projects
			$parent_project_sql .= " AND tp.parent_project_id IN( (SELECT id FROM {$wpdb->gp_projects} WHERE parent_project_id IS NULL AND active = 1) )";

		}

		$filter_order_by = $filter_where = '';
		$sort_order = 'DESC';
		$filter_name = $filter;
		if ( $filter && '-asc' == substr( $filter, -4 ) ) {
			$sort_order = 'ASC';
			$filter_name = substr( $filter, 0, -4 );
		}
		switch ( $filter_name ) {
			default:
			case 'special':
				// Float favorites to the start, but only if they have untranslated strings
				$user_fav_projects = array_map( 'esc_sql', $this->get_user_favorites( $project->slug ) );

				// Float Favorites to the start, float fully translated to the bottom, order the rest by name
				if ( $user_fav_projects ) {
					$filter_order_by = 'FIELD( tp.path, "' . implode( '", "', $user_fav_projects ) . '" ) > 0 AND stats.untranslated > 0 DESC, stats.untranslated > 0 DESC, stats.untranslated DESC, tp.name ASC';
				} else {
					$filter_order_by = 'stats.untranslated > 0 DESC, stats.untranslated DESC, tp.name ASC';
				}
				break;

			case 'favorites':
				// Only list favorites
				$user_fav_projects = array_map( 'esc_sql', $this->get_user_favorites( $project->slug ) );

				if ( $user_fav_projects ) {
					$filter_where = 'AND tp.path IN( "' . implode( '", "', $user_fav_projects ) . '" )';
				} else {
					$filter_where = 'AND 0=1';
				}
				$filter_order_by = 'stats.untranslated > 0 DESC, tp.name ASC';

				break;

			case 'strings-remaining':
				$filter_where = 'AND stats.untranslated > 0';
				$filter_order_by = "stats.untranslated $sort_order, tp.name ASC";
				break;

			case 'strings-waiting-and-fuzzy':
				$filter_where = 'AND (stats.waiting > 0 OR stats.fuzzy > 0 )';
				$filter_order_by = "tp.path LIKE 'wp/%%' AND (stats.fuzzy + stats.waiting) > 0 DESC, (stats.fuzzy + stats.waiting) $sort_order, tp.name ASC";
				break;

			case 'percent-completed':
				$filter_where = 'AND stats.untranslated > 0';
				$filter_order_by = "( stats.current / stats.all ) $sort_order, tp.name ASC";
				break;
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
		$_projects = $project->many( "
			SELECT SQL_CALC_FOUND_ROWS tp.*
			FROM {$wpdb->gp_projects} tp
				LEFT JOIN {$wpdb->project_translation_status} stats ON stats.project_id = tp.id AND stats.locale = %s AND stats.locale_slug = %s
			WHERE
				tp.active = 1
				$parent_project_sql
				$search_sql
				$filter_where
			GROUP BY tp.id
			ORDER BY $filter_order_by
			$limit_sql
		", $locale, $set_slug );

		$results = (int) $project->found_rows();
		$pages = (int) ceil( $results / $per_page );

		$projects = array();
		foreach ( $_projects as $project ) {
			$projects[ $project->id ] = $project;
		}

		return array(
			'pages' => compact( 'pages', 'page', 'per_page', 'results' ),
			'projects' => $projects,
			'filter' => $filter,
		);
	}

	/**
	 * Retrieves a list of projects which the current user has favorited.
	 *
	 * @return array List of favorited items, eg [ 'wp-themes/twentyten', 'wp-themes/twentyeleven' ]
	 */
	function get_user_favorites( $project_slug = false ) {
		global $wpdb;

		if ( ! is_user_logged_in() ) {
			return array();
		}

		$user_id = get_current_user_id();

		switch ( $project_slug ) {
			default:
				// Fall through to include both Themes and Plugins
			case 'wp-themes':
				// Theme favorites are stored as theme slugs, these map 1:1 to GlotPress projects
				$theme_favorites = array_map( function( $slug ) {
					return "wp-themes/$slug";
				}, (array) get_user_meta( $user_id, 'theme_favorites', true ) );

				if ( 'wp-themes' === $project_slug ) {
					return $theme_favorites;
				}

			case 'wp-plugins':
				// Plugin favorites are stored as topic ID's
				$plugin_fav_ids = array_keys( (array) get_user_meta( $user_id, PLUGINS_TABLE_PREFIX . 'plugin_favorite', true ) );
				$plugin_fav_slugs = array();
				if ( $plugin_fav_ids ) {
					$plugin_fav_ids = implode( ',', array_map( 'intval', $plugin_fav_ids ) );
					$plugin_fav_slugs = $wpdb->get_col( "SELECT topic_slug FROM " . PLUGINS_TABLE_PREFIX . "topics WHERE topic_id IN( $plugin_fav_ids )" );
				}

				$plugin_favorites = array_map( function( $slug ) {
					return "wp-plugins/$slug";
				}, $plugin_fav_slugs );

				if ( 'wp-plugins' === $project_slug ) {
					return $plugin_favorites;
				}
		}

		// Return all favorites, for uses in things like the Waiting tab
		return array_merge( $theme_favorites, $plugin_favorites );
	}

	/**
	 * Retrieves active top level projects.
	 *
	 * @return array List of top level projects.
	 */
	public function get_active_top_level_projects() {
		global $wpdb;

		return GP::$project->many( "
			SELECT *
			FROM {$wpdb->gp_projects}
			WHERE
				parent_project_id IS NULL
				AND active = 1
			ORDER BY name ASC
		" );
	}

	private function _sort_reverse_name_callback( $a, $b ) {
		// The Waiting project should always be first.
		if ( $a->slug == 'waiting' ) {
			return -1;
		}
		return - strcasecmp( $a->name, $b->name );
	}

	private function _sort_name_callback( $a, $b ) {
		return strcasecmp( $a->name, $b->name );
	}

	private function _encode( $raw ) {
		$raw = mb_convert_encoding( $raw, 'UTF-8', 'ASCII, JIS, UTF-8, Windows-1252, ISO-8859-1' );
		return ent2ncr( htmlspecialchars_decode( htmlentities( $raw, ENT_NOQUOTES, 'UTF-8' ), ENT_NOQUOTES ) );
	}
}
