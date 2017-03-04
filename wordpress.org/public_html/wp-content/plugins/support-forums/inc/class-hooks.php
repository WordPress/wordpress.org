<?php

namespace WordPressdotorg\Forums;

class Hooks {

	public function __construct() {
		// Basic behavior filters and actions.
		add_filter( 'bbp_get_forum_pagination_count', '__return_empty_string' );

		// Display-related filters and actions.
		add_filter( 'bbp_topic_admin_links', array( $this, 'admin_links' ), 10, 3 );
		add_filter( 'bbp_reply_admin_links', array( $this, 'admin_links' ), 10, 3 );

		// Gravatar suppression on lists of topics and revision logs.
		add_filter( 'bbp_after_get_topic_author_link_parse_args', array( $this, 'get_author_link' ) );
		add_filter( 'bbp_after_get_reply_author_link_parse_args', array( $this, 'get_author_link' ) );
		add_filter( 'bbp_after_get_author_link_parse_args',       array( $this, 'get_author_link' ) );

		// oEmbed.
		add_filter( 'oembed_discovery_links', array( $this, 'disable_oembed_discovery_links' ) );
		add_filter( 'oembed_response_data', array( $this, 'disable_oembed_response_data' ), 10, 2 );
		add_filter( 'embed_oembed_discover', '__return_false' );

		// Disable inline terms and mentions.
		add_action( 'plugins_loaded', array( $this, 'disable_inline_terms' ) );

		// Replace bbp_make_mentions_clickable() to add `class="mention"`.
		remove_filter( 'bbp_make_clickable', 'bbp_make_mentions_clickable', 8 );
		add_filter( 'bbp_make_clickable', array( $this, 'make_mentions_clickable' ), 8 );

		// Fix login url links
		add_filter( 'login_url', array( $this, 'fix_login_url' ), 10, 3 );

		// Limit no-replies view to certain number of days.
		add_filter( 'bbp_register_view_no_replies', array( $this, 'limit_no_replies_view' ) );

		// Add extra reply actions before Submit button in reply form.
		add_action( 'bbp_theme_before_reply_form_submit_wrapper', array( $this, 'add_extra_reply_actions' ) );

		// Process extra reply actions.
		add_action( 'bbp_new_reply',  array( $this, 'handle_extra_reply_actions' ), 10, 2 );
		add_action( 'bbp_edit_reply', array( $this, 'handle_extra_reply_actions' ), 10, 2 );

		// Store moderator's username on approve/unapprove actions.
		add_action( 'bbp_approved_topic',   array( $this, 'store_moderator_username' ) );
		add_action( 'bbp_approved_reply',   array( $this, 'store_moderator_username' ) );
		add_action( 'bbp_unapproved_topic', array( $this, 'store_moderator_username' ) );
		add_action( 'bbp_unapproved_reply', array( $this, 'store_moderator_username' ) );
	}

	/**
	 * Remove some unneeded or redundant admin links for topics and replies,
	 * move less commonly used inline quick links to 'Topic Admin' sidebar section.
	 *
	 * @param array $r       Admin links array.
	 * @param int   $post_id Topic or reply ID.
	 * @return array Filtered admin links array.
	 */
	public function admin_links( $r, $post_id ) {
		/*
		 * Remove 'Trash' from admin links. Trashing a topic or reply will eventually
		 * permanently delete it when the trash is emptied. Better to mark it as pending or spam.
		 */
		unset( $r['trash'] );

		/*
		 * Remove 'Reply' link. The theme adds its own 'Reply to Topic' sidebar link
		 * for quick access to reply form, making the default inline link redundant.
		 */
		unset( $r['reply'] );

		/*
		 * The following actions are removed from inline quick links as less commonly used,
		 * but are still available via 'Topic Admin' sidebar section.
		 */
		if ( ! did_action( 'wporg_compat_single_topic_sidebar_pre' ) ) {
			// Remove 'Merge' link.
			unset( $r['merge'] );

			// Remove 'Stick' link for moderators, but keep it for plugin/theme authors and contributors.
			if ( current_user_can( 'moderate', $post_id ) ) {
				unset( $r['stick'] );
			}
		}

		return $r;
	}

	/**
	 * Suppress Gravatars on lists of topics and revision logs.
	 */
	public function get_author_link( $r ) {
		// Keep Gravatars in search results and moderator views.
		if ( bbp_is_search_results() || bbp_is_single_view() && in_array( bbp_get_view_id(), array( 'spam', 'pending', 'archived' ) ) ) {
			return $r;
		}

		if ( ! bbp_is_single_topic() || bbp_is_topic_edit() || wp_is_post_revision( $r['post_id'] ) ) {
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
	 * Make mentions clickable in content areas.
	 *
	 * @param string $text Topic or reply content.
	 * @return string Filtered content.
	 */
	function make_mentions_clickable( $text = '' ) {
		return preg_replace_callback( '#([\s>])@([0-9a-zA-Z-_]+)#i', array( $this, 'make_mentions_clickable_callback' ), $text );
	}

	/**
	 * Callback to convert mention matches to HTML A tag.
	 *
	 * Replaces bbp_make_mentions_clickable_callback() to add `class="mention"`
	 * for styling purposes.
	 *
	 * @see https://meta.trac.wordpress.org/ticket/2542
	 * @see https://bbpress.trac.wordpress.org/ticket/3074
	 *
	 * @param array $matches Single Regex Match.
	 * @return string HTML A tag with link to user profile.
	 */
	function make_mentions_clickable_callback( $matches = array() ) {

		// Get user; bail if not found
		$user = get_user_by( 'slug', $matches[2] );
		if ( empty( $user ) || bbp_is_user_inactive( $user->ID ) ) {
			return $matches[0];
		}

		// Create the link to the user's profile
		$url    = bbp_get_user_profile_url( $user->ID );
		$anchor = '<a href="%1$s" class="mention" rel="nofollow">@%2$s</a>';
		$link   = sprintf( $anchor, esc_url( $url ), esc_html( $user->user_nicename ) );

		return $matches[1] . $link;
	}

	/**
	 * Adjust the login URL to point back to whatever part of the support forums we're
	 * currently looking at. This allows the redirect to come back to the same place
	 * instead of the main /support URL by default.
	 */
	public function fix_login_url( $login_url, $redirect, $force_reauth ) {
		// modify the redirect_to for the support forums to point to the current page
		if ( 0 === strpos($_SERVER['REQUEST_URI'], '/support' ) ) {
			// Note that this is not normal because of the code in /mu-plugins/wporg-sso/class-wporg-sso.php.
			// The login_url function there expects the redirect_to as the first parameter passed into it instead of the second
			// Since we're changing this with a filter on login_url, then we have to change the login_url to the
			// place we want to redirect instead, and then let the SSO plugin do the rest.
			//
			// If the SSO code gets fixed, this will need to be modified.
			//
			// parse_url is used here to remove any additional query args from the REQUEST_URI before redirection
			// The SSO code handles the urlencoding of the redirect_to parameter
			$url_parts = parse_url( set_url_scheme( 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] ) );
			$constructed_url = $url_parts['scheme'] . '://' . $url_parts['host'] . (isset($url_parts['path'])?$url_parts['path']:'');

			if ( class_exists( 'WPOrg_SSO' ) ) {
				$login_url = $constructed_url;
			} else {
				$login_url = add_query_arg( 'redirect_to', urlencode( $constructed_url ), $login_url );
			}
		}
		return $login_url;
	}

	/**
	 * Limits No Replies view to 21 days by default.
	 *
	 * @param array $args Array of query args for the view.
	 * @return array
	 */
	public function limit_no_replies_view( $args ) {
		$days = 21;

		if ( isset( $_GET['days'] ) ) {
			$days = (int) $_GET['days'];
		}

		$args['date_query'] = array(
			array(
				'after'  => sprintf( '%s days ago', $days ),
			),
		);

		return $args;
	}

	/**
	 * Add extra reply actions before Submit button in reply form.
	 */
	public function add_extra_reply_actions() {
		if ( class_exists( 'WordPressdotorg\Forums\Topic_Resolution\Plugin' ) ) :
			$topic_resolution_plugin = Topic_Resolution\Plugin::get_instance();

			if ( $topic_resolution_plugin->is_enabled_on_forum() && $topic_resolution_plugin->user_can_resolve( get_current_user_id(), bbp_get_topic_id() ) ) : ?>
				<p>
					<input name="bbp_reply_mark_resolved" id="bbp_reply_mark_resolved" type="checkbox" value="yes" />
					<label for="bbp_reply_mark_resolved"><?php esc_html_e( 'Reply and mark as resolved', 'wporg-forums' ); ?></label>
				</p>
				<?php
			endif;
		endif;

		if ( current_user_can( 'moderate', bbp_get_topic_id() ) ) : ?>
			<p>
				<input name="bbp_reply_close_topic" id="bbp_reply_close_topic" type="checkbox" value="yes" />
				<label for="bbp_reply_close_topic"><?php esc_html_e( 'Reply and close the topic', 'wporg-forums' ); ?></label>
			</p>
			<?php
		endif;
	}

	/**
	 * Process extra reply actions.
	 *
	 * @param int $reply_id Reply ID.
	 * @param int $topic_id Topic ID.
	 */
	public function handle_extra_reply_actions( $reply_id, $topic_id ) {
		// Handle "Reply and mark as resolved" checkbox
		if ( isset( $_POST['bbp_reply_mark_resolved'] ) && 'yes' === $_POST['bbp_reply_mark_resolved'] ) {
			if ( class_exists( 'WordPressdotorg\Forums\Topic_Resolution\Plugin' ) ) {
				$topic_resolution_plugin = Topic_Resolution\Plugin::get_instance();

				$plugin_enabled   = $topic_resolution_plugin->is_enabled_on_forum( bbp_get_topic_forum_id( $topic_id ) );
				$user_can_resolve = $topic_resolution_plugin->user_can_resolve( get_current_user_id(), $topic_id );

				if ( $plugin_enabled && $user_can_resolve ) {
					$topic_resolution_plugin->set_topic_resolution( array(
						'id'         => $topic_id,
						'resolution' => 'yes',
					) );
				}
			}
		}

		// Handle "Reply and close the topic" checkbox
		if ( isset( $_POST['bbp_reply_close_topic'] ) && 'yes' === $_POST['bbp_reply_close_topic'] ) {
			if ( current_user_can( 'moderate', $topic_id ) && bbp_is_topic_open( $topic_id ) ) {
				bbp_close_topic( $topic_id );
			}
		}
	}

	/**
	 * Store moderator's username on approve/unapprove actions.
	 *
	 * @param int $post_id Post ID.
	 */
	public function store_moderator_username( $post_id ) {
		update_post_meta( $post_id, '_wporg_bbp_moderator', wp_get_current_user()->user_login );
	}

}
