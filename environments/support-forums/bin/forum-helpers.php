<?php
/**
 * Forum creation helpers shared by seed.php and seed-site.php.
 *
 * No strict_types declaration: the callers are evaluated inline by
 * `wp eval-file`, where a declare() cannot be the first statement.
 *
 * @package support-forums-env
 */

namespace WordPressdotorg\Forums\Env;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The forums every forum site starts with, mirroring wordpress.org/support.
 *
 * @return array Map of forum title to description.
 */
function default_forums(): array {
	return array(
		'Installing WordPress' => 'If you encounter any problems while setting up WordPress.',
		'Fixing WordPress'     => 'For any problems encountered after setting up WordPress.',
	);
}

/**
 * Create a bbPress forum, optionally as a specific post ID.
 *
 * @param string $title     Forum title.
 * @param string $content   Forum description.
 * @param int    $import_id Post ID to create the forum as, or 0 for the next available.
 *
 * @return int The forum's post ID.
 */
function ensure_forum( string $title, string $content, int $import_id = 0 ): int {
	if ( $import_id && get_post( $import_id ) ) {
		return $import_id;
	}

	$existing = get_posts(
		array(
			'post_type'   => bbp_get_forum_post_type(),
			'post_status' => 'any',
			'name'        => sanitize_title( $title ),
			'numberposts' => 1,
		)
	);
	if ( $existing ) {
		return (int) $existing[0]->ID;
	}

	$forum_id = wp_insert_post(
		array(
			'post_type'    => bbp_get_forum_post_type(),
			'post_status'  => bbp_get_public_status_id(),
			'post_title'   => $title,
			'post_content' => $content,
			'post_author'  => 1,
			'import_id'    => $import_id,
		),
		true
	);
	if ( is_wp_error( $forum_id ) ) {
		\WP_CLI::error( "Could not create forum '{$title}': " . $forum_id->get_error_message() );
	}

	// wp_insert_post() does not add the forum meta bbPress queries against.
	bbp_update_forum( array( 'forum_id' => $forum_id ) );

	\WP_CLI::log( "Created forum '{$title}' ({$forum_id})." );
	return (int) $forum_id;
}
