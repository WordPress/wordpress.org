<?php
/**
 * FreeScout Plugin Review panel: a plugin as it is now, by its ID or slug.
 *
 * @package WordPressdotorg\API\FreeScout
 */

declare( strict_types = 1 );

namespace WordPressdotorg\API\FreeScout;

// Run as the plugin directory, such that any filters are correct.
$wp_init_host = 'https://wordpress.org/plugins/';
require __DIR__ . '/common.php';

/**
 * A plugin's security scans; %s is its slug.
 *
 * @var string
 */
const SCANS_URL = 'https://gandalf.wordpress.org/admin/subjects/plugin/%s?tab=runs';

/**
 * Gets a plugin as the Plugin Review panel shows it: its current name, slug, and status, its ZIP, and who submitted it.
 *
 * Reviews are the plugins team's, so it only answers for their mailbox. A review email's plugin ID is in its Review ID
 * line, which FreeScout's WPOrgPluginReview module reads; reviews that don't carry it are looked up by their slug.
 *
 * @param object $request Request payload: plugin_id, slug, and mailbox.
 * @return array The plugin; null if there's none with the ID or slug, or the conversation isn't the plugins team's.
 */
function render_plugin_review( object $request ): array {
	if ( ! str_starts_with( strtolower( (string) ( $request->mailbox->email ?? '' ) ), 'plugins' ) ) {
		return array( 'plugin' => null );
	}

	$post = find_plugin( (int) ( $request->plugin_id ?? 0 ), (string) ( $request->slug ?? '' ) );
	if ( ! $post ) {
		return array( 'plugin' => null );
	}

	// An approved plugin isn't in SVN until its author commits it.
	$released  = ! in_array( $post->post_status, array( 'new', 'pending', 'approved', 'rejected' ), true );
	$submitter = get_user_by( 'id', (int) $post->post_author );

	return array(
		'plugin' => array(
			'id'           => (int) $post->ID,
			// Stored escaped, and shown as text.
			'name'         => html_entity_decode( $post->post_title, ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
			'slug'         => $post->post_name,
			'status'       => $post->post_status,
			'status_label' => get_plugin_status_label( $post, true ),
			// Its code is in SVN, so it can be checked out and scanned.
			'released'     => $released,
			// Edit and permalinks are built by hand, as the post types aren't registered on this site.
			'edit_url'     => add_query_arg(
				array(
					'action' => 'edit',
					'post'   => $post->ID,
				),
				admin_url( 'post.php' )
			),
			'page_url'     => home_url( "/{$post->post_name}/" ),
			'download_url' => get_plugin_download_url( $post, true ),
			// Only releases are scanned.
			'scans_url'    => $released ? sprintf( SCANS_URL, rawurlencode( $post->post_name ) ) : '',
			'submitter'    => $submitter ? array(
				'username' => $submitter->user_login,
				'email'    => $submitter->user_email,
			) : null,
			'reviewer'     => get_assigned_reviewer( $post ),
		),
	);
}

/**
 * Finds a plugin by its ID or, without one, by its slug, whatever its status.
 *
 * @param int    $plugin_id Plugin ID, 0 if unknown.
 * @param string $slug      Slug, for when the ID is unknown.
 * @return \WP_Post|null
 */
function find_plugin( int $plugin_id, string $slug ): ?\WP_Post {
	if ( $plugin_id ) {
		$post = get_post( $plugin_id );
	} elseif ( '' !== $slug && class_exists( '\WordPressdotorg\Plugin_Directory\Plugin_Directory' ) ) {
		$post = \WordPressdotorg\Plugin_Directory\Plugin_Directory::get_plugin_post( $slug );
	} else {
		$post = null;
	}

	return $post instanceof \WP_Post && 'plugin' === $post->post_type ? $post : null;
}

wp_send_json( render_plugin_review( get_request( basename( __FILE__ ) ) ) );
