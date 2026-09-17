<?php
/**
 * Seed the WordPress.org Support Forums local environment.
 *
 * Creates the sub-sites, users, roles and forums. Content that needs a
 * sub-site's own post types registered is seeded by seed-site.php, which runs
 * once the plugins activated here are loaded.
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
 * Add users to one site of the network and give them a role there.
 *
 * Forum roles go through bbp_set_user_role(), which fires the filter
 * Badge_Automation::sync_support_team_badge() listens on.
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

		$added = add_user_to_blog( $blog_id, (int) $user->ID, 'subscriber' );
		if ( is_wp_error( $added ) ) {
			\WP_CLI::error( "Could not add '{$login}' to blog {$blog_id}: " . $added->get_error_message() );
		}

		if ( str_starts_with( $role, 'bbp_' ) && function_exists( 'bbp_set_user_role' ) ) {
			bbp_set_user_role( (int) $user->ID, $role );
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
 * Set a site's title, locale and permalink structure.
 *
 * @param int    $blog_id Blog to configure.
 * @param string $title   Site title.
 * @param string $locale  WPLANG value, or '' for the network default.
 * @param bool   $forums  Whether this site serves forums.
 *
 * @return void
 */
function configure_site( int $blog_id, string $title, string $locale = '', bool $forums = false ): void {
	switch_to_blog( $blog_id );

	update_option( 'blogname', $title );
	update_option( 'WPLANG', $locale );
	update_option( 'permalink_structure', '/%postname%/' );

	/*
	 * Production serves the archive from /forums/ but each forum from
	 * /forum/<slug>/, and bbPress only drops the root slug when it is excluded.
	 */
	if ( $forums ) {
		update_option( '_bbp_include_root', false );
	}

	/*
	 * Not flush_rewrite_rules(): switch_to_blog() leaves $wp_rewrite initialised
	 * for the site this request loaded, so a flush here would store the forums'
	 * rules on the sub-sites. Dropping the option regenerates them in context.
	 */
	delete_option( 'rewrite_rules' );

	restore_current_blog();
}

/**
 * Fetch a page's real content from wordpress.org/support.
 *
 * The directory environments seed themselves from the live wp-json API rather
 * than carry copies of production content, so do the same here.
 *
 * @param string $slug Page slug.
 *
 * @return array{title: string, content: string}|null The page, or null when it
 *                                                    could not be fetched.
 */
function fetch_support_page( string $slug ): ?array {
	$response = wp_remote_get(
		add_query_arg( 'slug', $slug, 'https://wordpress.org/support/wp-json/wp/v2/pages' ),
		array( 'timeout' => 15 )
	);
	if ( is_wp_error( $response ) ) {
		return null;
	}

	$pages = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $pages ) || ! isset( $pages[0]['content']['rendered'] ) ) {
		return null;
	}

	return array(
		'title'   => (string) ( $pages[0]['title']['rendered'] ?? '' ),
		'content' => (string) $pages[0]['content']['rendered'],
	);
}

/**
 * Find or create a page at a fixed slug, preferring the real content.
 *
 * @param string $slug    Page slug.
 * @param string $title   Fallback title, used when the import fails.
 * @param string $content Fallback content, used when the import fails.
 *
 * @return int The page's post ID.
 */
function ensure_page( string $slug, string $title, string $content ): int {
	$existing = get_page_by_path( $slug );
	if ( $existing ) {
		return (int) $existing->ID;
	}

	// Fall back to the placeholder when offline, rather than failing the seed.
	$imported = fetch_support_page( $slug );
	if ( $imported && '' !== $imported['content'] ) {
		$title   = $imported['title'] ?: $title;
		$content = $imported['content'];
		\WP_CLI::log( "Imported /{$slug}/ from wordpress.org/support." );
	}

	$page_id = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_name'    => $slug,
			'post_title'   => $title,
			'post_content' => $content,
			'post_author'  => 1,
		),
		true
	);
	if ( is_wp_error( $page_id ) ) {
		\WP_CLI::error( "Could not create the '{$slug}' page: " . $page_id->get_error_message() );
	}

	\WP_CLI::log( "Created page '/{$slug}/'." );
	return (int) $page_id;
}

// The blog IDs are pinned in .wp-env.json, so creation order matters.
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
 * HIDDEN_FORUMS and the Plugin::*_FORUM_ID constants are production post IDs,
 * so the compat forums have to be created as those exact posts.
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

// The support theme hardcodes /welcome/ and /guidelines/ in its local nav.
\WP_CLI::log( 'Creating pages...' );
ensure_page( 'welcome', 'Welcome to Support', 'Placeholder for the support welcome page; see wordpress.org/support/welcome/ for the real copy.' );
ensure_page( 'guidelines', 'Forum Guidelines', 'Placeholder for the forum guidelines; see wordpress.org/support/guidelines/ for the real copy.' );

/*
 * A fresh install seeds helphub-sidebar, which makes the theme add the
 * helphub-with-sidebar class and render stock widgets where production has the
 * HelpHub navigation this environment does not run.
 */
\WP_CLI::log( 'Clearing default sidebar widgets...' );
$sidebars = (array) get_option( 'sidebars_widgets', array() );
foreach ( array_keys( $sidebars ) as $sidebar_id ) {
	if ( 'wp_inactive_widgets' === $sidebar_id || 'array_version' === $sidebar_id ) {
		continue;
	}

	$sidebars['wp_inactive_widgets'] = array_merge( (array) ( $sidebars['wp_inactive_widgets'] ?? array() ), (array) $sidebars[ $sidebar_id ] );
	$sidebars[ $sidebar_id ]         = array();
}
update_option( 'sidebars_widgets', $sidebars );

/*
 * Directory_Compat finds a topic by the directory slug in its topic-plugin or
 * topic-theme term, so /plugin/<slug>/ needs both the forum and the term.
 */
\WP_CLI::log( 'Creating topics...' );
$installing = ensure_forum( 'Installing WordPress', '' );
$fixing     = ensure_forum( 'Fixing WordPress', '' );

$topic = ensure_topic(
	$installing,
	'Blank page after installing',
	'I finished the five minute install and every page is blank. Where should I start looking?',
	'visitor',
	array( 'topic_resolved' => 'yes' )
);
ensure_reply( $topic, $installing, 'Turn on WP_DEBUG and check your error log; a blank page is almost always a fatal.', 'moderator' );

$topic = ensure_topic(
	$fixing,
	'Media uploads fail with an HTTP error',
	'Every upload over about two megabytes fails with "HTTP error". Smaller files are fine.',
	'visitor',
	array( 'topic_resolved' => 'no' )
);
ensure_reply( $topic, $fixing, 'That is usually a server limit rather than WordPress. What are your upload_max_filesize and post_max_size set to?', 'keymaster' );
ensure_reply( $topic, $fixing, 'Both are 2M, so that explains it. Thanks!', 'visitor' );

$topic = ensure_topic(
	Plugin::PLUGINS_FORUM_ID,
	'Hello Dolly shows no lyric in the admin bar',
	'The plugin is active but no lyric appears. Is there a setting I am missing?',
	'visitor',
	array( 'topic_resolved' => 'no' ),
	array( 'topic-plugin' => 'hello-dolly' )
);
ensure_reply( $topic, Plugin::PLUGINS_FORUM_ID, 'It renders in the admin only. Which screen are you looking at?', 'pluginsupport' );

ensure_topic(
	Plugin::THEMES_FORUM_ID,
	'Twenty Twenty-Four template parts will not save',
	'Editing a template part in the site editor appears to work, but the change is gone after a reload.',
	'visitor',
	array( 'topic_resolved' => 'no' ),
	array( 'topic-theme' => 'twentytwentyfour' )
);

// The rating is stored twice so the star filters and the average agree.
$review = ensure_topic(
	Plugin::REVIEWS_FORUM_ID,
	'Still charming after all these years',
	'Does exactly one thing and does it well. A fine example of a tiny plugin.',
	'visitor',
	array( 'rating' => 5 ),
	array( 'topic-plugin' => 'hello-dolly' )
);
if ( class_exists( 'WPORG_Ratings' ) ) {
	$reviewer = get_user_by( 'login', 'visitor' );
	\WPORG_Ratings::set_rating( $review, 'plugin', 'hello-dolly', (int) $reviewer->ID, 5 );
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
configure_site( (int) get_current_blog_id(), 'WordPress.org Forums', '', true );

// The language pack itself is left to the developer.
configure_site( $rosetta_blog, 'Rosetta Forums', 'de_DE', true );
configure_site( $plugins_blog, 'Plugin Directory (forum dependency)' );
configure_site( $themes_blog, 'Theme Directory (forum dependency)' );

update_option( 'wporg_support_env_seeded', time() );

\WP_CLI::success( 'Seeded the support forums network.' );
