<?php
/**
 * Forum creation helpers shared by seed.php and seed-site.php.
 *
 * @package support-forums-env
 */

declare( strict_types = 1 );

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
 * Errors rather than adopting a post that already holds a required ID, which
 * would point the compat views at the wrong content.
 *
 * @param string $title     Forum title.
 * @param string $content   Forum description.
 * @param int    $import_id Post ID to create the forum as, or 0 for the next available.
 *
 * @return int The forum's post ID.
 */
function ensure_forum( string $title, string $content, int $import_id = 0 ): int {
	$slug = sanitize_title( $title );

	if ( $import_id ) {
		$existing = get_post( $import_id );

		if ( $existing ) {
			if ( bbp_get_forum_post_type() !== $existing->post_type || $slug !== $existing->post_name ) {
				\WP_CLI::error(
					sprintf(
						'Post %d is a %s named "%s", but the "%s" forum has to be created as that ID. Destroy the environment and start again.',
						$import_id,
						$existing->post_type,
						$existing->post_name,
						$title
					)
				);
			}

			return $import_id;
		}
	}

	$existing = get_posts(
		array(
			'post_type'   => bbp_get_forum_post_type(),
			'post_status' => 'any',
			'name'        => $slug,
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

/**
 * Create a bbPress topic in a forum.
 *
 * Goes through bbp_insert_topic() so the topic gets the meta and counts
 * bbPress queries against, then refreshes the forum's own counts.
 *
 * @param int    $forum_id  Forum to post in.
 * @param string $title     Topic title.
 * @param string $content   Topic content.
 * @param string $author    Login of the topic author.
 * @param array  $meta      Extra post meta, e.g. topic_resolved or rating.
 * @param array  $terms     Map of taxonomy to term slug, e.g. topic-plugin.
 *
 * @return int The topic's post ID.
 */
function ensure_topic( int $forum_id, string $title, string $content, string $author, array $meta = array(), array $terms = array() ): int {
	$slug     = sanitize_title( $title );
	$existing = get_posts(
		array(
			'post_type'   => bbp_get_topic_post_type(),
			'post_status' => 'any',
			'name'        => $slug,
			'numberposts' => 1,
		)
	);
	if ( $existing ) {
		return (int) $existing[0]->ID;
	}

	$user     = get_user_by( 'login', $author );
	$topic_id = bbp_insert_topic(
		array(
			'post_parent'  => $forum_id,
			'post_title'   => $title,
			'post_content' => $content,
			'post_author'  => $user ? (int) $user->ID : 1,
		),
		array(
			'forum_id' => $forum_id,
		)
	);

	if ( ! $topic_id ) {
		\WP_CLI::error( "Could not create topic '{$title}'." );
	}

	foreach ( $meta as $key => $value ) {
		update_post_meta( $topic_id, $key, $value );
	}

	foreach ( $terms as $taxonomy => $term ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			\WP_CLI::warning( "Taxonomy '{$taxonomy}' is not registered; '{$title}' will not appear in the compat views." );
			continue;
		}

		wp_set_object_terms( $topic_id, $term, $taxonomy, false );
	}

	bbp_update_topic( $topic_id, $forum_id );
	bbp_update_forum( array( 'forum_id' => $forum_id ) );

	\WP_CLI::log( "Created topic '{$title}' ({$topic_id})." );
	return (int) $topic_id;
}

/**
 * Add a reply to a topic.
 *
 * @param int    $topic_id Topic to reply to.
 * @param int    $forum_id Forum the topic is in.
 * @param string $content  Reply content.
 * @param string $author   Login of the reply author.
 *
 * @return int The reply's post ID.
 */
function ensure_reply( int $topic_id, int $forum_id, string $content, string $author ): int {
	$existing = get_posts(
		array(
			'post_type'   => bbp_get_reply_post_type(),
			'post_status' => 'any',
			'post_parent' => $topic_id,
			'numberposts' => -1,
			's'           => $content,
		)
	);
	if ( $existing ) {
		return (int) $existing[0]->ID;
	}

	$user     = get_user_by( 'login', $author );
	$reply_id = bbp_insert_reply(
		array(
			'post_parent'  => $topic_id,
			'post_content' => $content,
			'post_author'  => $user ? (int) $user->ID : 1,
		),
		array(
			'forum_id' => $forum_id,
			'topic_id' => $topic_id,
		)
	);

	if ( ! $reply_id ) {
		\WP_CLI::error( "Could not reply to topic {$topic_id}." );
	}

	bbp_update_reply( $reply_id, $topic_id, $forum_id );
	bbp_update_topic( $topic_id, $forum_id );
	bbp_update_forum( array( 'forum_id' => $forum_id ) );

	return (int) $reply_id;
}
