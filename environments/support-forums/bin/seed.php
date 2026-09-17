<?php
/**
 * Seed the WordPress.org Support Forums local environment.
 *
 * Creates the `/plugins`, `/themes` and `/rosetta` sub-sites, the full set of
 * forum user types, and the default forums on the main forums site.
 *
 * The compat forums are created with the post IDs that Plugin and Support_Compat
 * hard-code for production. Without that the `/plugin/<slug>/` and
 * `/theme/<slug>/` views, the reviews forum, and the hidden-forum filtering all
 * point at posts that do not exist locally.
 *
 * Content that needs a sub-site's own post types and taxonomies registered is
 * seeded by seed-site.php, which runs per sub-site once the plugins activated
 * here are loaded.
 *
 * Idempotent: gated on the wporg_support_env_seeded option, which
 * `npm run support:refresh` clears.
 *
 * Usage:
 *   wp eval-file wp-content/env-bin/seed.php
 *
 * No strict_types declaration: eval-file evaluates the file inline, where a
 * declare() cannot be the first statement.
 *
 * @package support-forums-env
 */

namespace WordPressdotorg\Forums\Env;

use WordPressdotorg\Forums\Plugin;
use WordPressdotorg\Forums\Support_Compat;
use WP_User;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! is_multisite() ) {
	\WP_CLI::error( 'This environment must be a multisite network; check the "multisite" key in .wp-env.json.' );
}

if ( ! class_exists( Plugin::class ) ) {
	\WP_CLI::error( 'The support-forums plugin is not loaded.' );
}

if ( ! function_exists( 'bbp_get_forum_post_type' ) ) {
	\WP_CLI::error( 'bbPress is not loaded.' );
}

foreach ( array( 'WPORG_SUPPORT_FORUMS_BLOGID', 'WPORG_PLUGIN_DIRECTORY_BLOGID', 'WPORG_THEME_DIRECTORY_BLOGID', 'WPORG_LOCAL_ROSETTA_BLOGID' ) as $required ) {
	if ( ! defined( $required ) ) {
		\WP_CLI::error( "{$required} is not defined; check the \"config\" key in .wp-env.json." );
	}
}

if ( get_option( 'wporg_support_env_seeded' ) ) {
	\WP_CLI::log( 'Already seeded, skipping. Run `npm run support:refresh` to re-seed.' );
	return;
}

require_once ABSPATH . 'wp-admin/includes/plugin.php';

/**
 * Find or create a sub-site at the given path.
 *
 * @param string $path  Path relative to the network root, without slashes.
 * @param string $title Site title.
 *
 * @return int The site's blog ID.
 */
function ensure_site( string $path, string $title ): int {
	$network = get_network();
	$full    = trailingslashit( $network->path . $path );

	$existing = get_blog_id_from_url( $network->domain, $full );
	if ( $existing ) {
		return (int) $existing;
	}

	$blog_id = wpmu_create_blog( $network->domain, $full, $title, 1, array( 'public' => 1 ), (int) $network->id );
	if ( is_wp_error( $blog_id ) ) {
		\WP_CLI::error( "Could not create the '{$path}' sub-site: " . $blog_id->get_error_message() );
	}

	\WP_CLI::log( "Created sub-site '{$path}' (blog {$blog_id})." );
	return (int) $blog_id;
}

/**
 * Find or create a network user.
 *
 * New users get the main site's default role, which set_roles() then replaces
 * with their forum role.
 *
 * @param string $login        User login.
 * @param string $display_name Display name.
 *
 * @return int The user's ID.
 */
function ensure_user( string $login, string $display_name ): int {
	$user = get_user_by( 'login', $login );
	if ( $user ) {
		return (int) $user->ID;
	}

	$user_id = wp_insert_user(
		array(
			'user_login'   => $login,
			'user_pass'    => 'password',
			'user_email'   => "{$login}@example.com",
			'display_name' => $display_name,
		)
	);
	if ( is_wp_error( $user_id ) ) {
		\WP_CLI::error( "Could not create user '{$login}': " . $user_id->get_error_message() );
	}

	\WP_CLI::log( "Created user '{$login}'." );
	return (int) $user_id;
}

/**
 * Give users a role on one site of the network.
 *
 * Setting a role writes the site's capability meta, which is also what makes
 * the user a member of that site.
 *
 * @param int   $blog_id Blog to assign the roles on.
 * @param array $roles   Map of user login to role name.
 *
 * @return void
 */
function set_roles( int $blog_id, array $roles ): void {
	switch_to_blog( $blog_id );

	foreach ( $roles as $login => $role ) {
		$user = get_user_by( 'login', $login );
		if ( ! $user ) {
			\WP_CLI::warning( "No such user '{$login}'; skipping role assignment." );
			continue;
		}

		// WP_User caches capabilities per blog, so read them for this one.
		$scoped = new WP_User( $user->ID, '', $blog_id );
		$scoped->set_role( $role );
	}

	restore_current_blog();
}

/**
 * Activate plugins on one site of the network.
 *
 * The plugins only take effect on the next request, so anything that needs
 * their post types or taxonomies belongs in seed-site.php.
 *
 * @param int   $blog_id Blog to activate on.
 * @param array $plugins Plugin files, relative to the plugins directory.
 *
 * @return void
 */
function activate_on_site( int $blog_id, array $plugins ): void {
	switch_to_blog( $blog_id );

	foreach ( $plugins as $plugin ) {
		if ( is_plugin_active( $plugin ) ) {
			continue;
		}

		$error = activate_plugin( $plugin );
		if ( is_wp_error( $error ) ) {
			\WP_CLI::warning( "Could not activate '{$plugin}' on blog {$blog_id}: " . $error->get_error_message() );
		}
	}

	restore_current_blog();
}

/**
 * Set a site's title and permalink structure.
 *
 * @param int    $blog_id Blog to configure.
 * @param string $title   Site title.
 *
 * @return void
 */
function configure_site( int $blog_id, string $title ): void {
	switch_to_blog( $blog_id );

	update_option( 'blogname', $title );
	update_option( 'permalink_structure', '/%postname%/' );
	flush_rewrite_rules( false );

	restore_current_blog();
}

/*
 * The blog IDs are pinned in .wp-env.json, so the sub-sites have to be created
 * in this order. Bail rather than leave a network the constants misdescribe.
 */
$plugins_blog = ensure_site( 'plugins', 'Plugin Directory (forum dependency)' );
$themes_blog  = ensure_site( 'themes', 'Theme Directory (forum dependency)' );
$rosetta_blog = ensure_site( 'rosetta', 'Rosetta Forums' );

$pinned_blog_ids = array(
	'WPORG_SUPPORT_FORUMS_BLOGID'   => array( WPORG_SUPPORT_FORUMS_BLOGID, get_current_blog_id() ),
	'WPORG_PLUGIN_DIRECTORY_BLOGID' => array( WPORG_PLUGIN_DIRECTORY_BLOGID, $plugins_blog ),
	'WPORG_THEME_DIRECTORY_BLOGID'  => array( WPORG_THEME_DIRECTORY_BLOGID, $themes_blog ),
	'WPORG_LOCAL_ROSETTA_BLOGID'    => array( WPORG_LOCAL_ROSETTA_BLOGID, $rosetta_blog ),
);
foreach ( $pinned_blog_ids as $constant => $pair ) {
	if ( (int) $pair[0] !== (int) $pair[1] ) {
		\WP_CLI::error( "{$constant} is {$pair[0]} but that site is blog {$pair[1]}; destroy the environment and start again." );
	}
}

\WP_CLI::log( 'Creating users...' );
$users = array(
	'keymaster'         => 'Forum Keymaster',
	'moderator'         => 'Forum Moderator',
	'rosettakeymaster'  => 'Rosetta Keymaster',
	'rosettamoderator'  => 'Rosetta Moderator',
	'pluginauthor'      => 'Plugin Author',
	'plugincontributor' => 'Plugin Contributor',
	'pluginsupport'     => 'Plugin Support Rep',
	'themeauthor'       => 'Theme Author',
	'themesupport'      => 'Theme Support Rep',
	'visitor'           => 'Forum Visitor',
);
foreach ( $users as $login => $display_name ) {
	ensure_user( $login, $display_name );
}

\WP_CLI::log( 'Assigning roles...' );
$participants = array_fill_keys( array_keys( $users ), 'bbp_participant' );

set_roles(
	(int) get_current_blog_id(),
	array_merge(
		$participants,
		array(
			'keymaster' => 'bbp_keymaster',
			'moderator' => 'bbp_moderator',
		)
	)
);

set_roles(
	$rosetta_blog,
	array_merge(
		$participants,
		array(
			'rosettakeymaster' => 'bbp_keymaster',
			'rosettamoderator' => 'bbp_moderator',
		)
	)
);

set_roles(
	$plugins_blog,
	array(
		'pluginauthor'      => 'subscriber',
		'plugincontributor' => 'subscriber',
		'pluginsupport'     => 'subscriber',
	)
);

set_roles(
	$themes_blog,
	array(
		'themeauthor'  => 'subscriber',
		'themesupport' => 'subscriber',
	)
);

/*
 * Support_Compat::HIDDEN_FORUMS and the Plugin::*_FORUM_ID constants are
 * production post IDs, so the compat forums have to be created as those exact
 * posts. Titles match the production forums.
 */
\WP_CLI::log( 'Creating forums...' );
$compat_forums = array(
	Plugin::THEMES_FORUM_ID  => array( 'Themes and Templates', 'Forum for theme-specific support topics; hidden from the forum index.' ),
	Plugin::PLUGINS_FORUM_ID => array( 'Plugins and Hacks', 'Forum for plugin-specific support topics; hidden from the forum index.' ),
	Plugin::REVIEWS_FORUM_ID => array( 'Reviews', 'Forum for plugin and theme reviews; hidden from the forum index.' ),
	21267                    => array( 'Your WordPress', 'Legacy forum, created so the hidden-forum list resolves.' ),
	21271                    => array( 'Meetups', 'Legacy forum, created so the hidden-forum list resolves.' ),
);

$unseeded = array_diff( Support_Compat::HIDDEN_FORUMS, array_keys( $compat_forums ) );
if ( $unseeded ) {
	\WP_CLI::warning( 'Support_Compat::HIDDEN_FORUMS references forums this seed does not create: ' . implode( ', ', $unseeded ) );
}

require_once __DIR__ . '/forum-helpers.php';

foreach ( $compat_forums as $forum_id => $forum ) {
	ensure_forum( $forum[0], $forum[1], (int) $forum_id );
}

foreach ( default_forums() as $forum_title => $forum_content ) {
	ensure_forum( $forum_title, $forum_content );
}

// Every forum site runs the same stack as the main forums.
\WP_CLI::log( 'Activating plugins on the sub-sites...' );
activate_on_site( $rosetta_blog, (array) get_option( 'active_plugins', array() ) );
activate_on_site( $plugins_blog, array( 'plugin-directory/plugin-directory.php' ) );
activate_on_site( $themes_blog, array( 'theme-directory/theme-directory.php' ) );

// The forum sites share one theme, so it has to be allowed network-wide.
\WP_CLI::log( 'Enabling the support theme...' );
$allowed_themes                       = (array) get_site_option( 'allowedthemes', array() );
$allowed_themes['wporg-support-2024'] = true;
update_site_option( 'allowedthemes', $allowed_themes );
switch_theme( 'wporg-support-2024' );

\WP_CLI::log( 'Configuring sites...' );
configure_site( (int) get_current_blog_id(), 'WordPress.org Forums' );
configure_site( $rosetta_blog, 'Rosetta Forums' );
configure_site( $plugins_blog, 'Plugin Directory (forum dependency)' );
configure_site( $themes_blog, 'Theme Directory (forum dependency)' );

update_option( 'wporg_support_env_seeded', time() );

\WP_CLI::success( 'Seeded the support forums network.' );
