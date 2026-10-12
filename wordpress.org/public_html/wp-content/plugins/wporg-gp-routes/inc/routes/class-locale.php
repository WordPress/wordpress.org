<?php

namespace WordPressdotorg\GlotPress\Routes\Routes;

use GP;
use GP_Locales;
use GP_Route;
use stdClass;
use WordPressdotorg\GlotPress\Rosetta_Roles\Plugin as Rosetta_Roles;
use WordPressdotorg\GlotPress\Routes\Plugin;

/**
 * Locale Route Class.
 *
 * Provides the route for translate.wordpress.org/locale/$locale.
 */
class Locale extends GP_Route {

	private $cache_group = 'wporg-translate';

	/**
	 * Adapter for the rosetta roles plugin.
	 *
	 * @var null|Rosetta_Roles
	 */
	private $roles_adapter = null;

	/**
	 * In-memory cache for active sub projects per instance.
	 *
	 * @var array
	 */
	private $sub_projects_cache = [];

	/**
	 * In-memory cache for user favorites per instance.
	 *
	 * @var array
	 */
	private $user_favorites_cache = [];

	public function __construct() {
		parent::__construct();

		if ( method_exists( Rosetta_Roles::class, 'get_instance' ) ) {
			$this->roles_adapter = Rosetta_Roles::get_instance();
		}
	}

	/**
	 * Prints projects/translation sets of a top level project.
	 *
	 * @param string      $locale_slug  Slug of the locale.
	 * @param string      $set_slug     Slug of the translation set.
	 * @param bool|string $project_path Path of a project.
	 */
	public function get_locale_projects( $locale_slug, $set_slug = 'default', $project_path = false ) {
		$per_page = 24;
		$page = (int) gp_get( 'page', 1 );
		$search = gp_get( 's', '' );
		$filter = gp_get( 'filter', false );
		$without_editors = (bool) gp_get( 'without-editors', false );

		if ( $search && ! is_string( $search ) ) {
			$search = '';
		}
		if ( $filter && ! is_string( $filter ) ) {
			$filter = false;
		}

		$locale = GP_Locales::by_slug( $locale_slug );
		if ( ! $locale ) {
			return $this->die_with_404();
		}

		$variants = $this->get_locale_variants( $locale_slug );
		if ( ! in_array( $set_slug, $variants, true ) ) {
			return $this->die_with_404();
		}

		// Grab the top level projects to show in the menu first, so as to be able to handle the default Waiting / WP tab selection
		$top_level_projects = $this->get_active_top_level_projects();

		// Default to the Waiting or WordPress tabs
		$default_project_tab = 'waiting';
		$user_id = get_current_user_id();
		if (
			! is_user_logged_in() ||
			! $this->roles_adapter || // Rosetta Roles plugin is not enabled
			! (
				$this->roles_adapter->is_global_administrator( $user_id ) || // Not a global admin
				$this->roles_adapter->is_approver_for_locale( $user_id, $locale_slug ) // Doesn't have project-level access either
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
				'without_editors' => $without_editors,
				'set_slug' => $set_slug,
				'locale' => $locale_slug,
			)
		);

		if ( $page > 1 && empty( $paged_sub_projects['projects'] ) ) {
			return $this->die_with_404();
		}

		$data['project']                = $project;
		$data['sub_projects']           = $paged_sub_projects['projects'];
		$data['pages']                  = $paged_sub_projects['pages'];
		$data                           = apply_filters( 'gp_subprojects', $data );
		$paged_sub_projects['projects'] = $data['sub_projects'];
		$paged_sub_projects['pages']    = $data['pages'];

		$sub_projects = $paged_sub_projects['projects'];
		$pages        = $paged_sub_projects['pages'];
		$filter       = $paged_sub_projects['filter'];
		unset( $paged_sub_projects );

		$project_status = $project_icons = array();
		foreach ( $sub_projects as $key => $sub_project ) {
			$project_status[ $sub_project->id ] = $this->get_project_status( $sub_project, $locale_slug, $set_slug );
			$project_icons[ $sub_project->id ] = $this->get_project_icon( $project, $sub_project );
		}

		$contributors_count = Plugin::get_contributors_count();

		$can_create_locale_glossary      = GP::$permission->current_user_can( 'admin' );
		$locale_glossary_translation_set = GP::$translation_set->by_project_id_slug_and_locale( 0, $set_slug, $locale_slug );
		$locale_glossary                 = false;
		if ( $locale_glossary_translation_set ) {
			$locale_glossary = GP::$glossary->by_set_id( $locale_glossary_translation_set->id );
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

		$variants = $this->get_locale_variants( $locale_slug );
		if ( ! in_array( $set_slug, $variants, true ) ) {
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

		$contributors_count = Plugin::get_contributors_count();

		$sub_project_statuses = array();
		$sub_projects         = $this->get_active_sub_projects( $sub_project, true );
		if ( $sub_projects ) {
			foreach ( $sub_projects as $key => $_sub_project ) {
				$sub_project_statuses[ $_sub_project->slug ] = $this->get_project_status(
					$_sub_project,
					$locale_slug,
					$set_slug,
					null,
					false
				);
			}
		}

		$sub_project_slugs = array_keys( $sub_project_statuses );

		$locale_contributors = $this->get_locale_contributors( $sub_project, $locale_slug, $set_slug );

		$can_create_locale_glossary      = GP::$permission->current_user_can( 'admin' );
		$locale_glossary_translation_set = GP::$translation_set->by_project_id_slug_and_locale( 0, $set_slug, $locale_slug );
		$locale_glossary                 = false;
		if ( $locale_glossary_translation_set ) {
			$locale_glossary = GP::$glossary->by_set_id( $locale_glossary_translation_set->id );
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
		// The Waiting tab will have $sub_project's which are not sub-projects of $project
		if ( $sub_project->parent_project_id && $sub_project->parent_project_id !== $project->id ) {
			$parent = GP::$project->get( $sub_project->parent_project_id );
			if ( $parent ) {
				$project = $parent;

				// In the case of Plugins, we may need to go up another level yet.
				if ( ! empty( $parent->parent_project_id ) ) {
					$grand_parent = GP::$project->get( $parent->parent_project_id );
					if ( $grand_parent ) {
						$sub_project = $parent;
						$project     = $grand_parent;
					}
				}
			}
		}

		if ( ! $project ) {
			return '<div class="default-icon"><span class="dashicons dashicons-translation"></span></div>';
		}

		switch ( $project->slug ) {
			case 'wp':
				return '<div class="wordpress-icon"><span class="dashicons dashicons-wordpress-alt"></span></div>';
			case 'meta':
				switch ( $sub_project->slug ) {
					case 'forums':
						return '<div class="default-icon"><span class="dashicons dashicons-format-chat"></span></div>';
					case 'rosetta':
						return '<div class="default-icon"><span class="dashicons dashicons-admin-site"></span></div>';
					case 'plugins':
					case 'plugins-v3':
						return '<div class="default-icon"><span class="dashicons dashicons-admin-plugins"></span></div>';
					case 'themes':
						return '<div class="default-icon"><span class="dashicons dashicons-admin-appearance"></span></div>';
					case 'wordcamp':
						return '<div class="default-icon"><span class="dashicons dashicons-tickets"></span></div>';
					case 'pattern-directory':
						return '<div class="default-icon"><span class="dashicons dashicons-art"></span></div>';
					case 'browsehappy':
						return '<div class="icon"><img src="' . plugins_url( 'templates/images/browsehappy.png', 'wporg-gp-customizations/wporg-gp-customizations.php' ) . '" width="' . $size . '" height="' . $size . '"></div>';
					case 'openverse':
						return '<div class="icon"><img src="' . plugins_url( 'templates/images/openverse.png', 'wporg-gp-customizations/wporg-gp-customizations.php' ) . '" width="' . $size . '" height="' . $size . '"></div>';
					default:
						return '<div class="default-icon"><span class="dashicons dashicons-networking"></span></div>';
				}
			case 'wp-themes':
				$screenshot = gp_get_meta( 'wp-themes', $sub_project->id, 'screenshot' );
				if ( $screenshot ) {
					return '<div class="theme icon"><img src="' . esc_url( 'https://i0.wp.com/' . $screenshot . '?w=' . ( $size * 2 ) . '&strip=all' ) . '" width="' . $size . '" height="' . $size . '"></div>';
				} else {
					return '<div class="default-icon"><span class="dashicons dashicons-admin-appearance"></span></div>';
				}
			case 'wp-plugins':
				$icon = '';

				if ( class_exists( 'WordPressdotorg\Plugin_Directory\Template' ) ) {
					$directory_post_id = gp_get_meta( 'wp-plugins', $sub_project->id, 'directory-post-id' );
					if ( $directory_post_id ) {
						switch_to_blog( WPORG_PLUGIN_DIRECTORY_BLOGID );
						$icon = \WordPressdotorg\Plugin_Directory\Template::get_plugin_icon( $directory_post_id, 'html' );
						restore_current_blog();
					}
				}

				if ( $icon ) {
					return $icon;
				}

				return '<div class="default-icon"><span class="dashicons dashicons-admin-plugins"></span></div>';
			case 'apps':
				return '<div class="default-icon"><span class="dashicons dashicons-smartphone"></span></div>';
			case 'patterns':
				return '<div class="default-icon"><span class="dashicons dashicons-art"></span></div>';
			default:
				return '<div class="default-icon"><span class="dashicons dashicons-translation"></span></div>';
		}
	}

	/**
	 * Retrieves all slugs of translation sets for a locale.
	 *
	 * For performance reasons, this checks the wp/dev project which is the canonical
	 * source for all available and active locales.
	 *
	 * @param string $locale Slug of a GlotPress locale.
	 * @return array List of translation set slugs.
	 */
	private function get_locale_variants( $locale ) {
		global $wpdb;

		return $wpdb->get_col( $wpdb->prepare(
			"SELECT slug FROM {$wpdb->gp_translation_sets} WHERE locale = %s AND project_id = %d",
			$locale,
			2 // wp/dev
		) );
	}

	/**
	 * Retrieves translation editors of a project.
	 *
	 * Also includes cross-locale editors and editors of parent projects.
	 *
	 * @param GP_Project $project     A GlotPress project.
	 * @param string     $locale_slug Slug of the locale.
	 * @return array List of user IDs.
	 */
	private function get_translation_editors( $project, $locale_slug ) {
		global $wpdb;

		$editors = [
			'project'   => [],
			'inherited' => [],
		];

		// Get the translation editors of the project.
		$editors['project'] = (array) $wpdb->get_col( $wpdb->prepare( "
			SELECT
				`user_id`
			FROM {$wpdb->wporg_translation_editors}
			WHERE
				`project_id` = %d
				AND `locale` IN (%s, 'all-locales')
		", $project->id, $locale_slug ) );

		// Get the translation editors of parent projects.
		$parent_ids        = [];
		$parent_project_id = (int) $project->parent_project_id;

		while ( $parent_project_id && ! in_array( $parent_project_id, $parent_ids, true ) ) {
			$parent_project = GP::$project->get( $parent_project_id );

			if ( ! $parent_project ) {
				break;
			}

			$parent_ids[]      = $parent_project_id;
			$parent_project_id = (int) $parent_project->parent_project_id;
		}

		if ( ! empty( $parent_ids ) ) {
			$parent_ids             = array_unique( $parent_ids );
			$parent_id_placeholders = implode( ', ', array_fill( 0, count( $parent_ids ), '%d' ) );

			$inherited_editors_sql = "
				SELECT
					`user_id`
				FROM {$wpdb->wporg_translation_editors}
				WHERE
					`project_id` IN ( {$parent_id_placeholders} )
					AND `locale` IN (%s, 'all-locales')
				";

			$inherited_editors_args = array_merge( $parent_ids, [ $locale_slug ] );
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $inherited_editors_sql uses dynamically generated %d placeholders.
			$inherited_editors = (array) $wpdb->get_col( $wpdb->prepare( $inherited_editors_sql, $inherited_editors_args ) );

			$editors['inherited'] = array_merge( $editors['inherited'], $inherited_editors );
		}

		// Get the translation editors for all projects.
		$editors['inherited'] = array_merge( $editors['inherited'], (array) $wpdb->get_col( $wpdb->prepare( "
			SELECT
				`user_id`
			FROM {$wpdb->wporg_translation_editors}
			WHERE
				`project_id` = '0'
				AND `locale` = %s
		", $locale_slug ) ) );

		return $editors;
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

		$locale_contributors = [
			'editors'      => [
				'project'   => [],
				'inherited' => [],
			],
			'contributors' => [],
		];

		// Get the translation editors of the project.
		$editors = $this->get_translation_editors( $project, $locale_slug );

		// Deduplicate editor IDs before processing to avoid redundant iterations.
		$editors['project']   = array_unique( array_map( 'intval', $editors['project'] ) );
		$editors['inherited'] = array_unique( array_map( 'intval', $editors['inherited'] ) );

		// Collect project IDs: parent project, active sub-projects, and sub-sub-projects (e.g. wp/dev/admin/network).
		$sub_projects = $this->get_active_sub_projects( $project, true );
		$projects     = [ (int) $project->id => $project ];

		foreach ( $sub_projects as $sub_project ) {
			$projects[ (int) $sub_project->id ] = $sub_project;
		}

		$all_project_ids = array_keys( $projects );
		$project_ids_sql = implode( ', ', $all_project_ids );

		// Batch-fetch all matching translation set IDs in a single query.
		$translation_sets_sql = "
			SELECT id
			FROM {$wpdb->gp_translation_sets}
			WHERE
				project_id IN ( {$project_ids_sql} )
				AND slug = %s
				AND locale = %s
		";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $translation_sets_sql contains an IN clause with sanitized project IDs.
		$translation_set_ids = $wpdb->get_col( $wpdb->prepare( $translation_sets_sql, $set_slug, $locale_slug ) );

		// Batch-fetch contributors across all translation sets.
		$contributors = [];
		if ( ! empty( $translation_set_ids ) ) {
			$contributors = $this->get_locale_contributors_by_translation_set( $translation_set_ids );
		}

		// Collect and deduplicate all user IDs to prime user caches in a single request.
		$user_ids = array_merge(
			$editors['project'],
			$editors['inherited'],
			wp_list_pluck( $contributors, 'user_id' )
		);
		$user_ids = array_unique( array_filter( array_map( 'intval', $user_ids ) ) );

		if ( ! empty( $user_ids ) ) {
			// Check which users are already in the object cache using the public Cache API.
			$cached_users      = wp_cache_get_multiple( $user_ids, 'users' );
			$uncached_user_ids = [];

			foreach ( $user_ids as $id ) {
				if ( empty( $cached_users[ $id ] ) ) {
					$uncached_user_ids[] = $id;
				}
			}

			// Batch-load only missing users directly from wp_users without touching usermeta.
			if ( ! empty( $uncached_user_ids ) ) {
				$user_ids_sql = implode( ', ', $uncached_user_ids );
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $user_ids_sql contains sanitized, integer-cast IDs.
				$users = $wpdb->get_results( "SELECT * FROM {$wpdb->users} WHERE ID IN ( {$user_ids_sql} )" );
				foreach ( $users as $user ) {
					update_user_caches( $user );
				}
			}
		}

		$editor_ids = [];

		// Get the names of the translation editors.
		foreach ( [ 'project', 'inherited' ] as $editor_source ) {
			foreach ( $editors[ $editor_source ] as $editor_id ) {
				$user = get_user_by( 'id', $editor_id );
				if ( ! $user ) {
					continue;
				}

				$locale_contributors['editors'][ $editor_source ][ $editor_id ] = (object) array(
					'nicename'     => $user->user_nicename,
					'display_name' => $user->display_name ?: $user->user_nicename,
					'email'        => $user->user_email,
				);

				// Store editor IDs.
				$editor_ids[ (int) $editor_id ] = true;
			}

			uasort(
				$locale_contributors['editors'][ $editor_source ],
				static function ( $a, $b ) {
					return strcasecmp( $a->display_name, $b->display_name );
				}
			);
		}

		unset( $editors );

		// Aggregate contributors and detailed counts.
		foreach ( $contributors as $contributor ) {
			$user_id    = (int) $contributor->user_id;
			$project_id = (int) $contributor->project_id;

			if ( ! isset( $projects[ $project_id ] ) ) {
				$projects[ $project_id ] = GP::$project->get( $project_id );
			}

			$detail = (object) [
				'total_count'   => (int) $contributor->total_count,
				'current_count' => (int) $contributor->current_count,
				'waiting_count' => (int) $contributor->waiting_count,
				'fuzzy_count'   => (int) $contributor->fuzzy_count,
				'project'       => $projects[ $project_id ],
			];

			if ( ! isset( $locale_contributors['contributors'][ $user_id ] ) ) {
				$user = get_user_by( 'id', $user_id );
				if ( ! $user ) {
					continue;
				}

				$locale_contributors['contributors'][ $user_id ] = (object) array(
					'login'         => $user->user_login,
					'nicename'      => $user->user_nicename,
					'display_name'  => $user->display_name ?: $user->user_nicename,
					'email'         => $user->user_email,
					'last_update'   => $contributor->last_update,
					'total_count'   => (int) $contributor->total_count,
					'current_count' => (int) $contributor->current_count,
					'waiting_count' => (int) $contributor->waiting_count,
					'fuzzy_count'   => (int) $contributor->fuzzy_count,
					'detailed'      => [
						$project_id => $detail,
					],
					'is_editor'     => isset( $editor_ids[ $user_id ] ),
				);
			} else {
				$entry = $locale_contributors['contributors'][ $user_id ];
				$entry->last_update   = max( $entry->last_update, $contributor->last_update );
				$entry->total_count   += (int) $contributor->total_count;
				$entry->current_count += (int) $contributor->current_count;
				$entry->waiting_count += (int) $contributor->waiting_count;
				$entry->fuzzy_count   += (int) $contributor->fuzzy_count;
				$entry->detailed[ $project_id ] = $detail;
			}
		}
		unset( $contributors, $editor_ids );

		uasort(
			$locale_contributors['contributors'],
			static function ( $a, $b ) {
				return $b->total_count <=> $a->total_count;
			}
		);

		return $locale_contributors;
	}

	/**
	 * Retrieves contributors of translation sets.
	 *
	 * Aggregates user contributions across multiple translation sets in a single query.
	 *
	 * @param array $set_ids List of translation set IDs.
	 * @return array List of contributor aggregated rows.
	 */
	private function get_locale_contributors_by_translation_set( array $set_ids ) {
		global $wpdb;

		$set_ids = array_filter( array_map( 'intval', $set_ids ) );

		if ( empty( $set_ids ) ) {
			return [];
		}

		$set_ids_sql = implode( ', ', $set_ids );

		$contributors_sql = "
			SELECT
				`t`.`user_id` as `user_id`,
				`o`.`project_id` as `project_id`,
				MAX( `t`.`date_added` ) AS `last_update`,
				COUNT( * ) as `total_count`,
				COUNT( CASE WHEN `t`.`status` = 'current' THEN `t`.`status` END ) AS `current_count`,
				COUNT( CASE WHEN `t`.`status` = 'waiting' THEN `t`.`status` END ) AS `waiting_count`,
				COUNT( CASE WHEN `t`.`status` = 'fuzzy' THEN `t`.`status` END ) AS `fuzzy_count`
			FROM `{$wpdb->gp_translations}` as `t`
			JOIN `{$wpdb->gp_originals}` as `o`
				ON `t`.`original_id` = `o`.`id` AND `o`.`status` = '+active'
			WHERE
				`t`.`translation_set_id` IN ( {$set_ids_sql} )
				AND `t`.`user_id` IS NOT NULL AND `t`.`user_id` != 0
				AND `t`.`status` IN( 'current', 'waiting', 'fuzzy' )
			GROUP BY `t`.`user_id`, `o`.`project_id`
			ORDER BY `t`.`user_id` ASC, `o`.`project_id` ASC
		";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $contributors_sql uses sanitized, integer-cast translation set IDs.
		$contributors = $wpdb->get_results( $contributors_sql );

		return (array) $contributors;
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
			$status->sub_projects_count     = 0;
			$status->waiting_count          = 0;
			$status->current_count          = 0;
			$status->fuzzy_count            = 0;
			$status->untranslated_count     = 0;
			$status->changesrequested_count = 0;
			$status->all_count              = 0;
			$status->percent_complete       = 0;
		}

		$status = apply_filters( 'gp_get_project_status', $status, $project, $locale, $set_slug );
		if ( isset( $status->is_pattern ) && $status->is_pattern ) {
			return $status;
		}

		$set = GP::$translation_set->by_project_id_slug_and_locale(
			$project->id,
			$set_slug,
			$locale
		);

		if ( $set ) {
			$status->sub_projects_count += 1;
			$status->waiting_count          += (int) $set->waiting_count();
			$status->current_count          += (int) $set->current_count();
			$status->fuzzy_count            += (int) $set->fuzzy_count();
			$status->untranslated_count     += (int) $set->untranslated_count();
			$status->changesrequested_count += (int) $set->changesrequested_count();
			$status->all_count              += (int) $set->all_count();

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

		$cache_key = (int) $project->id . ':' . (int) $with_sub_projects;
		if ( isset( $this->sub_projects_cache[ $cache_key ] ) ) {
			return $this->sub_projects_cache[ $cache_key ];
		}

		$projects_sql = "
			SELECT *
			FROM {$wpdb->gp_projects}
			WHERE
				parent_project_id = %d
				AND active = 1
			ORDER BY id ASC
		";
		$_projects = $project->many( $projects_sql, $project->id );

		$projects = [];

		if ( $with_sub_projects && ! empty( $_projects ) ) {
			// Batch fetch all nested child projects in a single query.
			$parent_ids     = wp_list_pluck( $_projects, 'id' );
			$parent_ids_sql = implode( ', ', array_map( 'intval', $parent_ids ) );

			$sub_sub_projects_sql = "
				SELECT *
				FROM {$wpdb->gp_projects}
				WHERE
					parent_project_id IN ( {$parent_ids_sql} )
					AND active = 1
				ORDER BY id ASC
			";
			$sub_sub_projects = $project->many( $sub_sub_projects_sql );

			// Group child projects by parent.
			$children_by_parent = [];
			foreach ( $sub_sub_projects as $child ) {
				$children_by_parent[ (int) $child->parent_project_id ][] = $child;
			}
			unset( $sub_sub_projects );

			foreach ( $_projects as $sub_project ) {
				$sub_id              = (int) $sub_project->id;
				$projects[ $sub_id ] = $sub_project;

				if ( isset( $children_by_parent[ $sub_id ] ) ) {
					foreach ( $children_by_parent[ $sub_id ] as $child ) {
						$projects[ (int) $child->id ] = $child;
					}
				}
			}
			unset( $children_by_parent );
		} else {
			foreach ( $_projects as $sub_project ) {
				$projects[ (int) $sub_project->id ] = $sub_project;
			}
		}
		unset( $_projects );

		$this->sub_projects_cache[ $cache_key ] = $projects;

		return $projects;
	}

	/**
	 * Retrieves active sub projects with paging.
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
			'per_page'        => 20,
			'page'            => 1,
			'search'          => false,
			'set_slug'        => '',
			'locale'          => '',
			'filter'          => false,
			'without_editors' => false,
		);

		[
			'per_page'        => $per_page,
			'page'            => $page,
			'search'          => $search,
			'set_slug'        => $set_slug,
			'locale'          => $locale,
			'filter'          => $filter,
			'without_editors' => $without_editors,
		] = wp_parse_args( $args, $defaults );

		$page     = max( 1, (int) $page );
		$per_page = (int) $per_page;

		$limit_sql = '';
		if ( $per_page ) {
			$limit_sql = $wpdb->prepare( 'LIMIT %d, %d', ( $page - 1 ) * $per_page, $per_page );
		}

		$sql_params = array( $locale, $set_slug );

		/*
		 * Conditions are split by which table they reference. When $stats_where is
		 * non-empty, the stats lookup is wrapped in a derived table so the join is
		 * driven from the (typically small) qualifying stats set; otherwise we LEFT
		 * JOIN stats directly so projects with no status row still appear.
		 */
		$tp_where    = $wpdb->prepare( 'AND tp.parent_project_id = %d', $project->id );
		$stats_where = '';

		$search_sql = '';
		if ( $search ) {
			$search_sql = ' AND ( tp.name LIKE %s OR tp.slug LIKE %s )';
			$search_param = '%' . $wpdb->esc_like( $search ) . '%';
			$sql_params[] = $search_param;
			$sql_params[] = $search_param;
		}

		// Special Waiting Project Tab
		// This removes the parent_project_id restriction and replaces it with all-translation-editor-projects
		if ( 'waiting' == $project->slug && is_user_logged_in() && $this->roles_adapter ) {

			if ( ! $filter ) {
				$filter = 'strings-waiting-and-fuzzy';
			}

			$user_id = get_current_user_id();

			// Global Admin or Locale-specific admin
			$can_approve_for_all = $this->roles_adapter->is_global_administrator( $user_id );

			// Limit to only showing base-level projects.
			$base_level_project_sql = '';
			$base_level_project_ids = wp_list_pluck( $this->get_active_top_level_projects(), 'id' );
			if ( $base_level_project_ids ) {
				$ids = implode( ', ', array_map( 'intval', $base_level_project_ids ) );
				$base_level_project_sql = " AND tp.parent_project_id IN( $ids )";
			}

			// Check to see if they have any special approval permissions.
			$allowed_projects = array();
			$allowed_base_level_projects = array();
			if ( ! $can_approve_for_all && $role = $this->roles_adapter->is_approver_for_locale( $user_id, $locale ) ) {
				$allowed_projects = $this->roles_adapter->get_project_id_access_list( $user_id, $locale );

				$allowed_base_level_projects = array_intersect( $allowed_projects, $base_level_project_ids );
				$allowed_projects = array_diff( $allowed_projects, $base_level_project_ids );

				// Check to see if they can approve for all projects in this locale.
				if ( Rosetta_Roles::LOCALE_MANAGER_ROLE === $role || in_array( 'all', $allowed_projects ) ) {
					$can_approve_for_all = true;
					$allowed_projects = array();
				}
			}

			if ( $can_approve_for_all ) {
				// The current user can approve for all projects, so just grab all with any waiting strings.
				$stats_where = ' AND stats.has_pending = 1';
				$tp_where    = $base_level_project_sql;
			} elseif ( $allowed_projects || $allowed_base_level_projects ) {
				$stats_where = ' AND stats.has_pending = 1';
				$tp_where    = ' AND ( (';

				if ( $allowed_projects ) {
					/*
					 * The current user can approve for a small set of projects.
					 * We only need to check against tp.id and not tp_sub.id in this case as we've overriding the parent_project_id check.
					 */
					$ids = implode( ', ', array_map( 'intval', $allowed_projects ) );
					$tp_where .= "tp.id IN( $ids )" . $base_level_project_sql;
				} else {
					$tp_where .= '0=1';
				}

				$tp_where .= ') OR (';

				if ( $allowed_base_level_projects ) {
					/*
					 * The current user can approve all sub-projects of a base level project.
					 */
					$ids = implode( ', ', array_map( 'intval', $allowed_base_level_projects ) );
					$tp_where .= "tp.parent_project_id IN( $ids )";
				} else {
					$tp_where .= '0=1';
				}

				$tp_where .= ') )';

			} else {
				// The current user can't approve for any locale projects, or is logged out.
				$tp_where = ' AND 0=1';
			}

			// Exclude projects which have an assigned editor.
			if ( $without_editors ) {
				$project_ids_with_editor = $wpdb->get_col( $wpdb->prepare(
					"SELECT DISTINCT(project_id) FROM {$wpdb->wporg_translation_editors} WHERE (locale = %s OR locale = 'all-locales') AND project_id != 0 AND user_id != %d",
					$locale,
					$user_id
				) );
				if ( $project_ids_with_editor ) {
					$project_ids_with_editor = implode( ', ', array_map( 'intval', $project_ids_with_editor ) );
					$tp_where .= " AND tp.id NOT IN( $project_ids_with_editor )";
				}
			}
		}

		$tp_where .= $search_sql;

		$filter_order_by = '';
		$sort_order = 'DESC';
		$filter_name = $filter;
		if ( $filter && '-asc' == substr( $filter, -4 ) ) {
			$sort_order = 'ASC';
			$filter_name = substr( $filter, 0, -4 );
		}
		switch ( $filter_name ) {
			default:
				if ( ! is_user_logged_in() || ! in_array( $project->slug, array( 'waiting', 'wp-themes', 'wp-plugins' ) ) ) {
					if ( 'wp' === $project->slug ) {
						$filter_order_by = 'stats.untranslated > 0 DESC, stats.untranslated DESC, tp.name DESC';
					} else {
						$filter_order_by = 'stats.untranslated > 0 DESC, stats.untranslated DESC, tp.name ASC';
					}
					break;
				}
			case 'special':
				$user_fav_projects = $this->get_user_favorites( $project->slug );

				// Float Favorites to the start, float fully translated to the bottom, order the rest by name
				if ( ! empty( $user_fav_projects ) ) {
					$favorite_path_placeholders = implode( ', ', array_fill( 0, count( $user_fav_projects ), '%s' ) );

					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $favorite_path_placeholders contains only safe %s placeholders.
					$fav_field_sql   = $wpdb->prepare( "FIELD( tp.path, {$favorite_path_placeholders} )", $user_fav_projects );
					$filter_order_by = "{$fav_field_sql} > 0 AND stats.untranslated > 0 DESC, stats.untranslated > 0 DESC, stats.untranslated DESC, tp.name ASC";
				} else {
					$filter_order_by = 'stats.untranslated > 0 DESC, stats.untranslated DESC, tp.name ASC';
				}
				break;

			case 'favorites':
				// Only list favorites
				$user_fav_projects = $this->get_user_favorites( $project->slug );

				if ( ! empty( $user_fav_projects ) ) {
					$favorite_path_placeholders = implode( ', ', array_fill( 0, count( $user_fav_projects ), '%s' ) );

					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $favorite_path_placeholders contains only safe %s placeholders.
					$tp_where .= $wpdb->prepare( " AND tp.path IN( {$favorite_path_placeholders} )", $user_fav_projects );
				} else {
					$tp_where .= ' AND 0=1';
				}
				$filter_order_by = 'stats.untranslated > 0 DESC, tp.name ASC';

				break;

			case 'strings-remaining':
				$stats_where    .= ' AND stats.untranslated > 0';
				$filter_order_by = "stats.untranslated $sort_order, tp.name ASC";
				break;

			case 'strings-waiting-and-fuzzy':
				$stats_where    .= ' AND stats.has_pending = 1';
				$filter_order_by = "tp.path LIKE 'wp/%%' DESC, (stats.fuzzy + stats.waiting) $sort_order, tp.name ASC";
				break;

			case 'strings-waiting-and-fuzzy-by-modified-date':
				$stats_where    .= ' AND stats.has_pending = 1 AND stats.date_modified > "0000-00-00 00:00:00"';
				$filter_order_by = "stats.date_modified $sort_order, tp.name ASC";
				break;

			case 'percent-completed':
				$stats_where    .= ' AND stats.untranslated > 0';
				$filter_order_by = "( stats.current / NULLIF( stats.all, 0 ) ) $sort_order, tp.name ASC";
				break;

			case 'completed':
				$stats_where    .= ' AND stats.all > 0 AND stats.current = stats.all';
				$filter_order_by = "tp.name $sort_order";
				break;

		}

		/*
		 * Find all child projects with translation sets that match the current locale/slug.
		 *
		 * Two dedicated queries are used instead of the deprecated SQL_CALC_FOUND_ROWS:
		 * 1. Data query: Fetches current page records with ORDER BY and LIMIT.
		 * 2. Count query: Fetches the total matching rows using COUNT(*) without ORDER BY
		 *    or LIMIT, avoiding expensive filesorts and full table scans.
		 *
		 * When the filter requires a stats row (waiting/fuzzy/untranslated/etc.), the
		 * stats lookup is wrapped in a derived table that pre-filters by locale +
		 * condition. The optimizer then drives the join from that small set and
		 * eq_refs into tp, instead of scanning every project to LEFT JOIN stats.
		 *
		 * Without a stats filter, fall back to the original LEFT JOIN so projects
		 * with no status row for this locale still appear (ordered last by NULL).
		 */
		if ( $stats_where ) {
			$from_where = "
				FROM (
					SELECT project_id, `all`, `current`, waiting, fuzzy, untranslated, date_modified
					FROM {$wpdb->project_translation_status} stats
					WHERE stats.locale = %s AND stats.locale_slug = %s
						$stats_where
				) stats
				INNER JOIN {$wpdb->gp_projects} tp ON tp.id = stats.project_id
				WHERE tp.active = 1
					$tp_where
			";
		} else {
			$from_where = "
				FROM {$wpdb->gp_projects} tp
					LEFT JOIN {$wpdb->project_translation_status} stats ON stats.project_id = tp.id AND stats.locale = %s AND stats.locale_slug = %s
				WHERE tp.active = 1
					$tp_where
			";
		}

		$sql       = "SELECT tp.* $from_where ORDER BY $filter_order_by $limit_sql";
		$count_sql = "SELECT COUNT(*) $from_where";

		// Fetch records for the current page.
		$_projects = $project->many( $sql, ...$sql_params );

		// Fetch total count for pagination calculations.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $count_sql is dynamically constructed for pagination and safely bound via $sql_params.
		$results = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, ...$sql_params ) );

		$pages = $per_page > 0 ? (int) ceil( $results / $per_page ) : 1;

		$projects = array();
		foreach ( $_projects as $sub_project ) {
			$projects[ $sub_project->id ] = $sub_project;
		}

		return [
			'pages' => [
				'pages'    => $pages,
				'page'     => $page,
				'per_page' => $per_page,
				'results'  => $results,
			],
			'projects' => $projects,
			'filter'   => $filter,
		];
	}

	/**
	 * Retrieves a list of projects which the current user has favorited.
	 *
	 * @param string|false $project_slug Project slug to filter by, or false for all.
	 * @return array List of favorited items, eg [ 'wp-themes/twentyten', 'wp-themes/twentyeleven' ]
	 */
	private function get_user_favorites( $project_slug = false ) {
		if ( ! is_user_logged_in() ) {
			return array();
		}

		$user_id   = get_current_user_id();
		$cache_key = (int) $user_id . ':' . (string) $project_slug;

		if ( isset( $this->user_favorites_cache[ $cache_key ] ) ) {
			return $this->user_favorites_cache[ $cache_key ];
		}

		$theme_favorites  = [];
		$plugin_favorites = [];

		switch ( $project_slug ) {
			default:
				// Fall through to include both Themes and Plugins
			case 'wp-themes':
				// Theme favorites are stored as theme slugs, these map 1:1 to GlotPress projects.
				// Cast to array and filter to support both arrays and legacy/scalar string values.
				$raw_theme_favorites = array_filter( (array) get_user_meta( $user_id, 'theme_favorites', true ) );
				if ( ! empty( $raw_theme_favorites ) ) {
					$theme_favorites = array_map(
						static function ( $slug ) {
							return "wp-themes/$slug";
						},
						$raw_theme_favorites
					);
				}

				if ( 'wp-themes' === $project_slug ) {
					$this->user_favorites_cache[ $cache_key ] = $theme_favorites;
					return $theme_favorites;
				}

			case 'wp-plugins':
				// Plugin favorites are stored as plugin slugs, these map 1:1 to GlotPress projects.
				// Cast to array and filter to support both arrays and legacy/scalar string values.
				$raw_plugin_favorites = array_filter( (array) get_user_meta( $user_id, 'plugin_favorites', true ) );
				if ( ! empty( $raw_plugin_favorites ) ) {
					$plugin_favorites = array_map(
						static function ( $slug ) {
							return "wp-plugins/$slug";
						},
						$raw_plugin_favorites
					);
				}

				if ( 'wp-plugins' === $project_slug ) {
					$this->user_favorites_cache[ $cache_key ] = $plugin_favorites;
					return $plugin_favorites;
				}
		}

		$all_favorites       = array_merge( $theme_favorites, $plugin_favorites );
		$this->user_favorites_cache[ $cache_key ] = $all_favorites;

		// Return all favorites, for uses in things like the Waiting tab
		return $all_favorites;
	}

	/**
	 * Retrieves active top level projects.
	 *
	 * @return array List of top level projects.
	 */
	public function get_active_top_level_projects() {
		$last_updated = get_option( 'wporg_projects_last_updated', 0 );
		$cache_key    = 'active_top_level_projects:' . $last_updated;
		$projects     = wp_cache_get( $cache_key, $this->cache_group );

		if ( false === $projects ) {
			global $wpdb;

			$projects_sql = "
				SELECT *
				FROM {$wpdb->gp_projects}
				WHERE
					parent_project_id IS NULL
					AND active = 1
				ORDER BY FIELD( slug, 'waiting', 'wp', 'wp-themes', 'wp-plugins', 'patterns', 'meta', 'apps' )
			";

			$projects = GP::$project->many( $projects_sql );

			wp_cache_set( $cache_key, $projects, $this->cache_group, DAY_IN_SECONDS );
		}

		return $projects;
	}
}
