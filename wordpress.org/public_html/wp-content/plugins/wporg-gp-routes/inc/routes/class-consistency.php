<?php

namespace WordPressdotorg\GlotPress\Routes\Routes;

use GP;
use GP_Locales;
use GP_Route;

/**
 * Consistency Route Class.
 *
 * Provides the route for translate.wordpress.org/consistency.
 */
class Consistency extends GP_Route {

	private $cache_group = 'wporg-translate';

	const PROJECTS = array(
		1      => 'WordPress',
		523    => 'Themes',
		17     => 'Plugins',
		487    => 'Meta',
		281    => 'Apps',
		473698 => 'Patterns',
	);

	/**
	 * The Slack channel ID for some Polyglots Logs.
	 *
	 * @var string
	 */
	private string $polyglots_logs_channel_id = 'C0828HUHGNA';

	/**
	 * Prints a search form and the search results for a consistency view.
	 */
	public function get_search_form( $request = null ) {
		if ( null == $request ) {
			$request = $_REQUEST;
		}
		$consistency_data = $this->prepare_consistency_data( $request );
		$this->tmpl( 'consistency', $consistency_data );
	}

	/**
	 * Retrieves a list of unique translation sets.
	 *
	 * @return array Array of sets.
	 */
	private function get_translation_sets() {
		global $wpdb;

		$sets = wp_cache_get( 'translation-sets', $this->cache_group );

		if ( empty( $sets ) ) {
			$_sets = $wpdb->get_results( "SELECT name, locale, slug FROM {$wpdb->gp_translation_sets} GROUP BY locale, slug ORDER BY name" );

			$sets = array();
			foreach ( $_sets as $set ) {
				$sets[ "{$set->locale}/$set->slug" ] = $set->name;
			}

			wp_cache_set( 'translation-sets', $sets, $this->cache_group, DAY_IN_SECONDS );
		}

		return $sets;
	}

	/**
	 * Performs the search query.
	 *
	 * @param array $args Query arguments.
	 *
	 * @return array The search results.
	 */
	private function query( $args ) {
		global $wpdb;

		if ( $args['case_sensitive'] ) {
			$collation = 'BINARY';
		} else {
			$collation = '';
		}

		$search   = $wpdb->prepare( "= {$collation} %s", $args['search'] );
		$locale   = $wpdb->prepare( '%s', $args['locale'] );
		$set_slug = $wpdb->prepare( '%s', $args['set_slug'] );

		$project_where = '';
		if ( $args['project'] ) {
			$project       = GP::$project->get( $args['project'] );
			$project_where = $wpdb->prepare( 'AND p.path LIKE %s', $wpdb->esc_like( $project->path ) . '/%' );
		}

		$results = $wpdb->get_results(
			"
			SELECT
				p.name AS project_name,
				p.id AS project_id,
				p.path AS project_path,
				p.parent_project_id AS project_parent_id,
				p.active AS active,
				o.singular AS original_singular,
				o.plural AS original_plural,
				o.context AS original_context,
				o.comment AS original_comment,
				o.id AS original_id,
				t.translation_0 AS translation,
				t.date_added AS translation_added,
				t.id AS translation_id
			FROM {$wpdb->gp_originals} AS o
			JOIN
				{$wpdb->gp_projects} AS p ON p.id = o.project_id
			JOIN
				{$wpdb->gp_translations} AS t ON o.id = t.original_id
			JOIN
				{$wpdb->gp_translation_sets} as ts on ts.id = t.translation_set_id
			WHERE
				p.active = 1
				AND t.status = 'current'
				AND o.status = '+active' AND o.singular {$search}
				AND ts.locale = {$locale} AND ts.slug = {$set_slug}
				{$project_where}
			LIMIT 0, 500
		"
		);

		if ( ! $results ) {
			return array();
		}

		// Group by translation and project path. Done in PHP because it's faster as in MySQL.
		usort( $results, array( $this, '_sort_callback' ) );

		return $results;
	}

	public function _sort_callback( $a, $b ) {
		$sort = strnatcmp( $a->translation . $a->original_context, $b->translation . $b->original_context );
		if ( 0 === $sort ) {
			$sort = strnatcmp( $a->project_path, $b->project_path );
		}

		return $sort;
	}

	/**
	 * Bulk update translations.
	 *
	 * @return void
	 */
	public function bulk_update() {
		// Create a new request
		$new_request                          = array();
		$new_request['search']                = $_POST['search'] ?? '';
		$new_request['set']                   = $_POST['set'] ?? '';
		$new_request['search_case_sensitive'] = $_POST['search_case_sensitive'] ?? false;
		$new_request['project']               = $_POST['project'] ?? '';
		$slack_message                        = '';
		$updated_translation_count            = 0;
		$modified_elements                    = array();

		$current_user = wp_get_current_user();

		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'bulk-update-consistency' ) ) {
			$new_request['error_message'][] = 'Your "nonce" is incorrect.';
		}
		if ( ! $this->is_the_user_a_gte( $current_user, $_POST['set'] ) ) {
			$new_request['error_message'][] = "You don't have permission to update translations for this locale.";
		}
		if ( ! isset( $_POST['set'] ) || ! isset( $_POST['search'] ) ) {
			$new_request['error_message'][] = 'The set and/or search items are missing.';
		}
		if ( ! isset( $new_request['error_message'] ) ) {
			$consistency_data = $this->prepare_consistency_data( $new_request );

			foreach ( $consistency_data['results'] as $result ) {
				$exception = '';
				if ( 'wporg-bulk-update-do-not-update' == $_POST['translation'][ $result->translation ] ) {
					continue;
				}
				if ( false !== stripos( strtolower( 'Plugin Name of the plugin' ), strtolower( $result->original_comment ) ) ) {
					$exception = 'A translation cannot be updated because it is the name of the plugin.';
				}
				if ( false !== stripos( strtolower( 'Author of the plugin' ), strtolower( $result->original_comment ) ) ) {
					$exception = 'A translation cannot be updated because it is the author of the plugin.';
				}
				if ( false !== stripos( strtolower( 'Theme Name of the theme' ), strtolower( $result->original_comment ) ) ) {
					$exception = 'A translation cannot be updated because it is the name of the theme.';
				}
				if ( false !== stripos( strtolower( 'Author of the theme' ), strtolower( $result->original_comment ) ) ) {
					$exception = 'A translation cannot be updated because it is the author of the theme.';
				}
				// Get the current translation to update.
				$current_translation = GP::$translation->get( $result->translation_id );
				// Get the translation used to overwrite the current.
				$translation_selected = null;
				foreach ( $consistency_data['results'] as $item ) {
					if ( $item->translation === stripslashes( $_POST['translation'][ $result->translation ] ) ) {
						$translation_selected = GP::$translation->get( $item->translation_id );
						break;
					}
				}

				if ( null === $translation_selected ) {
					$new_request['error_message'][] = 'The selected translation was not found.';
					break;
				}
				// Create a new translation with the selected translation.
				if ( ! $exception ) {
					$new_translation = GP::$translation->create(
						array(
							'original_id'           => $current_translation->original_id,
							'translation_set_id'    => $current_translation->translation_set_id,
							'translation_0'         => $translation_selected->translation_0,
							'translation_1'         => $translation_selected->translation_1,
							'translation_2'         => $translation_selected->translation_2,
							'translation_3'         => $translation_selected->translation_3,
							'translation_4'         => $translation_selected->translation_4,
							'translation_5'         => $translation_selected->translation_5,
							'user_id'               => $current_user->ID,
							'user_id_last_modified' => $current_user->ID,
							'status'                => 'current',
						)
					);
					$current_translation->set_status( 'old' );
					$updated_translation_count++;
				} else {
					$new_translation = $current_translation;
				}

				$modified_elements[] = array(
					'exception'          => $exception,
					'old_translation'    => $current_translation->translation_0,
					'new_translation'    => $translation_selected->translation_0,
					'new_translation_id' => $new_translation->id,
					'project_path'       => $result->project_path,
					'original_id'        => $result->original_id,
				);
			}

			$notice_message = sprintf(
				/* translators: %s: number of translations updated */
				esc_html( _n( '%s translation updated.', '%s translations updated.', $updated_translation_count, 'wporg' ) ),
				number_format_i18n( $updated_translation_count )
			);
			$new_request['notice_message'] = $notice_message;
			$slack_message                 = $notice_message;
			if ( $modified_elements ) {
				$new_request['notice_message'] .= '<ul>';
				$processed_old_translations     = array();
				$total_elements                 = count( $modified_elements );

				foreach ( $modified_elements as $index => $modified_element ) {
					$old_translation = $modified_element['old_translation'];
					if ( ! isset( $processed_old_translations[ $old_translation ] ) ) {
						$processed_old_translations[ $old_translation ] = true;
						$slack_message                                 .= sprintf(
							"\n- Old: %s → New: %s. ",
							esc_html( $old_translation ),
							esc_html( $modified_element['new_translation'] )
						);
					}

					$url = sprintf(
						'https://translate.wordpress.org/projects/%s/%s/?filters[status]=either&filters[original_id]=%d&filters[translation_id]=%d',
						$modified_element['project_path'],
						$new_request['set'],
						$modified_element['original_id'],
						$modified_element['new_translation_id'],
					);
					if ( '' != $modified_element['exception'] ) {
						$new_request['notice_message'] .= sprintf(
							'<li>%s Please, update this translation manually at <a href="%s" target="_blank">%s</a>.</li>',
							esc_html( $modified_element['exception'] ),
							esc_url( $url ),
							esc_url( $url )
						);
						continue;
					}
					$new_request['notice_message'] .= sprintf(
						"<li>Old: \"%s\" → New: \"%s\"\n <ul><li><a href=\"%s\" target=\"_blank\">%s</a> <span class=\"dashicons dashicons-external\"></span></li></ul></li>",
						esc_html( $modified_element['old_translation'] ),
						esc_html( $modified_element['new_translation'] ),
						esc_url( $url ),
						esc_url( $url )
					);
					$is_last_occurrence             = ( $index === $total_elements - 1 ) || ( $modified_elements[ $index + 1 ]['old_translation'] !== $old_translation );
					$link_text                      = $is_last_occurrence ? 'Link.' : 'Link,';
					$slack_message                 .= sprintf(
						'<%s|%s> ',
						esc_url_raw( $url ),
						$link_text
					);
				}

				$new_request['notice_message'] .= '</ul>';
			}
			 if ( $updated_translation_count > 0 ) {
				$this->notify_to_slack( $slack_message, $current_user, $new_request['set'] );
			 }
		}

		$this->get_search_form( $new_request );
	}

	/**
	 * Determine if the user is a GTE for the given set.
	 *
	 * @param null|WP_User $user User.
	 * @param string       $set  Locale set.
	 *
	 * @return bool
	 */
	private function is_the_user_a_gte( $user, $set ) {
		$locale_slug = explode( '/', $set )[0];
		$locale      = GP_Locales::by_slug( $locale_slug );
		if ( ! $locale ) {
			return false;
		}

		$result  = get_sites(
			array(
				'locale'     => $locale->wp_locale,
				'network_id' => WPORG_GLOBAL_NETWORK_ID,
				'path'       => '/',
				'fields'     => 'ids',
				'number'     => '1',
			)
		);
		$site_id = array_shift( $result );
		if ( ! $site_id ) {
			return false;
		}

		$users = get_users(
			array(
				'blog_id'     => $site_id,
				'role'        => 'general_translation_editor',
				'count_total' => false,
			)
		);

		foreach ( $users as $gte_user ) {
			if ( $gte_user->id === $user->id ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Prepares the data for the consistency view and for the bulk update.
	 *
	 * @param array $request Request data.
	 *
	 * @return array
	 */
	private function prepare_consistency_data( array $request ): array {
		$sets = $this->get_translation_sets();

		$search                         = $set = $project = '';
		$search_case_sensitive          = false;
		$notice_message                 = '';
		$error_message                  = '';
		$is_current_user_gte_for_locale = false;

		if ( isset( $request['search'] ) && strlen( $request['search'] ) ) {
			$search = wp_unslash( $request['search'] );
		}

		if ( ! empty( $request['set'] ) ) {
			$set = wp_unslash( $request['set'] );
			if ( ! isset( $sets[ $set ] ) ) {
				$set = '';
			}
		}

		if ( ! empty( $request['notice_message'] ) ) {
			$notice_message = $request['notice_message'];
		}

		if ( ! empty( $request['error_message'] ) ) {
			$error_message = $request['error_message'];
		}

		$is_current_user_gte_for_locale = $this->is_the_user_a_gte( wp_get_current_user(), $request['set'] ?? '' );

		if ( ! empty( $request['search_case_sensitive'] ) ) {
			$search_case_sensitive = true;
		}

		if ( ! empty( $request['project'] ) && isset( self::PROJECTS[ $request['project'] ] ) ) {
			$project = $request['project'];
		}

		$locale        = '';
		$set_slug      = '';
		$locale_is_rtl = false;

		if ( $set ) {
			list( $locale, $set_slug ) = explode( '/', $set );
			$locale_is_rtl             = 'rtl' === GP_Locales::by_slug( $locale )->text_direction;
		}

		$results          = array();
		$performed_search = false;
		if ( strlen( $search ) && $locale && $set_slug ) {
			$performed_search = true;
			$results          = $this->query(
				array(
					'search'         => $search,
					'locale'         => $locale,
					'set_slug'       => $set_slug,
					'case_sensitive' => $search_case_sensitive,
					'project'        => $project,
				)
			);

			$translations               = wp_list_pluck( $results, 'translation', 'translation_id' );
			$translations_unique        = array_values( array_unique( $translations ) );
			$translations_unique_counts = array_count_values( $translations );

			// Sort the unique translations by highest count first.
			arsort( $translations_unique_counts );
		}

		$projects = self::PROJECTS;

		return get_defined_vars();
	}

	/**
	 * Notify the bulk update to Matrix.
	 *
	 * @param string   $message
	 * @param \WP_User $user
	 * @param string   $set
	 *
	 * @return void
	 */
	private function notify_to_slack( string $message, \WP_User $user, string $set ) {
		$message = "<https://profiles.wordpress.org/{$user->user_nicename}|$user->display_name> has made some bulk updates in *{$set}*. " . $message;
		slack_dm( $message, $this->polyglots_logs_channel_id );
	}
}
