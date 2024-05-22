<?php

namespace Wporg\TranslationEvents\Routes\Event;

use GP;
use GP_Locales;
use GP_Original;
use Translation_Entry;
use Wporg\TranslationEvents\Routes\Route;
use Wporg\TranslationEvents\Translation_Events;
use Wporg\TranslationEvents\Event\Event_Repository_Interface;
use Wporg\TranslationEvents\Templates;

/**
 * Displays the event details page.
 */
class Translations_Route extends Route {
	private Event_Repository_Interface $event_repository;

	public function __construct() {
		parent::__construct();
		$this->event_repository = Translation_Events::get_event_repository();
	}

	public function handle( string $event_slug, string $locale, string $status = 'any' ): void {
		$user  = wp_get_current_user();
		$event = get_page_by_path( $event_slug, OBJECT, Translation_Events::CPT );
		if ( ! $event ) {
			$this->die_with_404();
		}
		$event = $this->event_repository->get_event( $event->ID );
		if ( ! $event ) {
			$this->die_with_404();
		}

		global $wpdb, $gp_table_prefix;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		$translation_sets = $wpdb->get_results(
			$wpdb->prepare(
				"
				SELECT DISTINCT ts.id as translation_set_id, ts.name, o.project_id as project_id
				FROM {$gp_table_prefix}event_actions ea
				JOIN {$gp_table_prefix}originals o ON ea.original_id = o.id
				JOIN {$gp_table_prefix}translation_sets ts ON o.project_id = ts.project_id AND ea.locale = ts.locale
				WHERE ea.event_id = %d
				AND ea.locale = %s
				",
				$event->id(),
				$locale
			)
		);
		$projects         = array();
		$translations     = array();
		$locale           = GP_Locales::by_slug( $locale );
		foreach ( $translation_sets as $ts ) {
			$projects[ $ts->translation_set_id ] = GP::$project->get( $ts->project_id );

		}
		Templates::render( 'event-translations-header', get_defined_vars() );

		foreach ( $translation_sets as $ts ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"
					SELECT
						t.*,
						o.*,
						t.id as id,
						o.id as original_id,
						t.status as translation_status,
						o.status as original_status,
						t.date_added as translation_added,
						o.date_added as original_added
					FROM {$gp_table_prefix}event_actions ea
					JOIN {$gp_table_prefix}originals o ON ea.original_id = o.id
					JOIN {$gp_table_prefix}translations t ON t.original_id = ea.original_id
					WHERE ea.event_id = %d
					AND t.translation_set_id = %d
					AND t.user_id = ea.user_id
					AND t.status LIKE %s
					",
					$event->id(),
					$ts->translation_set_id,
					trim( $status, '/' ) === 'waiting' ? 'waiting' : '%'
				)
			);
			// phpcs:enable
			if ( empty( $rows ) ) {
				echo '<style>li#translations_link_', esc_html( $ts->translation_set_id ), ' { display: none; }</style>';
				continue;
			}
			$translations             = array();
			$project                  = $projects[ $ts->translation_set_id ];
			$translation_set          = GP::$translation_set->get( $ts->translation_set_id );
			$filters                  = array();
			$sort                     = array();
			$glossary                 = GP::$glossary->get( $project->id, $locale );
			$page                     = 1;
			$per_page                 = 10000;
			$total_translations_count = 0;
			$text_direction           = 'ltr';
			$locale_slug              = $translation_set->locale;
			$translation_set_slug     = $translation_set->slug;
			$word_count_type          = $locale->word_count_type;
			$can_edit                 = $this->can( 'edit', 'translation-set', $translation_set->id );
			$can_write                = $this->can( 'write', 'project', $project->id );
			$can_approve              = $this->can( 'approve', 'translation-set', $translation_set->id );
			$can_import_current       = $can_approve;
			$can_import_waiting       = $can_approve || $this->can( 'import-waiting', 'translation-set', $translation_set->id );
			$url                      = gp_url_project( $project, gp_url_join( $translation_set->locale, $translation_set->slug ) );
			$set_priority_url         = gp_url( '/originals/%original-id%/set_priority' );
			$discard_warning_url      = gp_url_project( $project, gp_url_join( $translation_set->locale, $translation_set->slug, '-discard-warning' ) );
			$set_status_url           = gp_url_project( $project, gp_url_join( $translation_set->locale, $translation_set->slug, '-set-status' ) );
			$bulk_action              = gp_url_join( $url, '-bulk' );

			$editor_options[ $translation_set->id ] = compact( 'can_approve', 'can_write', 'url', 'discard_warning_url', 'set_priority_url', 'set_status_url', 'word_count_type' );

			foreach ( (array) $rows as $row ) {
				$row->user               = null;
				$row->user_last_modified = null;

				if ( $row->user_id ) {
					$user = get_userdata( $row->user_id );
					if ( $user ) {
						$row->user = (object) array(
							'ID'            => $user->ID,
							'user_login'    => $user->user_login,
							'display_name'  => $user->display_name,
							'user_nicename' => $user->user_nicename,
						);
					}
				}

				if ( $row->user_id_last_modified ) {
					$user = get_userdata( $row->user_id_last_modified );
					if ( $user ) {
						$row->user_last_modified = (object) array(
							'ID'            => $user->ID,
							'user_login'    => $user->user_login,
							'display_name'  => $user->display_name,
							'user_nicename' => $user->user_nicename,
						);
					}
				}

				$row->translations = array();
				for ( $i = 0; $i < $locale->nplurals; $i++ ) {
					$row->translations[] = $row->{'translation_' . $i};
				}
				$row->references         = $row->references ? preg_split( '/\s+/', $row->references, -1, PREG_SPLIT_NO_EMPTY ) : array();
				$row->extracted_comments = $row->comment;
				$row->warnings           = $row->warnings ? maybe_unserialize( $row->warnings ) : null;
				unset( $row->comment );

				// Reduce range by one since we're starting at 0, see GH#516.
				foreach ( range( 0, 5 ) as $i ) {
					$member = "translation_$i";
					unset( $row->$member );
				}

				$row->row_id = $row->original_id . ( $row->id ? "-$row->id" : '' );

				if ( '0' !== $row->priority ) {
					$row->flags = array(
						'gp-priority: ' . GP_Original::$priorities[ $row->priority ],
					);
				}

				$translations[ $row->row_id ] = new Translation_Entry( (array) $row );
			}
			Templates::render( 'translations', get_defined_vars() );
		}

		Templates::render( 'event-translations-footer', get_defined_vars() );
	}
}
