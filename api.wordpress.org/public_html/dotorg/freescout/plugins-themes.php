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

	foreach ( get_plugin_or_theme_from_email( $request ) as $type => $slugs ) {
		switch_to_blog( $sites[ $type ] );

		$post_ids = get_items_by_slug( $slugs );

		if ( $post_ids ) {
			$html .= '<p><strong>' . esc_html( ucwords( $type ) ) . ' mentioned in this email:</strong></p>';
			$html .= render_items( $post_ids, $mailbox_email );
			$html .= '<br/>';
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

				$html .= '<p><strong><a href="' . esc_url( $url ) . '">' . esc_html( ucwords( $type ) ) . ' owned by this user:</a></strong></p>';
				$html .= render_items( $post_ids, $mailbox_email );
				$html .= '<br/>';
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

	if ( ! $slugs ) {
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID
				FROM %i
				WHERE post_type IN( 'plugin', 'repopackage' ) AND post_author = %d
				ORDER BY FIELD( post_status, 'new', 'pending', 'publish', 'disabled', 'delisted', 'closed', 'approved', 'suspended', 'rejected', 'draft' ), post_title",
				$wpdb->posts,
				$user->ID
			)
		);
	} else {
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID
				FROM %i
				WHERE post_type IN( 'plugin', 'repopackage' ) AND ( post_author = %d OR post_name IN( " . implode( ', ', array_fill( 0, count( $slugs ), '%s' ) ) . " ) )
				ORDER BY FIELD( post_status, 'new', 'pending', 'publish', 'disabled', 'delisted', 'closed', 'approved', 'suspended', 'rejected', 'draft' ), post_title",
				array_merge( array( $wpdb->posts, $user->ID ), $slugs )
			)
		);
	}

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
	$html = '<ul>';

	foreach ( $post_ids as $post_id ) {
		$post          = get_post( (int) $post_id );
		$type          = ( 'plugin' === $post->post_type ) ? 'plugin' : 'theme';
		$post_status   = '';
		$style         = 'color: green;';
		$reviewer      = false;
		$last_modified = $post->post_modified_gmt;
		$download_link = "https://downloads.wordpress.org/{$type}/{$post->post_name}.latest-stable.zip";

		if ( 'plugin' === $type ) {
			if ( $post->assigned_reviewer ) {
				$reviewer_user = get_user_by( 'id', (int) $post->assigned_reviewer );
				if ( $reviewer_user ) {
					$reviewer = $reviewer_user->display_name ? $reviewer_user->display_name : $reviewer_user->user_login;
				}
			}

			// Prefer the last_updated post meta.
			$last_modified = $post->last_updated ? $post->last_updated : $last_modified;

			// Get the ZIPs attached, link to the latest for pending/new.
			if ( in_array( $post->post_status, array( 'new', 'pending' ), true ) ) {
				$attachments   = get_posts(
					array(
						'post_parent'    => $post->ID,
						'post_type'      => 'attachment',
						'orderby'        => 'post_date',
						'order'          => 'DESC',
						'posts_per_page' => 1,
					)
				);
				$download_link = $attachments ? (string) wp_get_attachment_url( $attachments[0]->ID ) : '';
			}

			// Append Info URL.
			if (
				$download_link &&
				str_starts_with( $mailbox_email, 'plugins' ) &&
				class_exists( '\WordPressdotorg\Plugin_Directory\API\Routes\Plugin_Review' )
			) {
				$download_link = \WordPressdotorg\Plugin_Directory\API\Routes\Plugin_Review::append_plugin_review_info_url( $download_link, $post );
			}
		}

		$short_last_updated = str_ireplace(
			array( ' seconds', ' second', ' hours', ' hour', ' days', ' day', ' weeks', ' week', ' months', ' month', ' years', ' year' ),
			array( 's', 's', 'h', 'h', 'd', 'd', 'w', 'w', 'm', 'm', 'y', 'y' ),
			human_time_diff( (int) strtotime( $last_modified ), time() )
		);

		switch ( $post->post_status ) {
			// Plugins.
			case 'rejected':
				$post_status   = '(Rejected)';
				$style         = 'color: red;';
				$download_link = '#'; // No zips exist for rejected plugins.
				break;
			case 'closed':
			case 'disabled':
				$post_status = ucwords( $post->post_status );
				// This is not perfect, but close enough.
				if ( $post->_close_reason ) {
					$post_status .= ': ' . ucwords( str_replace( '-', ' ', $post->_close_reason ) );
				}
				$post_status = "({$post_status})";
				$style       = 'color: red;';
				break;
			case 'pending':
			case 'new':
				$post_status = '(In Review)';
				$style       = '';
				break;
			case 'approved':
				$post_status = '(Approved)';
				$style       = '';
				break;

			// Themes.
			case 'draft':
				$post_status   = '(In Review or Rejected)';
				$style         = '';
				$download_link = '#'; // No zips exist for drafts.
				break;
			case 'suspend':
				$post_status = '(Suspended)';
				$style       = 'color: red;';
				break;
			case 'delist':
				$post_status = '(Delisted)';
				$style       = 'color: red;';
				break;
		}

		// Append assigned to, if known.
		if ( $reviewer ) {
			$post_status = str_replace( ')', ", Assigned to {$reviewer})", $post_status );
		}

		// Edit and permalinks are built by hand, as the post types aren't registered on this site.
		$html .= sprintf(
			'<li>
				<a href="%1$s" style="%2$s">%3$s</a>&nbsp;
				<a href="%4$s" style="%2$s">#</a>&nbsp;
				<a href="%5$s" style="%2$s">ↆ</a>&nbsp;%6$s<br>
				<span style="%2$s">%7$s</span>&nbsp;
				%8$s
			</li>',
			esc_url(
				add_query_arg(
					array(
						'action' => 'edit',
						'post'   => $post->ID,
					),
					admin_url( 'post.php' )
				)
			),
			esc_attr( $style ),
			esc_html( $post->post_title ),
			esc_url( home_url( "/{$post->post_name}/" ) ),
			esc_url( $download_link ),
			esc_html( $short_last_updated ),
			esc_html( $post->post_name ),
			esc_html( $post_status )
		);
	}

	return $html . '</ul>';
}

send_html( render_plugins_themes( get_request() ) );
