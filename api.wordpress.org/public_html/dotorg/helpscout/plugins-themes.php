<?php
namespace WordPressdotorg\API\HelpScout;
/**
 * Simple sidebar to list plugin/theme details.
 */

/*

Plugins mentioned in this email:
 - Plugin 1
 - Plugin 2

Themes mentioned in this email:
 - Theme 3

Plugins owned by this user:
 - Plugin 1

Themes owned by this user:
 - Theme 3

*/

// Run as the plugin directory, such that any filters are correct.
$wp_init_host = 'https://wordpress.org/plugins/';

include __DIR__ . '/common.php';

// $request is the validated HelpScout request.
$request = get_request();

// default empty output
ob_start();

// look up profile url by email
$email = get_user_email_for_email( $request );
$user  = get_user_by( 'email', $email );

$sites = [
	'themes'  => WPORG_THEME_DIRECTORY_BLOGID,
	'plugins' => WPORG_PLUGIN_DIRECTORY_BLOGID,
];
$repo_post_types = [
	'themes'  => 'repopackage',
	'plugins' => 'plugin',
];

// Display plugins first in the plugins inbox.
if ( str_starts_with( $request->mailbox->email ?? '' , 'plugins' ) ) {
	$sites           = array_reverse( $sites );
	$repo_post_types = array_reverse( $repo_post_types );
}

// Mentioned in email
$mentioned = get_plugin_or_theme_from_email( $request );
foreach ( $mentioned as $type => $slugs ) {
	switch_to_blog( $sites[ $type ] );

	$post_ids = get_posts( [
		'fields'        => 'ids',
		'post_name__in' => $slugs,
		'post_type'     => $repo_post_types, // Cannot be 'all', as that only queries for known post_type
		'post_status'   => 'any',
		'orderby'       => 'post_title',
		'order'         => 'ASC',
	] );

	if ( $post_ids ) {
		echo '<p><strong>' . ucwords( $type ) . ' mentioned in this email:</strong></p>';

		display_items( $post_ids );

		echo '<br/>';
	}

	restore_current_blog();
}

// Owned by user.
if ( $user ) {
	foreach ( $sites as $type => $_blog_id ) {
		switch_to_blog( $_blog_id );

		$items = get_user_items( $user );
		if ( $items ) {
			$url = add_query_arg( [ 'post_type' => $repo_post_types[ $type ], 'author' => $user->ID ], admin_url( 'edit.php' ) );
			echo '<p><strong><a href="' . esc_url( $url ) . '">' . ucwords( $type ) . ' owned by this user:</a></strong></p>';

			display_items( $items );

			echo '<br/>';
		}

		restore_current_blog();
	}
}

function get_user_items( $user ) {
	global $wpdb;

	if ( ! $user ) {
		return [];
	}

	$ids    = [];
	$slugs  = [];
	$wheres = [];

	if ( WPORG_PLUGIN_DIRECTORY_BLOGID === get_current_blog_id() ) {
		// Committer to a plugin.
		$committer_plugins = $wpdb->get_col( $wpdb->prepare( 'SELECT path FROM `' . PLUGINS_TABLE_PREFIX . 'svn_access' . '` WHERE user = %s', $user->user_login ) );

		foreach ( $committer_plugins as $plugin ) {
			$plugin = ltrim( $plugin, '/' );
			if ( $plugin ) {
				$slugs[] = $plugin;
			}
		}
	}

	if ( $slugs ) {
		$slugs    = '"' . implode( '", "', array_map( 'esc_sql', array_unique( $slugs ) ) ) . '"';
		$wheres[] = "post_name IN( {$slugs} )";
	}

	$wheres[] = $wpdb->prepare( "post_author = %d", $user->ID );

	if ( $wheres ) {
		$where = implode( ' OR ', $wheres );

		$ids = $wpdb->get_col(
			"SELECT ID
			FROM $wpdb->posts
			WHERE post_type IN( 'plugin', 'repopackage' ) AND ( {$where} )
			ORDER BY FIELD( post_status, 'new', 'pending', 'publish', 'disabled', 'delisted', 'closed', 'approved', 'suspended', 'rejected', 'draft' ), post_title",
		);
	}

	return $ids;
}

function display_items( $post_ids ) {
	global $request;

	echo '<ul>';
	foreach ( $post_ids as $post_id ) {
		$post          = get_post( $post_id );
		$type          = ( 'plugin' === $post->post_type ) ? 'plugin' : 'theme';
		$post_status   = '';
		$style         = 'color: green;';
		$reviewer      = false;
		$last_modified = $post->post_modified_gmt;
		$download_link = "https://downloads.wordpress.org/{$type}/{$post->post_name}.latest-stable.zip";

		if ( 'plugin' === $type ) {
			if ( $post->assigned_reviewer ) {
				$reviewer_user = get_user_by( 'id', $post->assigned_reviewer );
				$reviewer      = $reviewer_user->display_name ?: $reviewer_user->user_login;
			}

			// Prefer the last_updated post meta.
			$last_modified = $post->last_updated ?: $last_modified;

			// Get the ZIPs attached, link to the latest for pending/new.
			if ( in_array( $post->post_status, [ 'new', 'pending' ] ) ) {
				$attachments = get_posts( [
					'post_parent'    => $post_id,
					'post_type'      => 'attachment',
					'orderby'        => 'post_date',
					'order'          => 'DESC',
					'posts_per_page' => 1,
				] );
				$download_link = $attachments ? wp_get_attachment_url( $attachments[0]->ID ) : '';
			}

			// Append Info URL.
			if (
				$download_url &&
				str_starts_with( $request->mailbox->email ?? '' , 'plugins' ) &&
				class_exists( '\WordPressdotorg\Plugin_Directory\API\Routes\Plugin_Review' )
			) {
				$download_url = \WordPressdotorg\Plugin_Directory\API\Routes\Plugin_Review::append_plugin_review_info_url( $download_url, $post );
			}
		}

		$last_updated       = human_time_diff( strtotime( $last_modified ), time() );
		$short_last_updated = str_ireplace(
			[ ' seconds', ' second', ' hours', ' hour', ' days', ' day', ' weeks', ' week', ' months', ' month', ' years', ' year' ],
			[ 's', 's', 'h', 'h', 'd', 'd', 'w', 'w', 'm', 'm', 'y', 'y' ],
			$last_updated
		);

		switch ( $post->post_status ) {
			// Plugins
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

			// Themes
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

		printf(
			'<li>
				<a href="%1$s" style="%2$s">%3$s</a>&nbsp;
				<a href="%4$s" style="%2$s">#</a>&nbsp;
				<a href="%5$s" style="%2$s">ↆ</a>&nbsp;%6$s<br>
				<span style="%2$s">%7$s</span>&nbsp;
				%8$s
			</li>',
			/* 1: get_edit_post_link( $post ), // Won't work as post type not registered. */
			/* 1: Edit link */ esc_url( add_query_arg( [ 'action' => 'edit', 'post' => $post_id ], admin_url( 'post.php' ) ) ),
			/* 2: The HTML style for the links */ esc_attr( $style ),
			/* 3: The title */ esc_html( $post->post_title ),
			/* 4: get_permalink( $post ), // Won't work as post type is not properly registered. */
			/* 4: Permalink */ esc_url( home_url( "/{$post->post_name}/" ) ),
			/* 5: Download link */ esc_attr( $download_link ),
			/* 6: Last Updated diff */ esc_html( $short_last_updated ),
			/* 7: slug */ esc_html( $post->post_name ),
			/* 8: The actual text */ esc_html( $post_status )
		);
	}

	echo '</ul>';
}

// response to HS is just HTML to display in the sidebar
echo json_encode( array( 'html' => ob_get_clean() ) );
