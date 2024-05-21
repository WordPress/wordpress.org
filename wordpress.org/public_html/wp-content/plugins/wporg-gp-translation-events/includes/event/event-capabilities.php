<?php

namespace Wporg\TranslationEvents\Event;

use Exception;
use GP;
use WP_User;
use Wporg\TranslationEvents\Attendee\Attendee;
use Wporg\TranslationEvents\Attendee\Attendee_Repository;
use Wporg\TranslationEvents\Stats\Stats_Calculator;

class Event_Capabilities {
	private const MANAGE         = 'manage_translation_events';
	private const CREATE         = 'create_translation_event';
	private const VIEW           = 'view_translation_event';
	private const EDIT           = 'edit_translation_event';
	private const TRASH          = 'trash_translation_event';
	private const DELETE         = 'delete_translation_event';
	private const EDIT_ATTENDEES = 'edit_translation_event_attendees';

	/**
	 * All the capabilities that concern Events.
	 */
	private const CAPS = array(
		self::MANAGE,
		self::CREATE,
		self::VIEW,
		self::EDIT,
		self::TRASH,
		self::DELETE,
		self::EDIT_ATTENDEES,
	);

	private Event_Repository_Interface $event_repository;
	private Attendee_Repository $attendee_repository;
	private Stats_Calculator $stats_calculator;

	public function __construct(
		Event_Repository_Interface $event_repository,
		Attendee_Repository $attendee_repository,
		Stats_Calculator $stats_calculator
	) {
		$this->event_repository    = $event_repository;
		$this->attendee_repository = $attendee_repository;
		$this->stats_calculator    = $stats_calculator;
	}

	/**
	 * This function is automatically called whenever user_can() is called for one the capabilities in self::CAPS.
	 *
	 * @param string  $cap  Requested capability.
	 * @param array   $args Arguments that accompany the requested capability check.
	 * @param WP_User $user User for which we're evaluating the capability.
	 * @return bool
	 */
	private function has_cap( string $cap, array $args, WP_User $user ): bool {
		switch ( $cap ) {
			case self::MANAGE:
				return $this->has_manage( $user );
			case self::CREATE:
				return $this->has_create( $user );
			case self::VIEW:
			case self::EDIT:
			case self::TRASH:
			case self::DELETE:
			case self::EDIT_ATTENDEES:
				if ( ! isset( $args[2] ) || ! is_numeric( $args[2] ) ) {
					return false;
				}
				$event = $this->event_repository->get_event( intval( $args[2] ) );
				if ( ! $event ) {
					return false;
				}

				if ( self::VIEW === $cap ) {
					return $this->has_view( $user, $event );
				}
				if ( self::EDIT === $cap ) {
					return $this->has_edit( $user, $event );
				}
				if ( self::TRASH === $cap ) {
					return $this->has_trash( $user, $event );
				}
				if ( self::DELETE === $cap ) {
					return $this->has_delete( $user, $event );
				}
				if ( self::EDIT_ATTENDEES === $cap ) {
					return $this->has_edit_attendees( $user, $event );
				}
				break;
		}

		return false;
	}

	/**
	 * Evaluate whether a user can manage events.
	 *
	 * @param WP_User $user User for which we're evaluating the capability.
	 * @return bool
	 */
	private function has_manage( WP_User $user ): bool {
		return apply_filters( 'gp_translation_events_can_crud_event', GP::$permission->user_can( $user, 'admin' ) );
	}

	/**
	 * Evaluate whether a user can create events.
	 *
	 * @param WP_User $user User for which we're evaluating the capability.
	 * @return bool
	 */
	private function has_create( WP_User $user ): bool {
		return $this->has_manage( $user );
	}

	/**
	 * Evaluate whether a user can view a specific event.
	 *
	 * @param WP_User $user  User for which we're evaluating the capability.
	 * @param Event   $event Event for which we're evaluating the capability.
	 * @return bool
	 */
	private function has_view( WP_User $user, Event $event ): bool {
		if ( $this->has_manage( $user ) ) {
			return true;
		}

		return 'publish' === $event->status();
	}

	/**
	 * Evaluate whether a user can edit a specific event.
	 *
	 * @param WP_User $user  User for which we're evaluating the capability.
	 * @param Event   $event Event for which we're evaluating the capability.
	 * @return bool
	 */
	private function has_edit( WP_User $user, Event $event ): bool {
		if ( $event->end()->is_in_the_past() ) {
			return false;
		}

		if ( $this->stats_calculator->event_has_stats( $event->id() ) ) {
			return false;
		}

		if ( $event->author_id() === $user->ID ) {
			return true;
		}

		if ( user_can( $user->ID, 'edit_post', $event->id() ) ) {
			return true;
		}

		$attendee = $this->attendee_repository->get_attendee( $event->id(), $user->ID );
		if ( ( $attendee instanceof Attendee ) && $attendee->is_host() ) {
			return true;
		}

		if ( $this->has_manage( $user ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Evaluate whether a user can trash a specific event.
	 *
	 * @param WP_User $user  User for which we're evaluating the capability.
	 * @param Event   $event Event for which we're evaluating the capability.
	 *
	 * @return bool
	 * @throws Exception
	 */
	private function has_trash( WP_User $user, Event $event ): bool {
		if ( $this->has_manage( $user ) ) {
			return true;
		}

		if ( $this->stats_calculator->event_has_stats( $event->id() ) ) {
			return false;
		}

		if ( $event->author_id() === $user->ID ) {
			return true;
		}

		$attendee = $this->attendee_repository->get_attendee( $event->id(), $user->ID );
		if ( ( $attendee instanceof Attendee ) && $attendee->is_host() ) {
			return true;
		}

		return false;
	}

	/**
	 * Evaluate whether a user can permanently delete a specific event.
	 *
	 * @param WP_User $user  User for which we're evaluating the capability.
	 * @param Event   $event Event for which we're evaluating the capability.
	 *
	 * @return bool
	 * @throws Exception
	 */
	private function has_delete( WP_User $user, Event $event ): bool {
		if ( ! $event->is_trashed() ) {
			// The event must be trashed first.
			return false;
		}

		if ( $this->has_manage( $user ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Evaluate whether a user can edit attendees for a specific event.
	 *
	 * @param WP_User $user  User for which we're evaluating the capability.
	 * @param Event   $event Event for which we're evaluating the capability.
	 * @return bool
	 */
	private function has_edit_attendees( WP_User $user, Event $event ): bool {
		if ( $this->has_manage( $user ) ) {
			return true;
		}

		if ( $event->author_id() === $user->ID ) {
			return true;
		}

		$attendee = $this->attendee_repository->get_attendee( $event->id(), $user->ID );
		if ( ( $attendee instanceof Attendee ) && $attendee->is_host() ) {
			return true;
		}

		return false;
	}

	public function register_hooks(): void {
		add_action(
			'user_has_cap',
			function ( $allcaps, $caps, $args, $user ) {
				foreach ( $caps as $cap ) {
					if ( ! in_array( $cap, self::CAPS, true ) ) {
						continue;
					}
					if ( $this->has_cap( $cap, $args, $user ) ) {
						$allcaps[ $cap ] = true;
					}
				}
				return $allcaps;
			},
			10,
			4,
		);
	}
}
