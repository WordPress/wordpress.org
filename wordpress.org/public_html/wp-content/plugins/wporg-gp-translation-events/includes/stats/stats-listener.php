<?php

namespace Wporg\TranslationEvents\Stats;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use GP_Translation;
use GP_Translation_Set;
use Wporg\TranslationEvents\Event\Event_Repository_Interface;

class Stats_Listener {
	const ACTION_CREATE          = 'create';
	const ACTION_APPROVE         = 'approve';
	const ACTION_REJECT          = 'reject';
	const ACTION_REQUEST_CHANGES = 'request_changes';

	private Event_Repository_Interface $event_repository;

	public function __construct( Event_Repository_Interface $event_repository ) {
		$this->event_repository = $event_repository;
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
			// Get events that are active now, for which the user is registered for.
			$events = $this->event_repository->get_current_events_for_user( $user_id )->events;

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
}
