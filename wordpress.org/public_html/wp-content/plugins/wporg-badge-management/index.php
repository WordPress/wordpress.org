<?php
namespace WordPressdotorg\Make\Badge_Management;
/**
 * Plugin Name: Profiles.WordPress.org Badge Management for teams.
 * Description: Manage and display badges for WordPress.org profiles.
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * The capability that grants access to the badge manager.
 *
 * A meta capability, mapped in map_badge_manager_cap() to the capability
 * configured for this site.
 */
const MANAGE_BADGES_CAP = 'wporg_manage_profile_badges';

/**
 * Returns the capability a member of this site needs to manage badges.
 *
 * @return string
 */
function get_required_cap(): string {
	return get_option( 'wporg_profile_badge_required_cap', 'manage_options' );
}

/**
 * Returns the capabilities the required capability can be set to, with their labels.
 *
 * @return array<string, string>
 */
function get_required_cap_choices(): array {
	return [
		'publish_posts'  => 'Can publish posts (Author+)',
		'manage_options' => 'Can manage options (Admin+)',
		'manage_network' => 'Can manage network (Super Admin)',
	];
}

/**
 * Maps the badge manager capability to the capability configured for this site.
 *
 * The setting describes a role on this site, so only users who hold one are
 * considered. Capabilities that filters add for visitors without a role (o2
 * Posting Access grants `publish_posts` to logged-in non-members so the post
 * form renders for them) don't count. Super admins can always manage badges.
 *
 * @param string[] $caps    Primitive capabilities required of the user.
 * @param string   $cap     Capability being checked.
 * @param int      $user_id The user ID.
 * @return string[]
 */
function map_badge_manager_cap( $caps, $cap, $user_id ) {
	if ( MANAGE_BADGES_CAP !== $cap ) {
		return $caps;
	}

	if ( ! $user_id || ( ! is_super_admin( $user_id ) && ! is_user_member_of_blog( $user_id ) ) ) {
		return [ 'do_not_allow' ];
	}

	return [ get_required_cap() ];
}
add_filter( 'map_meta_cap', __NAMESPACE__ . '\map_badge_manager_cap', 10, 3 );

/**
 * Whether the current user can change the settings for this site.
 *
 * Settings are for administrators who can manage badges themselves, so a
 * setting that excludes site administrators can't be changed by one.
 *
 * @return bool
 */
function current_user_can_edit_settings(): bool {
	return current_user_can( MANAGE_BADGES_CAP ) && current_user_can( 'manage_options' );
}

// Add the menu item.
add_action( 'admin_menu', function() {
	add_submenu_page(
		'tools.php',
		'Profile Badges',
		'Profile Badges',
		MANAGE_BADGES_CAP,
		'profile-badges',
		__NAMESPACE__ . '\render'
	);
} );

// Load the plugin.
add_action( 'admin_init', function() {
	require_once __DIR__ . '/admin.php';
	require_once __DIR__ . '/admin-post.php';
} );
