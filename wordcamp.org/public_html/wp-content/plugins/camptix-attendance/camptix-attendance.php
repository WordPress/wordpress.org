<?php
/**
 * Plugin Name: CampTix - Attendance UI
 * Description: An addon for CampTix that allows admins and volunteers to track whether a ticket holder attended the event via a mobile UI.
 * Version: 0.1
 * Author: Konstantin Kovshenin
 * Author URI: http://kovshenin.com
 */

add_action( 'camptix_load_addons', 'camptix_attendance_register' );
function camptix_attendance_register() {
	require_once( plugin_dir_path( __FILE__ ) . 'addons/attendance.php' );
}