<?php
/**
 * FreeScout sidebar: plugins and themes mentioned in the conversation, and those the sender owns.
 *
 * @package WordPressdotorg\API\FreeScout
 */

declare( strict_types = 1 );

namespace WordPressdotorg\API\FreeScout;

// Run as the plugin directory, such that any filters are correct.
$wp_init_host = 'https://wordpress.org/plugins/';
require __DIR__ . '/common.php';

/**
 * Orders plugins and themes by status, then by name: in review or approved first, with theme drafts, which can also be
 * rejected; then published, then the others, and rejected plugins last.
 *
 * @var string
 */
const ITEM_ORDER = "CASE
	WHEN post_status IN( 'new', 'pending', 'approved', 'draft' ) THEN 1
	WHEN post_status = 'publish' THEN 2
	WHEN post_status = 'rejected' THEN 4
	ELSE 3
END, post_title";

/**
 * Gets the plugins and themes panel.
 *
 * @param object $request Request payload.
 * @return array Panel blocks.
 */
function render_plugins_themes( object $request ): array {
	$user          = get_user_by( 'email', get_user_email_for_email( $request ) );
	$mailbox_email = (string) ( $request->mailbox->email ?? '' );

	$sites           = array(
		'themes'  => WPORG_THEME_DIRECTORY_BLOGID,
		'plugins' => WPORG_PLUGIN_DIRECTORY_BLOGID,
	);
	$repo_post_types = array(
		'themes'  => 'repopackage',
		'plugins' => 'plugin',
	);

	// Display plugins first in the plugins inbox.
	if ( str_starts_with( strtolower( $mailbox_email ), 'plugins' ) ) {
		$sites           = array_reverse( $sites );
		$repo_post_types = array_reverse( $repo_post_types );
	}

	$blocks = array();

	$mentioned = get_plugin_or_theme_from_email( $request );

	foreach ( $sites as $type => $blog_id ) {
		if ( empty( $mentioned[ $type ] ) ) {
			continue;
		}

		switch_to_blog( $blog_id );

		$post_ids = get_items_by_slug( $mentioned[ $type ] );

		if ( $post_ids ) {
			$blocks[] = array(
				'type'  => 'heading',
				'text'  => ucwords( $type ) . ' mentioned',
				'count' => count( $post_ids ),
			);
			$blocks[] = render_items( $post_ids, $mailbox_email );
		}

		restore_current_blog();
	}

	if ( $user ) {
		foreach ( $sites as $type => $blog_id ) {
			switch_to_blog( $blog_id );

			$post_ids = get_user_items( $user );
			if ( $post_ids ) {
				$url = add_query_arg(
					array(
						'post_type' => $repo_post_types[ $type ],
						'author'    => $user->ID,
					),
					admin_url( 'edit.php' )
				);

				$blocks[] = array(
					'type'  => 'heading',
					'text'  => ucwords( $type ) . ' owned',
					'url'   => $url,
					'count' => count( $post_ids ),
				);
				$blocks[] = render_items( $post_ids, $mailbox_email );
			}

			restore_current_blog();
		}
	}

	return $blocks;
}

/**
 * Gets the plugins or themes on the current site a user owns or commits to.
 *
 * @param \WP_User $user User.
 * @return array Post IDs.
 */
function get_user_items( \WP_User $user ): array {
	global $wpdb;

	$slugs = array();

	if ( WPORG_PLUGIN_DIRECTORY_BLOGID === get_current_blog_id() ) {
		$committer_plugins = $wpdb->get_col(
			$wpdb->prepare( 'SELECT path FROM %i WHERE user = %s', PLUGINS_TABLE_PREFIX . 'svn_access', $user->user_login )
		);

		foreach ( $committer_plugins as $plugin ) {
			$plugin = ltrim( $plugin, '/' );
			if ( $plugin ) {
				$slugs[] = $plugin;
			}
		}
	}

	$slugs = array_values( array_unique( $slugs ) );

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- The slug condition only adds placeholders.
	$ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT ID
			FROM %i
			WHERE post_type IN( 'plugin', 'repopackage' ) AND ( post_author = %d" . ( $slugs ? ' OR post_name IN( ' . implode( ', ', array_fill( 0, count( $slugs ), '%s' ) ) . ' )' : '' ) . ' )
			ORDER BY ' . ITEM_ORDER,
			array_merge( array( $wpdb->posts, $user->ID ), $slugs )
		)
	);
	// phpcs:enable

	return array_map( 'intval', $ids );
}

/**
 * Gets the plugins or themes on the current site with the given slugs, in any status.
 *
 * Queried directly, as WP_Query's 'any' skips statuses this request doesn't register, like suspended themes.
 *
 * @param array $slugs Plugin or theme slugs.
 * @return int[] Post IDs, ordered by status, then title.
 */
function get_items_by_slug( array $slugs ): array {
	global $wpdb;

	if ( ! $slugs ) {
		return array();
	}

	$ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT ID
			FROM %i
			WHERE post_type IN( 'plugin', 'repopackage' ) AND post_status NOT IN( 'trash', 'auto-draft' )
				AND post_name IN( " . implode( ', ', array_fill( 0, count( $slugs ), '%s' ) ) . ' )
			ORDER BY ' . ITEM_ORDER, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- A constant, with no input.
			array_merge( array( $wpdb->posts ), array_values( $slugs ) )
		)
	);

	return array_map( 'intval', $ids );
}

/**
 * Gets a list of plugins or themes, with their status.
 *
 * @param array  $post_ids      Post IDs on the current site.
 * @param string $mailbox_email Address of the mailbox the conversation is in.
 * @return array Panel block.
 */
function render_items( array $post_ids, string $mailbox_email ): array {
	$items = array();

	// Reviews are the plugins team's; other mailboxes' conversations can name any plugin.
	$show_review = str_starts_with( strtolower( $mailbox_email ), 'plugins' );

	foreach ( $post_ids as $post_id ) {
		$post          = get_post( (int) $post_id );
		$type          = ( 'plugin' === $post->post_type ) ? 'plugin' : 'theme';
		$reviewer      = '';
		$close_reason  = '';
		$rejection     = '';
		$last_modified = $post->post_modified_gmt;
		$download_link = 'plugin' === $type ? get_plugin_download_url( $post, $show_review ) : "https://downloads.wordpress.org/theme/{$post->post_name}.latest-stable.zip";

		if ( 'plugin' === $type ) {
			$reviewer = $show_review ? get_assigned_reviewer( $post ) : '';

			// Prefer the last_updated post meta.
			$last_modified = $post->last_updated ? $post->last_updated : $last_modified;

			// Why a plugin was closed or rejected is the plugins team's, like reviews.
			if ( $show_review ) {
				$close_reason = (string) $post->_close_reason;
				$rejection    = $post->_rejection_reason ? get_plugin_reason( 'get_rejection_reasons', (string) $post->_rejection_reason ) : '';
			}
		} elseif ( 'draft' === $post->post_status ) {
			$download_link = ''; // No zips exist for themes in review.
		}

		$last_updated = (int) strtotime( $last_modified );
		$released     = 'plugin' === $type && is_plugin_released( $post );

		// Built by hand, as the post types aren't registered on this site; rarest first, so Download lines up at the end.
		$links = array();
		if ( $released || ( 'theme' === $type && 'publish' === $post->post_status ) ) {
			$links[] = array(
				'text' => 'Scans in Gandalf',
				'url'  => sprintf( SCANS_URL, $type, rawurlencode( $post->post_name ) ),
				'icon' => 'shield',
			);
		}
		if ( $released || ( 'theme' === $type && in_array( $post->post_status, array( 'publish', 'delist' ), true ) ) ) {
			$links[] = array(
				'text' => 'View on WordPress.org',
				'url'  => home_url( "/{$post->post_name}/" ),
				'icon' => 'link',
			);
		}
		if ( $download_link ) {
			$links[] = array(
				'text' => 'Download',
				'url'  => $download_link,
				'icon' => 'download-alt',
			);
		}

		$items[] = array_merge(
			array(
				// Stored escaped, and shown as text.
				'title' => html_entity_decode( $post->post_title, ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
				'url'   => add_query_arg(
					array(
						'action' => 'edit',
						'post'   => $post->ID,
					),
					admin_url( 'post.php' )
				),
				'meta'  => array( array( 'text' => $post->post_name ) ),
				'age'   => array(
					'text'    => short_time_diff( $last_updated, time() ),
					'tooltip' => 'Updated ' . gmdate( 'Y-m-d', $last_updated ),
				),
				'links' => $links,
			),
			get_item_status( $post->post_status, $reviewer, $close_reason, $rejection )
		);
	}

	return array(
		'type'  => 'items',
		'items' => $items,
	);
}

send_panel( render_plugins_themes( get_request( basename( __FILE__ ) ) ) );
