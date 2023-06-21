<?php
namespace WordPressdotorg\GitHub\MakeInviter;

/**
 * Plugin Name:       GitHub Invite Member
 * Description:       Invite Members to the WordPress organization.
 * Requires at least: 6.1
 * Requires PHP:      7.4
 * Version:           1.0.0
 * Author:            the WordPress.org Community.
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package           WordPressdotorg\GitHub\MakeInviter
 *
 * Hat-tip to Jonathan Bossenger for the original plugin code.
 */

const APP_ID        = \GH_INVITE_APP_ID;
const KEY           = \GH_INVITE_KEY;
const SLACK_CHANNEL = \GH_INVITE_SLACK_GITHUBADMINS;
const PERMISSION    = 'manage_options'; // Administrators only.

// Add the menu item.
add_action( 'admin_menu', function() {
	add_submenu_page(
		'tools.php',
		'Invite Github Member',
		'Invite Github Member',
		'manage_options',
		'gh_invite_collaborator',
		__NAMESPACE__ . '\render'
	);
} );

// Load the plugin.
add_action( 'admin_init', function() {
	require_once __DIR__ . '/api.php';
	require_once __DIR__ . '/admin.php';
	require_once __DIR__ . '/admin-post.php';
} );