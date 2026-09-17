<?php
/**
 * Plugin Name: WordPress.org Profiles (local stub)
 * Description: Keeps profile badge and activity writes inside the local
 *              environment. `assign_badge()` and friends live in
 *              mu-plugins/pub/profile-helpers.php, which every environment
 *              mounts, so Badge_Automation and the directories register their
 *              hooks locally. WordPressdotorg\Profiles\queue() then dispatches
 *              synchronously for anything that is not `production`, and api()
 *              only redirects the URL for `staging` — so on a local install an
 *              awarded badge is a real POST to profiles.wordpress.org.
 *
 *              Badge associations are persisted when the environment provides
 *              the bpmain_wporg_groups tables, and otherwise acknowledged.
 *
 * @package wporg-env
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Env\Profiles;

const HANDLER_URL = 'https://profiles.wordpress.org/wp-admin/admin-ajax.php';

/**
 * Whether this environment has the badge association tables.
 *
 * @global \wpdb $wpdb
 *
 * @return bool
 */
function has_group_tables(): bool {
	global $wpdb;

	static $exists = null;

	if ( null === $exists ) {
		$exists = (bool) $wpdb->get_var( "SHOW TABLES LIKE 'bpmain_wporg_groups'" );
	}

	return $exists;
}

/**
 * Find or create a badge group.
 *
 * @global \wpdb $wpdb
 *
 * @param string $slug Badge slug.
 * @return int The group ID, or 0 when it could not be created.
 */
function ensure_group( string $slug ): int {
	global $wpdb;

	$group_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM bpmain_wporg_groups WHERE slug = %s', $slug ) );
	if ( $group_id ) {
		return $group_id;
	}

	$wpdb->insert(
		'bpmain_wporg_groups',
		array(
			'slug' => $slug,
			'name' => ucwords( str_replace( '-', ' ', $slug ) ),
		),
		array( '%s', '%s' )
	);

	return (int) $wpdb->insert_id;
}

/**
 * Apply a badge association locally.
 *
 * @global \wpdb $wpdb
 *
 * @param array $body The request body Profiles would have received.
 * @return void
 */
function handle_association( array $body ): void {
	global $wpdb;

	if ( 'generic-badge' !== ( $body['source'] ?? '' ) || ! has_group_tables() ) {
		return;
	}

	$group_id = ensure_group( sanitize_title( (string) ( $body['badge'] ?? '' ) ) );
	if ( ! $group_id ) {
		return;
	}

	foreach ( (array) ( $body['users'] ?? array() ) as $user_id ) {
		$row = array(
			'group_id' => $group_id,
			'user_id'  => (int) $user_id,
		);

		if ( 'remove' === ( $body['command'] ?? '' ) ) {
			$wpdb->delete( 'bpmain_wporg_groups_members', $row, array( '%d', '%d' ) );
		} else {
			$wpdb->replace(
				'bpmain_wporg_groups_members',
				$row + array(
					'is_confirmed'  => 1,
					'is_banned'     => 0,
					'date_modified' => current_time( 'mysql', true ),
				),
				array( '%d', '%d', '%d', '%d', '%s' )
			);
		}

		if ( function_exists( 'WordPressdotorg\Profiles\clear_user_badges_cache' ) ) {
			\WordPressdotorg\Profiles\clear_user_badges_cache( (int) $user_id );
		}
	}
}

/**
 * Answer Profiles handler requests locally instead of calling production.
 *
 * @param mixed  $preempt Existing short-circuit response.
 * @param array  $args    Request arguments.
 * @param string $url     Request URL.
 * @return mixed A canned response for the Profiles handler, otherwise $preempt.
 */
function short_circuit_request( $preempt, array $args, string $url ) {
	if ( HANDLER_URL !== $url ) {
		return $preempt;
	}

	$body = (array) ( $args['body'] ?? array() );

	if ( 'wporg_handle_association' === ( $body['action'] ?? '' ) ) {
		handle_association( $body );
	}

	return array(
		'response' => array(
			'code'    => 200,
			'message' => 'OK',
		),
		'body'     => '1',
		'headers'  => array(),
		'cookies'  => array(),
	);
}
add_filter( 'pre_http_request', __NAMESPACE__ . '\short_circuit_request', 10, 3 );
