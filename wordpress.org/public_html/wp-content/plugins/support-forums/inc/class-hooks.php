<?php

namespace WordPressdotorg\Forums;

class Hooks {

	public function __construct() {
		// Basic behavior filters and actions.
		add_filter( 'bbp_get_forum_pagination_count', '__return_empty_string' );

		// Display-related filters and actions.
		add_filter( 'bbp_get_topic_admin_links', array( $this, 'get_admin_links' ), 10, 3 );
		add_filter( 'bbp_get_reply_admin_links', array( $this, 'get_admin_links' ), 10, 3 );

		// Gravatar suppression on lists of topics.
		add_filter( 'bbp_after_get_topic_author_link_parse_args', array( $this, 'get_author_link' ) );
		add_filter( 'bbp_after_get_reply_author_link_parse_args', array( $this, 'get_author_link' ) );

		// oEmbed.
		add_filter( 'oembed_discovery_links', array( $this, 'disable_oembed_discovery_links' ) );
		add_filter( 'oembed_response_data', array( $this, 'disable_oembed_response_data' ), 10, 2 );
		add_filter( 'embed_oembed_discover', '__return_false' );

		// Disable inline terms and mentions.
		add_action( 'plugins_loaded', array( $this, 'disable_inline_terms' ) );

		// Add notice to reply forms for privileged users in closed forums.
		add_action( 'bbp_template_notices', array( $this, 'closed_forum_notice_for_moderators' ), 1 );
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
	 * Suppress Gravatars on lists of topics.
	 */
	public function get_author_link( $r ) {
		if ( ! bbp_is_single_topic() || bbp_is_topic_edit() ) {
			$r['type'] = 'name';
		}
		return $r;
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

	/**
	 * Disable the inline terms and mentions, if they are enabled.
	 * Inline terms and mentions are for O2 and should not be running on the support forums.
	 * If this plugin is moved out of mu-plugins, this function can be removed as well.
	 *
	 * This fixes the post editing screens in the admin area on the support forums.
	 */
	public function disable_inline_terms() {
		remove_action( 'init', array( 'Jetpack_Inline_Terms', 'init' ) );
		remove_action( 'init', array( 'Jetpack_Mentions', 'init' ) );
	}

	/**
	 * For closed forums, adds a notice to privileged users indicating that
	 * though the reply form is available, the forum is closed.
	 *
	 * Otherwise, unless the topic itself is closed, there is no indication that
	 * the reply form is only available because of their privileged capabilities.
	 */
	public function closed_forum_notice_for_moderators() {
		if (
			is_single()
			&&
			bbp_current_user_can_access_create_reply_form()
			&&
			bbp_is_forum_closed( bbp_get_topic_forum_id() )
			&&
			! bbp_is_reply_edit()
		) {
			$err_msg = sprintf( esc_html__(
				'The forum &#8216;%s&#8217; is closed to new topics and replies, however your posting capabilities still allow you to do so.',
				'wporg-forums'),
				bbp_get_forum_title( bbp_get_topic_forum_id() )
			);

			bbp_add_error( 'bbp_forum_is_closed', $err_msg, 'message' );
		}
	}

}
