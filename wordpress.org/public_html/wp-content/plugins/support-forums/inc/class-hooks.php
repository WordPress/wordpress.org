<?php

namespace WordPressdotorg\Forums;

class Hooks {

	public function __construct() {
		// Basic behavior filters and actions.
		add_filter( 'bbp_get_forum_pagination_count', '__return_empty_string' );

		// Display-related filters and actions.
		add_filter( 'bbp_get_topic_admin_links', array( $this, 'get_admin_links' ), 10, 3 );
		add_filter( 'bbp_get_reply_admin_links', array( $this, 'get_admin_links' ), 10, 3 );

		// oEmbed.
		add_filter( 'oembed_discovery_links', array( $this, 'disable_oembed_discovery_links' ) );
		add_filter( 'oembed_response_data', array( $this, 'disable_oembed_response_data' ), 10, 2 );
	}

	/**
	 * Remove "Trash" from admin links. Trashing a topic or reply will eventually
	 * permanently delete it when the trash is emptied. Better to mark it as
	 * pending or spam.
	 */
	public function get_admin_links( $retval, $r, $args ) {
		unset( $r['links']['trash'] );

		$links = implode( $r['sep'], array_filter( $r['links'] ) );
		$retval = $r['before'] . $links . $r['after'];

		return $retval;
	}

	/**
	 * Removes oEmbed discovery links for bbPress' post types.
	 *
	 * @param string $output HTML of the discovery links.
	 * @return string Empty string for bbPress' post types, HTML otherwise.
	 */
	public function disable_oembed_discovery_links( $output ) {
		$post_type = get_post_type();
		if ( $post_type && in_array( $post_type, [ bbp_get_forum_post_type(), bbp_get_topic_post_type(), bbp_get_reply_post_type() ] ) ) {
			return '';
		}

		return $output;
	}

	/**
	 * Prevents retrieving oEmbed data for bbPress' post types.
	 *
	 * @param array   $data The response data.
	 * @param WP_Post $post The post object.
	 * @return array|false False for bbPress' post types, array otherwise.
	 */
	public function disable_oembed_response_data( $data, $post ) {
		if ( in_array( $post->post_type, [ bbp_get_forum_post_type(), bbp_get_topic_post_type(), bbp_get_reply_post_type() ] ) ) {
			return false;
		}

		return $data;
	}
}
