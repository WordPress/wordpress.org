<?php
namespace WordPressdotorg\Make\Badge_Management;
/**
 * Plugin Name: Profiles.WordPress.org Badge Management for teams.
 * Description: Manage and display badges for WordPress.org profiles.
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

// Add the menu item.
add_action( 'admin_menu', function() {
	add_submenu_page(
		'tools.php',
		'Profile Badges',
		'Profile Badges',
		get_option( 'wporg_profile_badge_required_cap', 'manage_options' ),
		'profile-badges',
		__NAMESPACE__ . '\render'
	);
} );

// Load the plugin.
add_action( 'admin_init', function() {
	require_once __DIR__ . '/admin.php';
	require_once __DIR__ . '/admin-post.php';
} );
