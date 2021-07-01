<?php

namespace WordPressdotorg\GlotPress\Routes\Routes;

use GP;
use GP_Route;
use GP_Locales;

/**
 * Stats Route Class.
 *
 * Provides the route for translate.wordpress.org/stats.
 */
class Stats extends GP_Route {

	public function get_stats_overview() {
		global $wpdb;

		$projects = array(
			'patterns/core' => false,
			'meta/wordpress-org' => false,
			'meta/rosetta' => false,
			'meta/browsehappy' => false,
			'meta/themes' => false,
			'meta/plugins-v3' => false,
			'meta/forums' => false,
			'apps/android' => false,
			'apps/ios' => false,
			'waiting' => false,
			'wp-themes' => false,
			'wp-plugins' => false,
		);

		// I'm sure there's somewhere to fetch these from statically defined
		$wp_project = GP::$project->by_path( 'wp' );
		$previous_wp_version = WP_CORE_STABLE_BRANCH - 0.1;
		foreach ( GP::$project->find_many( array( 'parent_project_id' => $wp_project->id, 'active' => 1 ), 'name ASC' ) as $wp_sub_project ) {
			if ( 'dev' === $wp_sub_project->slug || version_compare( $previous_wp_version, (float) $wp_sub_project->name, '<=' ) ) {
				// Prefix the WordPress projects...
				$wp_sub_project->name = $wp_project->name . ' ' . $wp_sub_project->name;
				$projects  = array_merge( array( $wp_sub_project->path => $wp_sub_project ), $projects );
			}
		}

		// Load the projects for each display item
		array_walk( $projects, function( &$project, $project_slug ) {
			if ( ! $project ) {
				$project = GP::$project->by_path( $project_slug );
			}
		} );

		$all_project_paths_sql = '"' . implode( '", "', array_keys( $projects ) ) . '"';
		$sql = "SELECT
				path, locale, locale_slug,
				(100 * stats.current/stats.all) as percent_complete,
				stats.waiting+stats.fuzzy as waiting_strings,
				stats.date_modified as last_modified
			FROM {$wpdb->project_translation_status} stats
				LEFT JOIN {$wpdb->gp_projects} p ON stats.project_id = p.id
			WHERE
				p.path IN ( $all_project_paths_sql )
				AND p.active = 1";

		$rows = $wpdb->get_results( $sql );

		// Split out into $[Locale][Project] = %
		$translation_locale_statuses = array();
		foreach ( $rows as $set ) {
			$locale_key = $set->locale;
			if ( 'default' !== $set->locale_slug ) {
				$locale_key = $set->locale . '/' . $set->locale_slug;
			}

			$gp_locale = GP_Locales::by_slug( $locale_key );
			if ( ! $gp_locale || ! $gp_locale->wp_locale ) {
				continue;
			}

			/*
			 * > 50% round down, so that a project with all strings except 1 translated shows 99%, instead of 100%.
			 * < 50% round up, so that a project with just a few strings shows 1%, instead of 0%.
			 */
			$percent_complete = (float) $set->percent_complete;
			$percent_complete = ( $percent_complete > 50 ) ? floor( $percent_complete ) : ceil( $percent_complete );
			$translation_locale_statuses[ $locale_key ][ $set->path ] = $percent_complete;

			// Don't include these in the 'waiting' section, override the value to be waiting strings w/ Date Modified.
			if ( 'wp-plugins' === $set->path || 'wp-themes' === $set->path ) {
				$translation_locale_statuses[ $locale_key ][ $set->path ] = $set->waiting_strings;
				$projects[ $set->path ]->cache_last_updated = $set->last_modified;
				continue;
			}

			if ( ! isset( $translation_locale_statuses[ $locale_key ]['waiting'] ) ) {
				$translation_locale_statuses[ $locale_key ]['waiting'] = 0;
			}
			$translation_locale_statuses[ $locale_key ]['waiting'] += (int) $set->waiting_strings;
		}
		unset( $rows, $locale_key, $set );

		// Calculate a list of [Locale] = % subtotals
		$translation_locale_complete = array();
		foreach ( $translation_locale_statuses as $locale => $sets ) {
			unset( $sets['waiting'], $sets['wp-plugins'], $sets['wp-themes'] );
			$sets_count = count( $sets );
			if ( $sets_count ) {
				$translation_locale_complete[ $locale ] = round( array_sum( $sets ) / $sets_count, 3 );
			} else {
				$translation_locale_complete[ $locale ] = 0;
			}
		}
		unset( $locale, $sets );

		// Sort by translation completeness, least number of waiting strings, and locale slug.
		uksort( $translation_locale_complete, function ( $a, $b ) use ( $translation_locale_complete, $translation_locale_statuses ) {
			if ( $translation_locale_complete[ $a ] < $translation_locale_complete[ $b ] ) {
				return 1;
			} elseif ( $translation_locale_complete[ $a ] == $translation_locale_complete[ $b ] ) {
				if ( $translation_locale_statuses[ $a ]['waiting'] != $translation_locale_statuses[ $b ]['waiting'] ) {
					return strnatcmp( $translation_locale_statuses[ $a ]['waiting'], $translation_locale_statuses[ $b ]['waiting'] );
				} else {
					return strnatcmp( $a, $b );
				}
			} else {
				return -1;
			}
		} );

		$this->tmpl( 'stats-overview', get_defined_vars() );
	}

	public function get_stats_plugin_theme_overview( $locale, $locale_slug, $view = false ) {
		global $wpdb;
		if ( ! $locale || ! $locale_slug || ! $view ) {
			wp_redirect( '/stats', 301 );
			exit;
		}

		if ( 'default' !== $locale_slug ) {
			$gp_locale = GP_Locales::by_slug( $locale . '/' . $locale_slug );
		} else {
			$gp_locale = GP_Locales::by_slug( $locale );
		}

		if ( ! $gp_locale ) {
			wp_redirect( '/stats', 301 );
			exit;
		}

		$locale_path = $locale . '/' . $locale_slug;

		$items = array();
		if ( 'plugins' == $view ) {
			// Fetch top ~500 plugins..
			$items = get_transient( __METHOD__ . '_plugin_items' );
			if ( false === $items ) {
				$items = array();
				foreach ( range( 1, 2 ) as $page ) { // 2 pages x 250 items per page.
					$api = wp_safe_remote_get( 'https://api.wordpress.org/plugins/info/1.2/?action=query_plugins&request[per_page]=250&request[page]=' . $page );
					foreach ( json_decode( wp_remote_retrieve_body( $api ) )->plugins as $plugin ) {
						$items[ $plugin->slug ] = (object) [
							'installs' => $plugin->active_installs,
						];
					}
				}
				set_transient( __METHOD__ . '_plugin_items', $items, $items ? DAY_IN_SECONDS : 5 * MINUTE_IN_SECONDS );
			}
		} elseif ( 'themes' == $view && defined( 'WPORG_THEME_DIRECTORY_BLOGID' ) ) {
			$items = get_transient( __METHOD__ . '_theme_items' );
			if ( false === $items ) {
				// The Themes API API isn't playing nice.. Easiest way..
				switch_to_blog( WPORG_THEME_DIRECTORY_BLOGID );
				$items = $wpdb->get_results(
					"SELECT p.post_name as slug, pm.meta_value as installs
					FROM {$wpdb->posts} p
					LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_active_installs'
					WHERE p.post_type = 'repopackage' AND p.post_status = 'publish'
					ORDER BY pm.meta_value+0 DESC, p.post_date
					LIMIT 500",
					OBJECT_K
				);
				restore_current_blog();
				foreach ( $items as $slug => $details ) {
					unset( $items[$slug]->slug );
				}
				set_transient( __METHOD__ . '_theme_items', $items, $items ? DAY_IN_SECONDS : 5 * MINUTE_IN_SECONDS );
			}
		} else {
			wp_safe_redirect( '/stats' );
			die();
		}

		$project_ids = [];
		foreach ( $items as $slug => $details ) {
			$items[$slug]->project = GP::$project->by_path( 'wp-' . $view . '/' . $slug );
			if ( ! $items[$slug]->project || ! $items[$slug]->project->active ) {
				// Not all top-all-time plugins/themes have translation projects.
				unset( $items[ $slug ] );
				continue;
			}

			if ( 'plugins' == $view ) {
				// For plugins, we're ignoring the Readme projects as they're not immediately useful.
				// The display will use the "Code Stable" if present, else "Code Development"
				$code_project =
					GP::$project->by_path( 'wp-' . $view . '/' . $slug . '/stable' ) ?:
					GP::$project->by_path( 'wp-' . $view . '/' . $slug . '/dev' );

				// No code project? This happens when the plugin isn't translatable. The readme projects still exist though.
				if ( ! $code_project ) {
					unset( $items[ $slug ] );
					continue;
				}

				$project_ids[ $code_project->id ] = $slug;
			} else {
				$project_ids[ $items[$slug]->project->id ] = $slug;
			}
		}

		$sql_project_ids = implode( ', ', array_map( 'intval', array_keys( $project_ids ) ) );
		$stats = $wpdb->get_results( $wpdb->prepare(
			"SELECT `project_id`, `all`, `current`, `waiting`, `fuzzy`, `untranslated`
				FROM {$wpdb->project_translation_status}
				WHERE project_id IN ($sql_project_ids)
				AND locale = %s AND locale_slug = %s",
			$locale, $locale_slug
		) );

		// Link Projects & Stats
		foreach ( $stats as $row ) {
			$items[ $project_ids[ $row->project_id ] ]->stats = $row;
		}

		// Final sanity check that we have data to display.
		foreach ( $items as $slug => $details ) {
			if (
				! isset( $details->stats ) || // Didn't find any cached data for a project
				! $details->stats->all // Project has no strings, not useful for this interface
			) {
				unset( $items[ $slug ] );
			}
		}

		$this->tmpl( 'stats-plugin-themes-overview', compact( 'locale_path', 'view', 'gp_locale', 'items' ) );
	}
}
