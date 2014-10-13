<?php
/**
 * Plugin Name: CampTix - Admin Flags
 * Description: An addon for CampTix that allows admins to configure and set private per-attendee flags.
 * Version: 0.1
 * Author: Konstantin Kovshenin
 * Author URI: http://kovshenin.com
 */

add_action( 'camptix_load_addons', 'camptix_admin_flags_register' );
function camptix_admin_flags_register() {
	require_once( plugin_dir_path( __FILE__ ) . 'addons/admin-flags.php' );
}