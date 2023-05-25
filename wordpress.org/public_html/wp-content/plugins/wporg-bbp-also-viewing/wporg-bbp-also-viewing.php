<?php
namespace WordPressdotorg\BBP_Also_Viewing;
use function WordPressdotorg\SEO\Canonical\get_canonical_url;
use const MINUTE_IN_SECONDS;

/**
 * Plugin Name: bbPress: Also Viewing
 * Description: Adds an Also Viewing functionality for logged in users.
 * Version:     1.0
 * Author:      WordPress.org
 * Author URI:  https://wordpress.org/
 * License:     GPLv2 or later
 *
 */

/**
 *	This program is free software; you can redistribute it and/or modify
 *	it under the terms of the GNU General Public License, version 2, as
 *	published by the Free Software Foundation.
 *
 *	This program is distributed in the hope that it will be useful,
 *	but WITHOUT ANY WARRANTY; without even the implied warranty of
 *	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *	GNU General Public License for more details.
 *
 *	You should have received a copy of the GNU General Public License
 *	along with this program; if not, see <http://www.gnu.org/licenses/>.
 */

const USER_OPTION  = 'also-viewing';
const TIMEOUT      = 5 * MINUTE_IN_SECONDS;
const REFRESH_INT  = 45; // How often the client should check for new viewers in seconds.
const CACHE_GROUP  = 'also-viewing';
const CACHE_TIME   = 5 * MINUTE_IN_SECONDS;
const REPLY_THRESH = 20; // The number of replies a user must have before the feature can be opt'd into.

function init() {

	// Non-user-specific cron needs to be registered first.
	add_action( 'admin_init', function() {
		if ( ! wp_next_scheduled ( 'also_viewing_cleanup' ) ) {
			wp_schedule_event( time(), 'hourly', 'also_viewing_cleanup' );
		}
	} );
	add_action( 'also_viewing_cleanup', __NAMESPACE__ . '\cron_cleanup' );

	// If the user can't enable it, we can skip registering the panels and such.
	if ( ! allowed_for_user() ) {
		return;
	}

	// Add a UI to enable/disable the feature.
	add_action( 'bbp_user_edit_after', __NAMESPACE__ . '\bbp_user_edit_after' );
	add_action( 'bbp_profile_update', __NAMESPACE__ . '\bbp_profile_update', 10, 1 );

	// If enabled, queue up the JS, and register the API endpoints.
	if ( enabled() ) {
		add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\wp_enqueue_scripts' );

		// Record the user as being on the page.
		add_action( 'wp_head', function() {
			user_viewing(); // Record the user as being on the current page.
		} );
	}

	// Add some REST API endpoints for JS usage:
	add_action( 'rest_api_init', __NAMESPACE__ . '\rest_api_init' );

	// Maintain it, maybe create the storage table
	add_action( 'admin_init', __NAMESPACE__ . '\maybe_create_table' );
}
add_action( 'init', __NAMESPACE__ . '\init' );

/**
 * Whether Also Viewing is enabled for the current user.
 *
 * @param int $user_id The user ID to check for.
 *
 * @return bool
 */
function enabled( $user_id = 0 ) {
	if ( ! $user_id ) {
		$user_id = get_current_user_id();
	}

	return (bool) get_user_meta( $user_id, USER_OPTION, true );
}

/**
 * Whether Also Viewing is able to be activated for the current user.
 *
 * @param int $user_id The user ID to check for.
 *
 * @return bool
 */
function allowed_for_user( $user_id = 0 ) {
	if ( ! $user_id ) {
		$user_id = get_current_user_id();
	}

	if ( ! $user_id ) {
		return false;
	}

	return
		enabled( $user_id ) ||
		user_can( $user_id, 'moderate' ) ||
		bbp_get_user_reply_count( $user_id, true ) >= REPLY_THRESH;
}

/**
 * The current page "slug"/"path" for refering to the current request.
 *
 * This uses the WordPress.org SEO plugin for the canonical url, or falls back to REQUEST_URI.
 *
 * @return string The path for the current page, eg. 'view/no-replies'
 */
function current_page() {
	$page = false;

	// If on WordPress.org, use the canonical url for the page.
	if ( is_callable( '\WordPressdotorg\SEO\Canonical\get_canonical_url' ) ) {
		$page = \WordPressdotorg\SEO\Canonical\get_canonical_url();
	}
	if ( ! $page ) {
		$page = $_SERVER['REQUEST_URI'];
	}

	return sanitize_page_url_for_db( $page );
}

/**
 * Sanitizes a given string/url/path to the format used for uniquely identifying pages.
 *
 * @param string $page The strng/url/path
 *
 * @return string The sanitized $page.
 */
function sanitize_page_url_for_db( $page ) {
	if ( parse_url( $page, PHP_URL_SCHEME ) ) {
		$page = parse_url( $page, PHP_URL_PATH );
	}

	list( $page, ) = explode( '?', $page );

	// Remove the leading path component of the site.
	$home_path = parse_url( home_url(), PHP_URL_PATH );
	if ( $home_path && $home_path === substr( $page, 0, strlen( $home_path ) ) ) {
		$page = substr( $page, strlen( $home_path ) );
	}

	// No leading/trailing slash.
	$page = trim( $page, '/' );

	return $page;
}

/**
 * Load the JS and bootstrap it.
 */
function wp_enqueue_scripts() {
	wp_enqueue_script(
		'also-viewing',
		plugins_url( 'wporg-bbp-also-viewing.js', __FILE__ ),
		[ 'jquery', 'wp-i18n' ],
		filemtime( __DIR__ . '/wporg-bbp-also-viewing.js' )
	);
	wp_set_script_translations( 'also-viewing', 'wporg-forums' );

	wp_localize_script(
		'also-viewing',
		'_alsoViewing',
		[
			'restAPIEndpoint'  => rest_url( 'wporg/v1/currentlyViewing/' ),
			'restAPINonce'     => wp_create_nonce( 'wp_rest' ),
			'currentPage'      => current_page(),
			'currentlyViewing' => get_others_currently_viewing( current_page() ),
			'heartbeatTime'    => TIMEOUT / 2,
			'refreshInterval'  => REFRESH_INT,
		]
	);

}

/**
 * Add an option to the user profile to enable/disable it.
 */
function bbp_user_edit_after() {
	$user_id = bbp_get_displayed_user_id();

	if ( ! allowed_for_user( $user_id ) && ! current_user_can( 'moderate' ) ) {
		return;
	}

	printf(
		'<p>
		<input type="hidden" name="can_update_also_viewing_preference" value="true">
		<input name="also_viewing" id="also_viewing_toggle" type="checkbox" value="yes" %s>
		<label for="also_viewing_toggle">%s</label>
		</p>',
		checked( enabled( $user_id ), true, false ),
		sprintf(
			__( 'Enable the <a href="%s">Also Viewing</a> feature.', 'wporg-forums' ),
			'https://make.wordpress.org/support/handbook/appendix/helpful-tools/#avoiding-overlapping-replies'
		)
	);
}

/**
 * Save the user option to enable/disable.
 */
function bbp_profile_update( $user_id ) {
	// Catch profile updates that should not be able to include the Also Viewing preference, and return early.
	if ( ! isset( $_REQUEST['can_update_also_viewing_preference'] ) ) {
		return;
	}

	$enabled = ! empty( $_REQUEST['also_viewing'] ) && 'yes' === $_REQUEST['also_viewing'];

	update_user_meta( $user_id, USER_OPTION, (int) $enabled );

	// Cleanup.
	if ( ! $enabled ) {
		clear_viewing( null, $user_id );
	}

}

/**
 * Get the list of users who are currently viewing a page.
 *
 * @param string $page The page to get the userse for.
 *
 * @return array Array of user names + if they're typing.
 */
function get_currently_viewing( $page ) {
	global $wpdb;

	$return = [];
	$table  = get_table();
	$page   = sanitize_page_url_for_db( $page );

	if ( ! $page ) {
		return [];
	}

	$users = wp_cache_get( $page, CACHE_GROUP );
	if ( false === $users ) {
		$users = $wpdb->get_results( $wpdb->prepare(
			"SELECT user_id, typing, last_updated
			FROM {$table}
			WHERE page = %s",
			$page
		) );

		wp_cache_set( $page, $users, CACHE_GROUP, CACHE_TIME );
	}

	foreach ( $users as $u ) {
		// Ignore expired records.
		if ( strtotime( $u->last_updated ) < time() - TIMEOUT ) {
			continue;
		}

		$return[] = [
			'who'      => get_user_by( 'id', $u->user_id )->display_name,
			'isTyping' => (bool) $u->typing,
			'user_id'  => (int) $u->user_id,
		];
	}

	return $return;
}

/**
 * Get the list of OTHER users who are currently viewing a page.
 *
 * This anonymizes users so that only mods can see other mods, and plugin support reps can see other reps and committers.
 *
 * @param string $page The page to get the userse for.
 *
 * @return array Array of user names + if they're typing.
 */
function get_others_currently_viewing( $page ) {
	$users = get_currently_viewing( $page );
	foreach ( $users as $i => $u ) {
		if ( $u['user_id'] == get_current_user_id() ) {
			unset( $users[ $i ] );
		}
	}

	// Anonymize the list of users if appropriate.
	// Mods + Admins can see all.
	if ( current_user_can( 'moderate' ) || current_user_can( 'list_users' ) ) {
		return array_values( $users );
	}

	// Anonymize mods for other users.
	foreach ( $users as &$u ) {
		if ( user_can( $u['user_id'], 'moderate' ) ) {
			$u['who']     = '';
			$u['user_id'] = 0;
		}
	}

	// Anonymize users unless they've got similar caps.
	// Plugin support reps can see other reps and committers -for their own plugins-.
	$current_user_objects = get_user_object_slugs( get_current_user_id() );
	foreach ( $users as &$u ) {
		$user_objects = get_user_object_slugs( $u['user_id'] );
		if ( ! array_intersect( $current_user_objects, $user_objects ) ) {
			$u['who']     = '';
			$u['user_id'] = 0;
		}
	}

	return array_values( $users );
}

/**
 * Fetch the list of plugins/themes a user has access to.
 *
 * @param int $user_id The user ID to check for.
 * @return array Array of plugin slugs.
 */
function get_user_object_slugs( $user_id ) {
	if ( ! class_exists( '\WordPressdotorg\Forums\Plugin' ) ) {
		return [];
	}

	$forums = \WordPressdotorg\Forums\Plugin::get_instance();
	if ( ! $forums->plugins || ! $forums->themes ) {
		return [];
	}

	$plugin_slugs = $forums->plugins->get_user_object_slugs( $user_id );
	$theme_slugs  = $forums->themes->get_user_object_slugs( $user_id );

	$matrix = [];
	foreach ( $plugin_slugs as $slug ) {
		$matrix[] = "plugin:{$slug}";
	}
	foreach ( $theme_slugs as $slug ) {
		$matrix[] = "theme:{$slug}";
	}

	return $matrix;
}

/**
 * Mark a user as currently viewing/typing on the current page.
 *
 * @param string $page    The page being viewed. Default current page.
 * @param bool   $typing  If the current user is typing. Default false.
 * @param int    $user_id The user ID who is viewing/typing.
 *
 * @return bool
 */
function user_viewing( $page = false, $typing = false, $user_id = false ) {
	global $wpdb;

	if ( $page ) {
		$page = sanitize_page_url_for_db( $page );
	} else {
		$page = current_page();
	}

	if ( ! $user_id ) {
		$user_id = get_current_user_id();
	}

	if ( ! $page ) {
		return false;
	}

	$table  = get_table();
	$typing = (int) $typing;
	$now    = current_time( 'mysql', true );

	$ret = (bool) $wpdb->query( $wpdb->prepare(
		"INSERT INTO `{$table}` ( `user_id`, `page`, `typing`, `timestamp`, `last_updated` )
		VALUES ( %d, %s, %d, %s, %s )
		ON DUPLICATE KEY UPDATE `typing` = VALUES( `typing` ), `last_updated` = VALUES( `last_updated` )",
		$user_id,
		$page,
		$typing,
		$now,
		$now,
	) );

	// Clear the cache.
	wp_cache_delete( $page, CACHE_GROUP );

	return $ret;
}

/**
 * Mark a user as no longer viewing a page.
 *
 * @param string $page    The page to no longer view. Defaults to clearing all pages.
 * @param int    $user_id The user ID no longer viewing the page. Default current user.
 */
function clear_viewing( $page = null, $user_id = false ) {
	global $wpdb;

	$table = get_table();
	if ( ! $user_id ) {
		$user_id = get_current_user_id();
	}

	if ( is_null( $page ) ) {
		$pages = $wpdb->get_col( $wpdb->prepare(
			"SELECT page FROM `{$table}` WHERE user_id = %d",
			$user_id
		) );
		$wpdb->delete(
			$table,
			[ 'user_id' => $user_id ]
		);
	} else {
		$pages = (array) $page;

		$wpdb->delete(
			$table,
			[
				'user_id' => $user_id,
				'page' => $page
			]
		);
	}

	foreach ( $pages as $p ) {
		wp_cache_delete( $p, CACHE_GROUP );
	}
}

/**
 * Register the REST API urls used by the javascript.
 */
function rest_api_init() {
	register_rest_route( 'wporg/v1', '/currentlyViewing/(?P<page>.+)', [
		[
			'methods'  => 'GET',
			'callback' => function( $request ) {
				return get_others_currently_viewing( (string) $request['page'] );
			},
			'permission_callback' => function() {
				return is_user_logged_in() && enabled();
			},
		],
		[
			'methods'  => 'POST',
			'callback' => function( $request ) {
				return user_viewing(
					(string) $request['page'],
					! empty( $request['isTyping'] ) && 'false' !== $request['isTyping']
				);
			},
			'permission_callback' => function() {
				return is_user_logged_in() && enabled();
			},
		],
		[
			'methods'  => 'DELETE',
			'callback' => function( $request ) {
				return clear_viewing( (string) $request['page'] );
			},
			'permission_callback' => function() {
				return is_user_logged_in() && enabled();
			},
		],
	] );
}

/**
 * Clean out viewing users hourly.
 */
function cron_cleanup() {
	global $wpdb;

	$before = gmdate( 'Y-m-d H:i:s', time() - TIMEOUT );
	$table  = get_table();

	$results = $wpdb->get_results( $wpdb->prepare(
		"SELECT user_id, page FROM `{$table}` WHERE last_updated < %s",
		$before
	) );

	foreach ( $results as $r ) {
		clear_viewing( $r->page, $r->user_id );
	}
}

/**
 * The table name used for storing the state of users.
 *
 * @return string
 */
function get_table() {
	global $wpdb;

	return $wpdb->prefix . 'also_viewing';
}

/**
 * Maybe create the database table used for this plugin.
 *
 * This only runs once per site, ever.
 */
function maybe_create_table() {
	global $wpdb;

	if ( get_option( 'also-viewing' ) ) {
		return;
	}

	$table = get_table();

	$wpdb->query(
		"CREATE TABLE IF NOT EXISTS `{$table}` (
		`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		`user_id` bigint(20) unsigned NOT NULL,
		`page` varchar(255) NOT NULL DEFAULT '',
		`typing` tinyint(1) unsigned NOT NULL DEFAULT 0,
		`timestamp` datetime NOT NULL,
		`last_updated` datetime NOT NULL,
		PRIMARY KEY (`id`),
		UNIQUE KEY `user_id_page` (`user_id`,`page`),
		KEY `page` (`page`)
	  )"
	);

	update_option( 'also-viewing', true );
}
