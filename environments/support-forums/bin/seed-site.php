<?php
/**
 * Seed one sub-site of the support forums network.
 *
 * Runs once per sub-site, after seed.php has activated that site's plugins, so
 * their post types and taxonomies are registered on this request. Which site it
 * seeds is decided by the blog IDs pinned in .wp-env.json.
 *
 * Idempotent: every record is looked up before it is created.
 *
 * Usage:
 *   wp eval-file wp-content/env-bin/seed-site.php --url=<sub-site url>
 *
 * No strict_types declaration: eval-file evaluates the file inline, where a
 * declare() cannot be the first statement.
 *
 * @package support-forums-env
 */

namespace WordPressdotorg\Forums\Env;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/forum-helpers.php';

/**
 * Find or create a directory record for one of the forum compat views.
 *
 * @param string $post_type Directory post type: plugin or repopackage.
 * @param string $slug      Post slug, which is the directory slug the forums use.
 * @param string $title     Post title.
 * @param string $content   Post content.
 * @param string $author    Login of the user to attribute the record to.
 *
 * @return int The record's post ID.
 */
function ensure_directory_record( string $post_type, string $slug, string $title, string $content, string $author ): int {
	$existing = get_page_by_path( $slug, OBJECT, $post_type );
	if ( $existing ) {
		return (int) $existing->ID;
	}

	$user = get_user_by( 'login', $author );

	// Plugin_Directory::filter_wp_insert_post_data() requires the modified dates.
	$date = '2022-08-20 01:00:00';

	$post_id = wp_insert_post(
		array(
			'post_type'         => $post_type,
			'post_status'       => 'publish',
			'post_name'         => $slug,
			'post_title'        => $title,
			'post_content'      => $content,
			'post_author'       => $user ? $user->ID : 1,
			'post_date'         => $date,
			'post_date_gmt'     => $date,
			'post_modified'     => $date,
			'post_modified_gmt' => $date,
		),
		true
	);
	if ( is_wp_error( $post_id ) ) {
		\WP_CLI::error( "Could not create the '{$slug}' {$post_type}: " . $post_id->get_error_message() );
	}

	\WP_CLI::log( "Created {$post_type} '{$slug}' ({$post_id})." );
	return (int) $post_id;
}

$current_blog_id = (int) get_current_blog_id();

if ( (int) WPORG_PLUGIN_DIRECTORY_BLOGID === $current_blog_id ) {
	if ( ! taxonomy_exists( 'plugin_committers' ) ) {
		\WP_CLI::error( 'The plugin-directory plugin is not loaded on this site.' );
	}

	$plugin_id = ensure_directory_record(
		'plugin',
		'hello-dolly',
		'Hello Dolly',
		'This is not just a plugin, it symbolizes the hope and enthusiasm of an entire generation.',
		'pluginauthor'
	);

	wp_set_object_terms( $plugin_id, 'pluginauthor', 'plugin_committers' );
	wp_set_object_terms( $plugin_id, 'plugincontributor', 'plugin_contributors' );
	wp_set_object_terms( $plugin_id, 'pluginsupport', 'plugin_support_reps' );

	\WP_CLI::success( 'Seeded the plugin directory dependency.' );
	return;
}

if ( (int) WPORG_THEME_DIRECTORY_BLOGID === $current_blog_id ) {
	if ( ! post_type_exists( 'repopackage' ) ) {
		\WP_CLI::error( 'The theme-directory plugin is not loaded on this site.' );
	}

	$theme_id = ensure_directory_record(
		'repopackage',
		'twentytwentyfour',
		'Twenty Twenty-Four',
		'Twenty Twenty-Four is designed to be flexible, versatile and applicable to any website.',
		'themeauthor'
	);

	/*
	 * Themes_API::get_theme() indexes this meta by version without checking its
	 * shape, so a record without it makes the theme page a TypeError.
	 */
	$version = '1.0';
	update_post_meta( $theme_id, '_screenshot', array( $version => 'screenshot.png' ) );
	update_post_meta( $theme_id, '_status', array( $version => 'live' ) );
	update_post_meta( $theme_id, '_requires', array( $version => '6.4' ) );
	update_post_meta( $theme_id, '_requires_php', array( $version => '7.0' ) );

	\WP_CLI::success( 'Seeded the theme directory dependency.' );
	return;
}

if ( (int) WPORG_LOCAL_ROSETTA_BLOGID === $current_blog_id ) {
	if ( ! function_exists( 'bbp_get_forum_post_type' ) ) {
		\WP_CLI::error( 'bbPress is not loaded on this site.' );
	}

	foreach ( default_forums() as $forum_title => $forum_content ) {
		ensure_forum( $forum_title, $forum_content );
	}

	$forum_id = ensure_forum( 'Installing WordPress', '' );
	$topic_id = ensure_topic(
		$forum_id,
		'Installation auf Deutsch schlaegt fehl',
		'Die Installation bricht bei der Datenbankverbindung ab. Hat jemand einen Tipp?',
		'visitor',
		array( 'topic_resolved' => 'no' )
	);
	ensure_reply( $topic_id, $forum_id, 'Pruefe bitte die Zugangsdaten in der wp-config.php.', 'rosettamoderator' );

	switch_theme( 'wporg-support-2024' );

	\WP_CLI::success( 'Seeded the rosetta forums.' );
	return;
}

\WP_CLI::warning( "Blog {$current_blog_id} is not one of the seeded sub-sites; nothing to do." );
