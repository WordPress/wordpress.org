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

// $request is the validated HelpScout request.
$request = include __DIR__ . '/common.php';

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
			echo '<p><strong>' . ucwords( $type ) . ' owned by this user:</strong></p>';

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
	echo '<ul>';
	foreach ( $post_ids as $post_id ) {
		$post        = get_post( $post_id );
		$post_status = '';
		$style       = 'color: green;';

		switch ( $post->post_status ) {
			// Plugins
			case 'rejected':
				$post_status = '(Rejected)';
				$style       = 'color: red;';
				break;
			case 'closed':
			case 'disabled':
				$post_status = '(Closed)';
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
				$post_status = '(In Review or Rejected)';
				$style       = '';
				break;
			case 'suspended':
				$post_status = '(Suspended)';
				$style       = 'color: red;';
				break;
			case 'delisted':
				$post_status = '(Delisted)';
				$style       = 'color: red;';
				break;
		}

		printf(
			'<li><a href="%1$s" style="%2$s">%3$s</a> <a href="%4$s" style="%2$s">#</a> %5$s</li>',
			/* 1 get_edit_post_link( $post ), // Won't work as post type not registered */
			/* 1 */ esc_url( add_query_arg( [ 'action' => 'edit', 'post' => $post_id ], admin_url( 'post.php' ) ) ),
			/* 2 */ esc_attr( $style ),
			/* 3 */ esc_html( $post->post_title ),
			/* 4 get_permalink( $post ), */
			/* 4 */ esc_url( home_url( "/{$post->post_name}/" ) ),
			/* 5 */ esc_html( $post_status )
		);
	}

	echo '</ul>';
}

// response to HS is just HTML to display in the sidebar
echo json_encode( array( 'html' => ob_get_clean() ) );
