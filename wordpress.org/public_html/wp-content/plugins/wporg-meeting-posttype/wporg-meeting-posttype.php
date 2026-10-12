<?php
/**
 * Plugin Name: WPORG Make Homepage Meeting Post Type
 * Description: The meeting post type, its occurrence logic, and the [meeting_time] shortcode for https://make.wordpress.org/meetings. The admin screens, calendar block, REST API, and feeds are in the Meeting Calendar plugin.
 * Version:     2.0
 * License:     GPLv2 or later
 * Author:      WordPress.org
 * Author URI:  https://wordpress.org/
 * Text Domain: wporg-meeting-calendar
 *
 * @package WordPressdotorg\Meeting_Calendar
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-meeting-post-type.php';

Meeting_Post_Type::init();

/**
 * Flush the rewrite rules once if they still hold the /meetings/ archive that 1.x registered.
 *
 * Stored rules are only rebuilt on a flush, so the stale rule would otherwise keep sending
 * /meetings/ to a meeting query instead of to the page there.
 */
function wporg_meeting_posttype_flush_archive_rules(): void {
	$rules = get_option( 'rewrite_rules' );
	if ( is_array( $rules ) && in_array( 'index.php?post_type=meeting', $rules, true ) ) {
		flush_rewrite_rules( false );
	}
}
add_action( 'wp_loaded', 'wporg_meeting_posttype_flush_archive_rules' );
