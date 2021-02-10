<?php
namespace WordPressdotorg\BBP_Also_Viewing;
use function WordPressdotorg\SEO\Canonical\get_canonical_url;

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

const TOGGLE_KEY   = 'toggle-also-viewing';
const USER_OPTION  = 'also-viewing';
const NONCE        = 'also-viewing';
const TIMEOUT      = 5 * \MINUTE_IN_SECONDS;
const REFRESH_INT  = 45; // How often the client should check for new viewers.
const CACHE_GROUP  = 'also-viewing';
const CACHE_TIME   = 5 * \MINUTE_IN_SECONDS;

function init() {
	if ( ! allowed_for_user() ) {
		return;
	}

	// Add a UI to enable/disable the feature.
	add_action( 'admin_bar_menu', __NAMESPACE__ . '\admin_bar_menu', 1000 );
	maybe_toggle();

	// If enabled, queue up the JS, and register the API endpoints.
	if ( enabled() ) {
		add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\wp_enqueue_scripts' );

		// Record the user as being on the page.
		add_action( 'wp_head', __NAMESPACE__ . '\user_viewing', 10, 0 );
	}

	// Add some REST API endpoints for JS usage:
	add_action( 'rest_api_init', __NAMESPACE__ . '\rest_api_init' );

	// Maintain it, maybe create the storage table, and setup a cron to cleanup hourly if needed.
	add_action( 'admin_init', __NAMESPACE__ . '\maybe_create_table' );
	add_action( 'also_viewing_cleanup', __NAMESPACE__ . '\cron_cleanup' );
	add_action( 'admin_init', function() {
		if ( ! wp_next_scheduled ( 'also_viewing_cleanup' ) ) {
			wp_schedule_event( time(), 'hourly', 'also_viewing_cleanup', $args );
		}
	} );
}
add_action( 'init', __NAMESPACE__ . '\init' );

/**
 * Whether Also Viewing is enabled for the current user.
 * 
 * @return bool
 */
function enabled() {
	return
		allowed_for_user() &&
		get_user_meta( get_current_user_id(), 'also-viewing', true );
}

/**
 * Whether Also Viewing is able to be activated for the current user.
 * 
 * @return bool
 */
function allowed_for_user() {
	// TODO: Enable for non-moderator? users with more than x replies?
	return is_user_logged_in() && current_user_can( 'moderate' );
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
	wp_set_script_translations( 'also-viewing', 'wporg-bbp-also-viewing' );

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
 * Add a Admin bar entry to enable/disable the Also Viewing tool.
 */
function admin_bar_menu( $wp_admin_bar ) {
	$args = [
		'id'    => 'toggle_translator',
		'title' => '<span class="ab-icon dashicons-welcome-view-site"></span> ' . __( 'Also Viewing', 'wporg-bbp-also-viewing' ),
		'href'  => wp_nonce_url( add_query_arg( TOGGLE_KEY, (int)( ! enabled() ) ), NONCE ),
		'meta'  => [
			'class' => 'toggle-also-viewing',
			'title' => ( enabled() ? __( 'Disable also viewing', 'wporg-bbp-also-viewing' ) : __( 'Enable also viewing', 'wporg-bbp-also-viewing' ) )
		]
	];
	$wp_admin_bar->add_node( $args );

	// Add a descriptive sub-child menu.
	$args['title'] = $args['meta']['title'];
	$args['parent'] = $args['id'];
	$args['id'] .= '-child';
	$wp_admin_bar->add_node( $args );
}

/**
 * Handle the admin bar toggle actions.
 */
function maybe_toggle() {
	if (
		! isset( $_GET[ TOGGLE_KEY ] ) ||
		! isset( $_GET['_wpnonce'] ) ||
		! wp_verify_nonce( $_GET['_wpnonce'], NONCE )
	) {
		return;
	}

	update_user_meta( get_current_user_id(), USER_OPTION, (int) $_GET[ TOGGLE_KEY ] );

	// Cleanup.
	if ( ! enabled() ) {
		clear_viewing( null, get_current_user_id() );
	}

	wp_safe_redirect( remove_query_arg( [ TOGGLE_KEY, '_wpnonce' ] ) );
	die();
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
 * @param string $page The page to get the userse for.
 * 
 * @return array Array of user names + if they're typing.
 */
function get_others_currently_viewing( $page ) {
	$users = get_currently_viewing( $page );
	foreach ( $users as $i => $u ) {
		if ( $u->user_id == get_current_user_id() ) {
			unset( $users[ $i ] );
		}
	}

	return array_values( $users );
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
		wp_cache_delete( $page, CACHE_GROUP );
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
			'permission_callback' => 'is_user_logged_in',
		],
		[
			'methods'  => 'POST',
			'callback' => function( $request ) {
				return user_viewing(
					(string) $request['page'],
					! empty( $request['isTyping'] ) && 'false' !== $request['isTyping']
				);
			},
			'permission_callback' => 'is_user_logged_in',
		],
		[
			'methods'  => 'DELETE',
			'callback' => function( $request ) {
				return clear_viewing( (string) $request['page'] );
			},
			'permission_callback' => 'is_user_logged_in',
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

	if ( get_site_option( 'also-viewing' ) ) {
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

	update_site_option( 'also-viewing', true );
}