<?php

namespace Wporg\TranslationEvents;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use GP_Translation;
use GP_Translation_Set;

class Stats_Listener {
	const ACTION_CREATE          = 'create';
	const ACTION_APPROVE         = 'approve';
	const ACTION_REJECT          = 'reject';
	const ACTION_REQUEST_CHANGES = 'request_changes';

	private Active_Events_Cache $active_events_cache;

	public function __construct( Active_Events_Cache $active_events_cache ) {
		$this->active_events_cache = $active_events_cache;
	}

	public function start(): void {
		add_action(
			'gp_translation_created',
			function ( $translation ) {
				$happened_at = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $translation->date_added, new DateTimeZone( 'UTC' ) );
				if ( ! $translation->user_id ) {
					return;
				}
				$this->handle_action( $translation, $translation->user_id, self::ACTION_CREATE, $happened_at );
			},
		);

		add_action(
			'gp_translation_saved',
			function ( $translation, $translation_before ) {
				$user_id     = $translation->user_id_last_modified;
				$status      = $translation->status;
				$happened_at = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $translation->date_modified, new DateTimeZone( 'UTC' ) );

				if ( $translation_before->status === $status ) {
					// Translation hasn't changed status, so there's nothing for us to track.
					return;
				}

				$action = null;
				switch ( $status ) {
					case 'current':
						$action = self::ACTION_APPROVE;
						break;
					case 'rejected':
						$action = self::ACTION_REJECT;
						break;
					case 'changesrequested':
						$action = self::ACTION_REQUEST_CHANGES;
						break;
				}

				if ( $action && $user_id ) {
					$this->handle_action( $translation, $user_id, $action, $happened_at );
				}
			},
			10,
			2,
		);
	}

	private function handle_action( GP_Translation $translation, int $user_id, string $action, DateTimeImmutable $happened_at ): void {
		try {
			// Get events that are active when the action happened, for which the user is registered for.
			$active_events = $this->get_active_events( $happened_at );
			$events        = $this->select_events_user_is_registered_for( $active_events, $user_id );

			// phpcs:ignore Generic.Commenting.DocComment.MissingShort
			/** @var GP_Translation_Set $translation_set Translation set */
			$translation_set = ( new GP_Translation_Set() )->find_one( array( 'id' => $translation->translation_set_id ) );
			global $wpdb, $gp_table_prefix;

			foreach ( $events as $event ) {
				// A given user can only do one action on a specific translation.
				// So we insert ignore, which will keep only the first action.
				// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
				// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->query(
					$wpdb->prepare(
						"insert ignore into {$gp_table_prefix}event_actions (event_id, locale, user_id, original_id, action, happened_at) values (%d, %s, %d, %d, %s, %s)",
						array(
							// Start unique key.
							'event_id'    => $event->id(),
							'locale'      => $translation_set->locale,
							'user_id'     => $user_id,
							'original_id' => $translation->original_id,
							// End unique key.
							'action'      => $action,
							'happened_at' => $happened_at->format( 'Y-m-d H:i:s' ),
						),
					),
				);
				// phpcs:enable
			}
		} catch ( Exception $exception ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( $exception );
		}
	}

	/**
	 * Get active events at a given time.
	 *
	 * @return Event[]
	 * @throws Exception When it fails to get active events.
	 */
	private function get_active_events( DateTimeImmutable $at ): array {
		$events = $this->active_events_cache->get();
		if ( null === $events ) {
			$cache_duration = Active_Events_Cache::CACHE_DURATION;
			$boundary_start = $at;
			$boundary_end   = $at->modify( "+$cache_duration seconds" );

			// Get events for which start is before $boundary_end AND end is after $boundary_start.
			$event_ids = get_posts(
				array(
					'post_type'      => Translation_Events::CPT,
					'post_status'    => 'publish',
					'posts_per_page' => - 1,
					'fields'         => 'ids',
					'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						array(
							'key'     => '_event_start',
							'value'   => $boundary_end->format( 'Y-m-d H:i:s' ),
							'compare' => '<',
							'type'    => 'DATETIME',
						),
						array(
							'key'     => '_event_end',
							'value'   => $boundary_start->format( 'Y-m-d H:i:s' ),
							'compare' => '>',
							'type'    => 'DATETIME',
						),
					),
				),
			);

			$events = array();
			foreach ( $event_ids as $event_id ) {
				$meta     = get_post_meta( $event_id );
				$events[] = Event::from_post_meta( $event_id, $meta );
			}

			$this->active_events_cache->cache( $events );
		}

		// Filter out events that aren't actually active at $at.
		return array_filter(
			$events,
			function ( $event ) use ( $at ) {
				return $event->start() <= $at && $at <= $event->end();
			}
		);
	}

	/**
	 * Filter an array of events so that it only includes events the given user is attending.
	 *
	 * @param Event[] $events Events.
	 *
	 * @return Event[]
	 */
	// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.Found
	private function select_events_user_is_registered_for( array $events, int $user_id ): array {
		$attending_event_ids = get_user_meta( $user_id, Translation_Events::USER_META_KEY_ATTENDING, true );
		return array_filter(
			$events,
			function ( Event $event ) use ( $attending_event_ids ) {
				return isset( $attending_event_ids[ $event->id() ] );
			}
		);
	}
	// phpcs:enable
}
