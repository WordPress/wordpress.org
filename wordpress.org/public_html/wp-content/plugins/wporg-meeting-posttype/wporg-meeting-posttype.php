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

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-meeting-post-type.php';

Meeting_Post_Type::init();
