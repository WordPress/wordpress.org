<?php
/**
 * Seed demo Translation Events for local development.
 *
 * Creates a few users and one event per state the events pages distinguish
 * (active, upcoming, past, and draft), with hosts and attendees on the active
 * one, so the event list, details, and attendee pages have content.
 *
 * Idempotent: users are looked up by login and events by slug, so it can be
 * re-run at any time.
 *
 * Usage:
 *   wp eval-file wp-content/env-bin/seed-events.php
 *
 * No strict_types declaration: eval-file evaluates the file inline, where a
 * declare() cannot be the first statement.
 *
 * @package translate-env
 */

namespace WordPressdotorg\Translate\Env;

use DateTimeImmutable;
use DateTimeZone;
use Wporg\TranslationEvents\Attendee\Attendee;
use Wporg\TranslationEvents\Event\Event;
use Wporg\TranslationEvents\Event\Event_End_Date;
use Wporg\TranslationEvents\Event\Event_Start_Date;
use Wporg\TranslationEvents\Translation_Events;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( Translation_Events::class ) ) {
	\WP_CLI::error( 'The Translation Events plugin is not loaded.' );
}

/**
 * Find or create a subscriber with the given login.
 *
 * @param string $login        User login.
 * @param string $display_name Display name.
 *
 * @return int The user's ID.
 */
function ensure_user( string $login, string $display_name ): int {
	$user = get_user_by( 'login', $login );
	if ( $user ) {
		return (int) $user->ID;
	}

	$user_id = wp_insert_user(
		array(
			'user_login'   => $login,
			'user_pass'    => wp_generate_password(),
			'user_email'   => "{$login}@example.com",
			'display_name' => $display_name,
			'role'         => 'subscriber',
		)
	);
	if ( is_wp_error( $user_id ) ) {
		\WP_CLI::error( "Could not create user '{$login}': " . $user_id->get_error_message() );
	}

	\WP_CLI::log( "Created user '{$login}'." );
	return (int) $user_id;
}

/**
 * Find or create an event.
 *
 * @param string            $title           Event title; its slug identifies the event on re-runs.
 * @param DateTimeImmutable $start           Start, in UTC.
 * @param DateTimeImmutable $end             End, in UTC.
 * @param string            $status          Post status: publish or draft.
 * @param string            $attendance_mode One of onsite, remote, or hybrid.
 *
 * @return int The event's post ID.
 */
function ensure_event( string $title, DateTimeImmutable $start, DateTimeImmutable $end, string $status, string $attendance_mode ): int {
	// Events sit under a year parent, so look them up by slug rather than path.
	$existing = get_posts(
		array(
			'post_type'   => Translation_Events::CPT,
			'post_status' => 'any',
			'name'        => sanitize_title( $title ),
			'numberposts' => 1,
		)
	);
	if ( $existing ) {
		return (int) $existing[0]->ID;
	}

	$utc   = new DateTimeZone( 'UTC' );
	$event = new Event(
		get_current_user_id(),
		new Event_Start_Date( $start->format( 'Y-m-d H:i:s' ), $utc ),
		new Event_End_Date( $end->format( 'Y-m-d H:i:s' ), $utc ),
		new DateTimeZone( 'Europe/Lisbon' ),
		$status,
		$title,
		"Description of {$title}, with a \"quote\", an & ampersand, and -- dashes.",
		null,
		$attendance_mode
	);
	Translation_Events::get_event_repository()->insert_event( $event );

	\WP_CLI::log( "Created event '{$title}' ({$status}, {$attendance_mode})." );
	return $event->id();
}

/**
 * Add a user to an event unless they already attend it.
 *
 * @param int  $event_id  Event ID.
 * @param int  $user_id   User ID.
 * @param bool $is_host   Whether the user hosts the event.
 * @param bool $is_remote Whether the user attends remotely.
 *
 * @return void
 */
function ensure_attendee( int $event_id, int $user_id, bool $is_host = false, bool $is_remote = false ): void {
	$attendees = Translation_Events::get_attendee_repository();
	if ( $attendees->get_attendee_for_event_for_user( $event_id, $user_id ) ) {
		return;
	}
	$attendees->insert_attendee( new Attendee( $event_id, $user_id, $is_host, false, array(), $is_remote ) );
}

$admin = get_user_by( 'login', 'admin' );
if ( ! $admin ) {
	\WP_CLI::error( "The 'admin' user does not exist." );
}
wp_set_current_user( $admin->ID );

$host      = ensure_user( 'hostuser', 'Host User' );
$attendee1 = ensure_user( 'attendee1', 'Remote Attendee' );
$attendee2 = ensure_user( 'attendee2', 'Onsite Attendee' );

$now = Translation_Events::now();

$active = ensure_event( 'Active Hybrid Event', $now->modify( '-1 hour' ), $now->modify( '+3 hours' ), 'publish', 'hybrid' );
ensure_attendee( $active, $host, true );
ensure_attendee( $active, $attendee1, false, true );
ensure_attendee( $active, $attendee2 );

ensure_event( 'Upcoming Onsite Event', $now->modify( '+1 week' ), $now->modify( '+1 week +2 hours' ), 'publish', 'onsite' );
ensure_event( 'Past Remote Event', $now->modify( '-1 month' ), $now->modify( '-1 month +2 hours' ), 'publish', 'remote' );
ensure_event( 'Draft Event', $now->modify( '+2 days' ), $now->modify( '+2 days +2 hours' ), 'draft', 'onsite' );

\WP_CLI::log( 'Done. Events: ' . home_url( '/events/' ) );
