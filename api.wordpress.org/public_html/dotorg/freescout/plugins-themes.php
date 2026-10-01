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
 * Renders the plugins and themes panel.
 *
 * @param object $request Request payload.
 * @return string
 */
function render_plugins_themes( object $request ): string {
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
	if ( str_starts_with( $mailbox_email, 'plugins' ) ) {
		$sites           = array_reverse( $sites );
		$repo_post_types = array_reverse( $repo_post_types );
	}

	$html = '';

	$mentioned = get_plugin_or_theme_from_email( $request );

	foreach ( $sites as $type => $blog_id ) {
		if ( empty( $mentioned[ $type ] ) ) {
			continue;
		}

		switch_to_blog( $blog_id );

		$post_ids = get_items_by_slug( $mentioned[ $type ] );

		if ( $post_ids ) {
			$html .= '<h5 class="wporg-sidebar-heading">' . esc_html( ucwords( $type ) ) . ' mentioned ' . render_count( count( $post_ids ) ) . '</h5>';
			$html .= render_items( $post_ids, $mailbox_email );
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

				$html .= '<h5 class="wporg-sidebar-heading"><a href="' . esc_url( $url ) . '">' . esc_html( ucwords( $type ) ) . ' owned</a> ' . render_count( count( $post_ids ) ) . '</h5>';
				$html .= render_items( $post_ids, $mailbox_email );
			}

			restore_current_blog();
		}
	}

	return $html;
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
			WHERE post_type IN( 'plugin', 'repopackage' ) AND ( post_author = %d" . ( $slugs ? ' OR post_name IN( ' . implode( ', ', array_fill( 0, count( $slugs ), '%s' ) ) . ' )' : '' ) . " )
			ORDER BY FIELD( post_status, 'new', 'pending', 'publish', 'disabled', 'delisted', 'delist', 'closed', 'approved', 'suspended', 'suspend', 'rejected', 'draft' ), post_title",
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
 * @return int[] Post IDs, ordered by title.
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
			ORDER BY post_title',
			array_merge( array( $wpdb->posts ), array_values( $slugs ) )
		)
	);

	return array_map( 'intval', $ids );
}

/**
 * Renders a list of plugins or themes, with their status.
 *
 * @param array  $post_ids      Post IDs on the current site.
 * @param string $mailbox_email Address of the mailbox the conversation is in.
 * @return string
 */
function render_items( array $post_ids, string $mailbox_email ): string {
	$html = '<ul class="wporg-sidebar-items">';

	// Reviews are the plugins team's; other mailboxes' conversations can name any plugin.
	$show_review = str_starts_with( $mailbox_email, 'plugins' );

	foreach ( $post_ids as $post_id ) {
		$post          = get_post( (int) $post_id );
		$type          = ( 'plugin' === $post->post_type ) ? 'plugin' : 'theme';
		$status        = '';
		$tone          = 'neutral';
		$reviewer      = false;
		$last_modified = $post->post_modified_gmt;
		$download_link = "https://downloads.wordpress.org/{$type}/{$post->post_name}.latest-stable.zip";

		if ( 'plugin' === $type ) {
			// Only a review in progress has someone on it; the assignment stays after it's done.
			if ( $show_review && $post->assigned_reviewer && in_array( $post->post_status, array( 'new', 'pending' ), true ) ) {
				$reviewer_user = get_user_by( 'id', (int) $post->assigned_reviewer );
				if ( $reviewer_user ) {
					$reviewer = $reviewer_user->display_name ? $reviewer_user->display_name : $reviewer_user->user_login;
				}
			}

			// Prefer the last_updated post meta.
			$last_modified = $post->last_updated ? $post->last_updated : $last_modified;

			// Get the ZIPs attached, link to the latest for pending/new. Unreleased, so only for reviews.
			if ( in_array( $post->post_status, array( 'new', 'pending' ), true ) ) {
				$attachments   = $show_review ? get_posts(
					array(
						'post_parent'    => $post->ID,
						'post_type'      => 'attachment',
						'orderby'        => 'post_date',
						'order'          => 'DESC',
						'posts_per_page' => 1,
					)
				) : array();
				$download_link = $attachments ? (string) wp_get_attachment_url( $attachments[0]->ID ) : '';
			}

			// Append Info URL.
			if (
				$download_link &&
				$show_review &&
				class_exists( '\WordPressdotorg\Plugin_Directory\API\Routes\Plugin_Review' )
			) {
				$download_link = \WordPressdotorg\Plugin_Directory\API\Routes\Plugin_Review::append_plugin_review_info_url( $download_link, $post );
			}
		}

		$last_updated = (int) strtotime( $last_modified );

		switch ( $post->post_status ) {
			// Plugins.
			case 'rejected':
				$status        = 'Rejected';
				$tone          = 'error';
				$download_link = ''; // No zips exist for rejected plugins.
				break;
			case 'closed':
			case 'disabled':
				$status = ucwords( $post->post_status );
				// This is not perfect, but close enough.
				if ( $show_review && $post->_close_reason ) {
					$status .= ': ' . ucwords( str_replace( '-', ' ', $post->_close_reason ) );
				}
				$tone = 'error';
				break;
			case 'pending':
			case 'new':
				$status = 'In Review';
				$tone   = 'warning';
				break;
			case 'approved':
				$status = 'Approved';
				$tone   = 'success';
				break;

			// Themes.
			case 'draft':
				$status        = 'In Review or Rejected';
				$tone          = 'warning';
				$download_link = ''; // No zips exist for drafts.
				break;
			case 'suspend':
				$status = 'Suspended';
				$tone   = 'error';
				break;
			case 'delist':
				$status = 'Delisted';
				$tone   = 'error';
				break;
		}

		$meta = array(
			esc_html( $post->post_name ),
			sprintf(
				'<span title="%s">Updated %s ago</span>',
				esc_attr( gmdate( 'Y-m-d', $last_updated ) ),
				esc_html( human_time_diff( $last_updated, time() ) )
			),
		);

		if ( $reviewer ) {
			$meta[] = 'Assigned to ' . esc_html( $reviewer );
		}

		// Edit and permalinks are built by hand, as the post types aren't registered on this site.
		$links = sprintf(
			'<a href="%s" title="View on WordPress.org" aria-label="View on WordPress.org"><i class="glyphicon glyphicon-link"></i></a>',
			esc_url( home_url( "/{$post->post_name}/" ) )
		);
		if ( $download_link ) {
			$links .= sprintf(
				' <a href="%s" title="Download" aria-label="Download"><i class="glyphicon glyphicon-download-alt"></i></a>',
				esc_url( $download_link )
			);
		}

		$html .= sprintf(
			'<li class="wporg-sidebar-item"><span class="wporg-sidebar-item-links">%5$s</span><a class="wporg-sidebar-item-title" href="%1$s">%2$s</a> %3$s<div class="wporg-sidebar-item-meta">%4$s</div></li>',
			esc_url(
				add_query_arg(
					array(
						'action' => 'edit',
						'post'   => $post->ID,
					),
					admin_url( 'post.php' )
				)
			),
			esc_html( $post->post_title ),
			$status ? render_badge( $status, $tone ) : '',
			implode( ' · ', $meta ),
			$links
		);
	}

	return $html . '</ul>';
}

send_html( render_plugins_themes( get_request( basename( __FILE__ ) ) ) );
