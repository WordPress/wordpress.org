<?php

namespace Wporg\TranslationEvents\Event;

use DateTime;
use DateTimeZone;
use Exception;
use WP_Error;
use Wporg\TranslationEvents\Notifications\Notifications_Schedule;
use Wporg\TranslationEvents\Stats\Stats_Calculator;
use Wporg\TranslationEvents\Urls;

class Event_Form_Handler {
	private Event_Repository_Interface $event_repository;
	private Notifications_Schedule $notifications_schedule;

	public function __construct( Event_Repository_Interface $event_repository ) {
		$this->event_repository       = $event_repository;
		$this->notifications_schedule = new Notifications_Schedule( $this->event_repository );
	}

	public function handle( array $form_data ): void {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( esc_html__( 'The user must be logged in.', 'gp-translation-events' ), 403 );
		}

		$action = isset( $form_data['form_name'] ) ? sanitize_text_field( wp_unslash( $form_data['form_name'] ) ) : '';
		if ( ! in_array( $action, array( 'create_event', 'edit_event', 'trash_event' ), true ) ) {
			wp_send_json_error( esc_html__( 'Invalid form name.', 'gp-translation-events' ), 403 );
		}

		$event_id = isset( $form_data['event_id'] ) ? intval( sanitize_text_field( wp_unslash( $form_data['event_id'] ) ) ) : 0;
		$event    = null;

		if ( 'create_event' === $action && ( ! current_user_can( 'create_translation_event' ) ) ) {
			wp_send_json_error( esc_html__( 'You do not have permissions to create events.', 'gp-translation-events' ), 403 );
		}
		if ( 'edit_event' === $action && ( ! current_user_can( 'edit_translation_event', $event_id ) ) ) {
			wp_send_json_error( esc_html__( 'You do not have permissions to edit this event.', 'gp-translation-events' ), 403 );
		}
		if ( 'trash_event' === $action && ( ! current_user_can( 'trash_translation_event', $event_id ) ) ) {
			wp_send_json_error( esc_html__( 'You do not have permissions to delete this event.', 'gp-translation-events' ), 403 );
		}

		$is_nonce_valid = false;
		$nonce_name     = '_event_nonce';
		if ( isset( $form_data[ $nonce_name ] ) ) {
			$nonce_value = sanitize_text_field( wp_unslash( $form_data[ $nonce_name ] ) );
			if ( wp_verify_nonce( $nonce_value, $nonce_name ) ) {
				$is_nonce_valid = true;
			}
		}
		if ( ! $is_nonce_valid ) {
			wp_send_json_error( esc_html__( 'Nonce verification failed.', 'gp-translation-events' ), 403 );
		}

		$response_message = '';
		if ( $event_id ) {
			$event = $this->event_repository->get_event( $event_id );
		}

		if ( 'trash_event' === $action ) {
			// Trash event.
			if ( ! $event ) {
				wp_send_json_error( esc_html__( 'Event does not exist.', 'gp-translation-events' ), 404 );
			}

			$stats_calculator = new Stats_Calculator();
			try {
				$event_stats = $stats_calculator->for_event( $event->id() );
			} catch ( Exception $e ) {
				wp_send_json_error( esc_html__( 'Failed to calculate event stats.', 'gp-translation-events' ), 500 );
			}
			if ( ! empty( $event_stats->rows() ) ) {
				wp_send_json_error( esc_html__( 'Event has stats so it cannot be deleted.', 'gp-translation-events' ), 422 );
			}

			if ( false === $this->event_repository->trash_event( $event ) ) {
				$response_message = esc_html__( 'Failed to delete event.', 'gp-translation-events' );
				$event_status     = $event->status();
			} else {
				$response_message = esc_html__( 'Event deleted successfully.', 'gp-translation-events' );
				$event_status     = 'trashed';
				$this->notifications_schedule->delete_scheduled_emails( $event_id );
			}
		} else {
			// Create or update event.

			try {
				if ( 'edit_event' === $action && $event ) {
					$form_data['event_timezone'] = $event->timezone()->getName();
				}
				$new_event = $this->parse_form_data( $form_data );
			} catch ( InvalidTimeZone $e ) {
				wp_send_json_error( esc_html__( 'Invalid time zone.', 'gp-translation-events' ), 422 );
				return;
			} catch ( InvalidStart $e ) {
				wp_send_json_error( esc_html__( 'Invalid start date.', 'gp-translation-events' ), 422 );
				return;
			} catch ( InvalidEnd $e ) {
				wp_send_json_error( esc_html__( 'Invalid end date.', 'gp-translation-events' ), 422 );
				return;
			} catch ( InvalidStatus $e ) {
				wp_send_json_error( esc_html__( 'Invalid status.', 'gp-translation-events' ), 422 );
				return;
			}

			if ( empty( $new_event->title() ) ) {
				wp_send_json_error( esc_html__( 'Invalid title.', 'gp-translation-events' ), 422 );
				return;
			}

			// This is a list of slugs that are not allowed, as they conflict with the event URLs.
			$invalid_slugs = array( 'new', 'edit', 'attend', 'my-events' );
			if ( in_array( sanitize_title( $new_event->title() ), $invalid_slugs, true ) ) {
				wp_send_json_error( esc_html__( 'Invalid slug.', 'gp-translation-events' ), 422 );
			}

			if ( 'create_event' === $action ) {
				$result = $this->event_repository->insert_event( $new_event );
				if ( $result instanceof WP_Error ) {
					wp_send_json_error( esc_html__( 'Failed to create event.', 'gp-translation-events' ), 422 );
					return;
				}
				$response_message = esc_html__( 'Event created successfully.', 'gp-translation-events' );
				$this->notifications_schedule->schedule_emails( $result );
			}
			if ( 'edit_event' === $action ) {
				$event = $this->event_repository->get_event( $new_event->id() );
				if ( ! $event ) {
					wp_send_json_error( esc_html__( 'Event does not exist.', 'gp-translation-events' ), 404 );
				}

				try {
					$event->set_status( $new_event->status() );
					if ( current_user_can( 'edit_translation_event_title', $event->id() ) ) {
						$event->set_title( $new_event->title() );
					}
					if ( current_user_can( 'edit_translation_event_description', $event->id() ) ) {
						$event->set_description( $new_event->description() );
					}
					if ( current_user_can( 'edit_translation_event_timezone', $event->id() ) ) {
						$event->set_timezone( $new_event->timezone() );
					}

					$event->validate_times( $new_event->start(), $new_event->end() );

					if ( current_user_can( 'edit_translation_event_start', $event->id() ) ) {
						$event->set_start( $new_event->start() );
					}
					if ( current_user_can( 'edit_translation_event_end', $event->id() ) ) {
						$event->set_end( $new_event->end() );
					}
				} catch ( Exception $e ) {
					wp_send_json_error( esc_html__( 'Failed to update event.', 'gp-translation-events' ), 422 );
					return;
				}

				$result = $this->event_repository->update_event( $event );
				if ( $result instanceof WP_Error ) {
					wp_send_json_error( esc_html__( 'Failed to update event.', 'gp-translation-events' ), 422 );
					return;
				}
				$response_message = esc_html__( 'Event updated successfully', 'gp-translation-events' );
				$this->notifications_schedule->schedule_emails( $result );
			}

			$event_id     = $new_event->id();
			$event_status = $new_event->status();
		}

		wp_send_json_success(
			array(
				'message'       => $response_message,
				'eventId'       => $event_id,
				'eventStatus'   => $event_status,
				'eventUrl'      => Urls::event_details_absolute( $event_id ),
				'eventEditUrl'  => Urls::event_edit( $event_id ),
				'eventTrashUrl' => Urls::my_events(), // The URL the user is redirected to after trashing.
			)
		);
	}

	// PHPCS erroneously thinks there should be only two throw tags.
	// phpcs:disable Squiz.Commenting.FunctionCommentThrowTag.WrongNumber
	/**
	 * @throws InvalidStart
	 * @throws InvalidEnd
	 * @throws InvalidTimeZone
	 * @throws InvalidStatus
	 */
	// phpcs:enable
	private function parse_form_data( array $data ): Event {
		$event_id = isset( $data['event_id'] ) ? sanitize_text_field( wp_unslash( $data['event_id'] ) ) : 0;
		$title    = isset( $data['event_title'] ) ? sanitize_text_field( wp_unslash( $data['event_title'] ) ) : '';

		// This will be sanitized by sanitize_post which is called in wp_insert_post.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$description    = isset( $data['event_description'] ) ? force_balance_tags( wp_unslash( $data['event_description'] ) ) : '';
		$event_start    = isset( $data['event_start'] ) ? sanitize_text_field( wp_unslash( $data['event_start'] ) ) : '';
		$event_end      = isset( $data['event_end'] ) ? sanitize_text_field( wp_unslash( $data['event_end'] ) ) : '';
		$event_timezone = isset( $data['event_timezone'] ) ? sanitize_text_field( wp_unslash( $data['event_timezone'] ) ) : '';

		$event_status = '';
		if ( isset( $data['event_form_action'] ) && in_array( $data['event_form_action'], array( 'draft', 'publish', 'trash' ), true ) ) {
			$event_status = sanitize_text_field( wp_unslash( $data['event_form_action'] ) );
		}

		try {
			$timezone = new DateTimeZone( $event_timezone );
		} catch ( Exception $e ) {
			throw new InvalidTimeZone();
		}

		try {
			$start = new Event_Start_Date( $event_start, $timezone );
		} catch ( Exception $e ) {
			throw new InvalidStart();
		}

		try {
			$end = new Event_End_Date( $event_end, $timezone );
		} catch ( Exception $e ) {
			throw new InvalidEnd();
		}

		$event = new Event(
			get_current_user_id(),
			$start->setTimezone( new DateTimeZone( 'UTC' ) ),
			$end->setTimezone( new DateTimeZone( 'UTC' ) ),
			$timezone,
			$event_status,
			$title,
			$description,
		);
		$event->set_id( intval( $event_id ) );
		return $event;
	}
}
