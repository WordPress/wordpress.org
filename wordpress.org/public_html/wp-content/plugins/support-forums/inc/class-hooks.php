<?php

namespace WordPressdotorg\Forums;

class Hooks {

	const SITE_URL_META = '_wporg_bbp_topic_site_url';

	public function __construct() {
		// Basic behavior filters and actions.
		add_filter( 'bbp_get_forum_pagination_count',  '__return_empty_string' );
		add_filter( 'bbp_get_form_topic_subscribed',   array( $this, 'check_topic_subscription_checkbox' ) );
		add_action( 'pre_get_posts',                   array( $this, 'hide_non_public_forums' ) );
		add_filter( 'pre_option__bbp_edit_lock',       array( $this, 'increase_edit_lock_time' ) );
		add_filter( 'pre_option__bbp_topics_per_page', array( $this, 'increase_topics_per_page' ) );
		add_filter( 'bbp_map_meta_caps',               array( $this, 'disallow_editing_past_lock_time' ), 10, 4 );
		add_filter( 'redirect_canonical',              array( $this, 'disable_redirect_guess_404_permalink' ) );
		add_filter( 'old_slug_redirect_post_id',       array( $this, 'disable_wp_old_slug_redirect' ) );
		add_action( 'template_redirect',               array( $this, 'redirect_update_php_page' ) );
		add_filter( 'wp_insert_post_data',             array( $this, 'set_post_date_gmt_for_pending_posts' ) );
		add_action( 'wp_print_footer_scripts',         array( $this, 'replace_quicktags_blockquote_button' ) );

		// Output rel="canonical" meta tag. Runs before WP's rel_canonical to unhook that if needed.
		add_action( 'wp_head', array( $this, 'rel_canonical' ), 9 );

		// Link to create new topics atop topic list.
		add_filter( 'bbp_template_before_pagination_loop', array( $this, 'new_topic_link' ) );

		// Gravatar suppression on lists of topics and revision logs.
		add_filter( 'bbp_after_get_topic_author_link_parse_args', array( $this, 'get_author_link' ) );
		add_filter( 'bbp_after_get_reply_author_link_parse_args', array( $this, 'get_author_link' ) );
		add_filter( 'bbp_after_get_author_link_parse_args',       array( $this, 'get_author_link' ) );

		// oEmbed.
		add_filter( 'oembed_discovery_links', array( $this, 'disable_oembed_discovery_links' ) );
		add_filter( 'oembed_response_data',   array( $this, 'disable_oembed_response_data' ), 10, 2 );
		add_filter( 'embed_oembed_discover',  '__return_false' );

		// Disable inline terms and mentions.
		add_action( 'plugins_loaded', array( $this, 'disable_inline_terms' ) );

		// Replace bbp_make_mentions_clickable() to add `class="mention"`.
		remove_filter( 'bbp_make_clickable', 'bbp_make_mentions_clickable', 8 );
		add_filter( 'bbp_make_clickable', array( $this, 'make_mentions_clickable' ), 8 );

		// Fix login url links
		add_filter( 'login_url', array( $this, 'fix_login_url' ), 10, 3 );

		// Auto-close topics after a certain number of months since the last reply.
		add_filter( 'bbp_is_topic_closed', array( $this, 'auto_close_old_topics' ), 10, 2 );

		// Limit no-replies view to a certain number of days and hide resolved topics.
		add_filter( 'bbp_register_view_no_replies', array( $this, 'limit_no_replies_view' ) );

		// Display extra topic fields after content.
		add_action( 'bbp_theme_after_topic_content', array( $this, 'display_extra_topic_fields' ) );

		// Add extra topic fields after Title field in topic form.
		add_action( 'bbp_theme_after_topic_form_title', array( $this, 'add_extra_topic_fields' ) );

		// Process extra topic fields.
		add_action( 'bbp_new_topic',  array( $this, 'handle_extra_topic_fields' ), 10, 2 );
		add_action( 'bbp_edit_topic', array( $this, 'handle_extra_topic_fields' ), 10, 2 );

		// Add extra reply actions before Submit button in reply form.
		add_action( 'bbp_theme_before_reply_form_submit_wrapper', array( $this, 'add_extra_reply_actions' ) );

		// Process extra reply actions.
		add_action( 'bbp_new_reply',  array( $this, 'handle_extra_reply_actions' ), 10, 2 );
		add_action( 'bbp_edit_reply', array( $this, 'handle_extra_reply_actions' ), 10, 2 );

		// Update topic replies count if the reply status changes on editing.
		add_filter( 'bbp_edit_reply_pre_insert', array( $this, 'update_replies_count_on_editing_reply' ), 20 );

		// Honor i18n number formatting.
		add_filter( 'bbp_number_format', array( $this, 'number_format_i18n' ), 10, 5 );

		// Edit quicktags for reply box
		add_filter( 'bbp_get_quicktags_settings', array( $this, 'quicktags_settings' ) );

		// Limit pagination of the 'all-topics' view
		add_filter( 'bbp_after_has_topics_parse_args', array( $this, 'has_topics_all_topics' ) );

		// Remove the redundant prefixes in the bbPress <title>.
		add_filter( 'bbp_raw_title_array', array( $this, 'bbp_raw_title_array' ) );

		// Don't 404 user profile pages. Fixed in bbPress 2.6: https://bbpress.trac.wordpress.org/ticket/3047
		add_filter( 'bbp_template_redirect', array( $this, 'disable_404_for_user_profile' ) );

	}

	/**
	 * Check "Notify me of follow-up replies via email" box for new topics by default.
	 *
	 * If the user has enabled "Always notify me via email of follow-up posts" option
	 * in their profile, check the box for new replies as well.
	 *
	 * @param string $checked Checked value of topic subscription.
	 * @return string Checked value of topic subscription.
	 */
	public function check_topic_subscription_checkbox( $checked ) {
		if (
			bbp_is_single_forum() || bbp_is_single_view()
		||
			get_user_option( 'auto_topic_subscription' )
		) {
			$checked = checked( true, true, false );
		}

		return $checked;
	}

	/**
	 * Remove non-public forums from lists on front end.
	 *
	 * By default, bbPress shows all forums to keymasters, including private and
	 * hidden forums. This ensures that front-end queries include only public forums.
	 *
	 * @param WP_Query $query Current query object.
	 */
	public function hide_non_public_forums( $query ) {
		if ( ! is_admin() && 'forum' === $query->get( 'post_type' ) ) {
			$query->set( 'post_status', 'publish' );
		}
	}

	/**
	 * Increase bbPress' default edit lock time from 5 minutes to 1 hour.
	 *
	 * @return int Filtered edit lock time.
	 */
	public function increase_edit_lock_time() {
		return 60;
	}

	/**
	 * Increase bbPress' default topics per page setting from 15 to 30.
	 *
	 * @return int Filtered topics per page setting.
	 */
	public function increase_topics_per_page() {
		return 30;
	}

	/**
	 * Disallow editing topics or replies past edit lock time for non-moderators.
	 *
	 * @see https://bbpress.trac.wordpress.org/ticket/3164
	 *
	 * @param array  $caps    User's actual capabilities.
	 * @param string $cap     Capability name.
	 * @param int    $user_id Current user ID.
	 * @param array  $args    Capability context, typically the object ID.
	 * @return array Filtered capabilities.
	 */
	function disallow_editing_past_lock_time( $caps, $cap, $user_id, $args ) {
		switch ( $cap ) {
			case 'edit_topic':
			case 'edit_reply':
				$post = get_post( $args[0] );

				// Reviews can be edited at any time.
				if ( 'topic' === $post->post_type && Plugin::REVIEWS_FORUM_ID == $post->post_parent ) {
					break;
				}

				if (
					$post && bbp_past_edit_lock( $post->post_date_gmt )
				&&
					! user_can( $user_id, 'moderate', $post->ID )
				) {
					$caps = array( 'do_not_allow' );
				}

				break;
		}

		return $caps;
	}

	/**
	 * Disable redirect_guess_404_permalink() for hidden topics.
	 *
	 * Prevents Spam, Pending, or Archived topics that the current user cannot view
	 * from performing a redirect to other unrelated topics.
	 *
	 * @param string $redirect_url The redirect URL.
	 * @return string Filtered redirect URL.
	 */
	public function disable_redirect_guess_404_permalink( $redirect_url ) {
		if ( is_404() && 'topic' === get_query_var( 'post_type' ) && get_query_var( 'name' ) ) {
			$hidden_topic = get_posts( array(
				'name'        => get_query_var( 'name' ),
				'post_type'   => 'topic',
				'post_status' => array( 'spam', 'pending', 'archived' ),
			) );
			$hidden_topic = reset( $hidden_topic );

			if ( $hidden_topic && ! current_user_can( 'read_topic', $hidden_topic->ID ) ) {
				$redirect_url = false;
			}
		}

		// Avoid redirecting the old slug of "Update PHP" page to a forum topic.
		if ( is_404() && 'upgrade-php' === get_query_var( 'pagename' ) ) {
			$redirect_url = false;
		}

		return $redirect_url;
	}

	/**
	 * Disable wp_old_slug_redirect() for hidden topics.
	 *
	 * Prevents Spam, Pending, or Archived topics that the current user cannot view
	 * from performing a redirect loop.
	 *
	 * @param int $post_id The redirect post ID.
	 * @return int Filtered redirect post ID.
	 */
	public function disable_wp_old_slug_redirect( $post_id ) {
		if ( is_404() && 'topic' === get_query_var( 'post_type' ) && get_query_var( 'name' ) ) {
			$hidden_topic = get_post( $post_id );

			if ( $hidden_topic && ! current_user_can( 'read_topic', $hidden_topic->ID ) ) {
				$post_id = 0;
			}
		}

		return $post_id;
	}

	/**
	 * Redirect "Update PHP" page from the old slug to the new one.
	 *
	 * The old slug is 'upgrade-php', the new one is 'update-php'.
	 */
	public function redirect_update_php_page() {
		if ( is_404() && 'upgrade-php' === get_query_var( 'pagename' ) ) {
			wp_redirect( home_url( '/update-php/' ), 301 );
			exit;
		}
	}

	/**
	 * Keep the original post date when approving a pending post.
	 *
	 * Sets a non-empty 'post_date_gmt' for pending posts to prevent wp_update_post()
	 * from overwriting the post date on approving.
	 *
	 * @see https://bbpress.trac.wordpress.org/ticket/3133
	 *
	 * @param array $data An array of post data.
	 * @return array Filtered post data.
	 */
	public function set_post_date_gmt_for_pending_posts( $data ) {
		if (
			in_array( $data['post_type'], array( 'topic', 'reply' ) )
		&&
			'pending' === $data['post_status']
		&&
			'0000-00-00 00:00:00' === $data['post_date_gmt']
		) {
			$data['post_date_gmt'] = get_gmt_from_date( $data['post_date'] );
		}

		return $data;
	}

	/**
	 * Replace Quicktags' blockquote button to remove extra line breaks
	 * before and after the tag.
	 */
	public function replace_quicktags_blockquote_button() {
		if ( ! wp_script_is( 'quicktags' ) ) {
			return;
		}
		?>
		<script type="text/javascript">
			if ( 'undefined' !== typeof edButtons && 'undefined' !== QTags ) {
				// Replace Quicktags' blockquote button.
				edButtons[40]  = new QTags.TagButton(
					'block',         // Button HTML ID.
					'b-quote',       // Button's value="...".
					'<blockquote>',  // Starting tag.
					'</blockquote>', // Ending tag.
					'',              // Deprecated, not used.
					'',              // Button's title="...".
					'',              // Quicktags instance.
					{                // Additional attributes.
						ariaLabel: quicktagsL10n.blockquote,
						ariaLabelClose: quicktagsL10n.blockquoteClose
					}
				);
			}
		</script>
		<?php
	}

	/**
	 * Outputs <link rel="canonical"> tags for various pages.
	 */
	public function rel_canonical() {
		$canonical_url = false;
		if ( bbp_is_topic_tag() ) {
			$canonical_url = bbp_get_topic_tag_link();
		} elseif ( bbp_is_single_view() ) {
			$canonical_url = bbp_get_view_url();
		} elseif ( bbp_is_forum_archive() ) {
			$canonical_url = get_post_type_archive_link( 'forum' );
		} elseif ( bbp_is_single_topic() ) {
			remove_action( 'wp_head', 'rel_canonical' ); // Doesn't handle pagination.
			$canonical_url = bbp_get_topic_permalink();
		} elseif ( bbp_is_single_forum() ) {
			remove_action( 'wp_head', 'rel_canonical' ); // Doesn't handle pagination.
			$canonical_url = bbp_get_forum_permalink();
		} elseif ( bbpress()->displayed_user && bbpress()->displayed_user->exists() ) {
			// This covers all user pages rather than using 6 different bbp_is_*() calls.
			$canonical_url = 'https://profiles.wordpress.org/' . bbpress()->displayed_user->user_nicename . '/';
		}

		// Make sure canonical has pagination if needed.
		$page = get_query_var( 'paged', 0 );
		if ( $canonical_url && $page >= 2 ) {
			$canonical_url .= 'page/' . absint( $page ) . '/';
		}

		if ( $canonical_url ) {
			echo '<link rel="canonical" href="' . esc_url( $canonical_url ) . '" />' . "\n";
		}
	}

	/**
	 * Displays a link to the new topic form.
	 */
	public function new_topic_link() {
		if ( bbp_is_single_forum() ) {
			if ( bbp_current_user_can_access_create_topic_form() ) {
				printf(
					'<a class="button create-topic" href="#new-topic-0">%s</a>',
					__( 'Create Topic', 'wporg-forums' )
				);
			} elseif ( ! bbp_is_forum_closed() && ! is_user_logged_in() ) {
				printf(
					'<a class="button create-topic login" href="%s">%s</a>',
					wp_login_url(),
					__( 'Login to Create a Topic', 'wporg-forums' )
				);
			}

			remove_filter( 'bbp_template_before_pagination_loop', array( $this, 'new_topic_link' ) );
		}
	}

	/**
	 * Suppress Gravatars on lists of topics and revision logs.
	 */
	public function get_author_link( $r ) {
		// Keep Gravatars in single topics or replies, search results, and moderator views.
		if (
			bbp_is_single_topic() || bbp_is_single_reply() || bbp_is_search_results()
		||
			bbp_is_single_view() && in_array( bbp_get_view_id(), array( 'spam', 'pending', 'archived' ) )
		) {
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
	public function make_mentions_clickable( $text = '' ) {
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
	public function make_mentions_clickable_callback( $matches = array() ) {

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
	 * Auto-close topics after 6 months since the last reply.
	 *
	 * @param bool $is_topic_closed Whether the topic is closed.
	 * @return bool True if closed, false if not.
	 */
	public function auto_close_old_topics( $is_topic_closed, $topic_id ) {
		if ( $is_topic_closed ) {
			return $is_topic_closed;
		}

		$last_active_post_date = get_post_field( 'post_date', bbp_get_topic_last_active_id( $topic_id ) );

		if ( ( time() - strtotime( $last_active_post_date ) ) / MONTH_IN_SECONDS >= 6 ) {
			$is_topic_closed = true;
		}

		return $is_topic_closed;
	}

	/**
	 * Limits No Replies view to 21 days by default and hides resolved topics.
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

		$args['meta_query'] = array( array(
			'key'     => 'topic_resolved',
			'type'    => 'CHAR',
			'value'   => 'no',
			'compare' => '='
		) );

		return $args;
	}

	/**
	 * Display extra topic fields after content.
	 */
	public function display_extra_topic_fields() {
		$topic_id = bbp_get_topic_id();

		if ( Plugin::REVIEWS_FORUM_ID != bbp_get_topic_forum_id( $topic_id ) ) {
			$site_url = get_post_meta( $topic_id, self::SITE_URL_META, true );

			if ( $site_url ) {
				// Display site URL for logged-in users only.
				if ( is_user_logged_in() ) {
					printf( '<p class="wporg-bbp-topic-site-url">%1$s <a href="%2$s" rel="nofollow">%2$s</a></p>',
						__( 'The page I need help with:', 'wporg-forums' ),
						esc_url( $site_url )
					);
				} else {
					printf( '<p class="wporg-bbp-topic-site-url">%1$s <em>%2$s</em></p>',
						__( 'The page I need help with:', 'wporg-forums' ),
						sprintf( __( '[<a href="%s">log in</a> to see the link]', 'wporg-forums' ), wp_login_url() )
					);
				}
			}
		}
	}

	/**
	 * Add extra topic fields after Title field in topic form.
	 */
	public function add_extra_topic_fields() {
		$topic_id = bbp_get_topic_id();

		if (
			Plugin::REVIEWS_FORUM_ID != bbp_get_topic_forum_id( $topic_id )
		&&
			( ! bbp_is_single_view() || 'reviews' !== bbp_get_view_id() )
		) :
			$site_url = ( bbp_is_topic_edit() ) ? get_post_meta( $topic_id, self::SITE_URL_META, true ) : '';
			?>
			<p>
				<label for="site_url"><?php _e( 'Link to the page you need help with:', 'wporg-forums' ) ?></label><br />
				<input type="text" id="site_url" value="<?php echo esc_attr( $site_url ); ?>" size="40" name="site_url" maxlength="400" aria-describedby="site_url_description" /><br />
				<em id="site_url_description"><?php _e( 'This link will only be shown to logged-in users.', 'wporg-forums' ); ?></em>
			</p>
			<?php
		endif;
	}

	/**
	 * Process extra topic fields.
	 *
	 * @param int $topic_id Topic ID.
	 */
	public function handle_extra_topic_fields( $topic_id ) {
		// Handle "URL of the site or page you need help with" field.
		if ( isset( $_POST['site_url'] ) ) {
			if ( Plugin::REVIEWS_FORUM_ID == bbp_get_topic_forum_id( $topic_id ) ) {
				return;
			}

			$site_url = esc_url_raw( apply_filters( 'pre_user_url', $_POST['site_url'] ) );

			if ( $site_url ) {
				$protocols = implode( '|', array( 'http', 'https' ) );
				if ( ! preg_match( '/^(' . $protocols . '):/is', $site_url ) ) {
					$site_url = 'http://' . $site_url;
				}

				update_post_meta( $topic_id, self::SITE_URL_META, $site_url );
			} elseif ( 'bbp_edit_topic' === current_action() ) {
				delete_post_meta( $topic_id, self::SITE_URL_META );
			}
		}
	}

	/**
	 * Add extra reply actions before Submit button in reply form.
	 */
	public function add_extra_reply_actions() {
		$topic_id = bbp_get_topic_id();

		if ( class_exists( 'WordPressdotorg\Forums\Topic_Resolution\Plugin' ) ) :
			$topic_resolution_plugin = Topic_Resolution\Plugin::get_instance();

			if (
				$topic_resolution_plugin->is_enabled_on_forum()
			&&
				$topic_resolution_plugin->user_can_resolve( get_current_user_id(), $topic_id )
			&&
				'yes' !== $topic_resolution_plugin->get_topic_resolution( array( 'id' => $topic_id ) )
			) : ?>
				<p>
					<input name="bbp_reply_mark_resolved" id="bbp_reply_mark_resolved" type="checkbox" value="yes" />
					<label for="bbp_reply_mark_resolved"><?php esc_html_e( 'Reply and mark as resolved', 'wporg-forums' ); ?></label>
				</p>
				<?php
			endif;
		endif;

		if ( current_user_can( 'moderate', $topic_id ) && ! bbp_is_topic_closed( $topic_id ) ) : ?>
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
		// Handle "Reply and mark as resolved" checkbox.
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

		// Handle "Reply and close the topic" checkbox.
		if ( isset( $_POST['bbp_reply_close_topic'] ) && 'yes' === $_POST['bbp_reply_close_topic'] ) {
			if ( current_user_can( 'moderate', $topic_id ) && bbp_is_topic_open( $topic_id ) ) {
				bbp_close_topic( $topic_id );
			}
		}
	}

	/**
	 * Update topic replies count if the reply status changes on editing.
	 *
	 * This is neccesary to properly account for the status change as a result of
	 * Akismet check or user flagging rather than an explicit moderator action.
	 *
	 * @see https://bbpress.trac.wordpress.org/ticket/3132
	 *
	 * @param array $data Reply post data.
	 * @return array Filtered reply data.
	 */
	public function update_replies_count_on_editing_reply( $data ) {
		// Bail if the reply is not published.
		if ( 'publish' !== get_post_status( $data['ID'] ) ) {
			return $data;
		}

		// Bail if the new status is not pending or spam.
		if ( ! in_array( $data['post_status'], array( 'pending', 'spam' ) ) ) {
			return $data;
		}

		$topic_id = bbp_get_reply_topic_id( $data['ID'] );

		bbp_update_topic_last_reply_id( $topic_id );
		bbp_update_topic_last_active_id( $topic_id );
		bbp_update_topic_last_active_time( $topic_id );
		bbp_update_topic_voice_count( $topic_id );

		bbp_decrease_topic_reply_count( $topic_id );
		bbp_increase_topic_reply_count_hidden( $topic_id );

		return $data;
	}

	/**
	 * Override `bbp_number_format()` to behave like `bbp_number_format_i18n()` under default conditions.
	 *
	 * bbPress uses `bbp_number_format()` in lots of places for display.
	 *
	 * @param string $formatted_number Formatted number.
	 * @param string $number           Number before formatting.
	 * @param bool   $decimals         Display decimals?
	 * @param int    $dec_point        Decimal point character.
	 * @param int    $thousands_sep    Thousands separator character.
	 * @return string
	 */
	public function number_format_i18n( $formatted_number, $number, $decimals, $dec_point, $thousands_sep ) {
		// Format number for i18n unless non-default decimal point or thousands separator provided.
		if ( '.' === $dec_point && ',' === $thousands_sep ) {
			$formatted_number = bbp_number_format_i18n( $number, $decimals );
		}

		return $formatted_number;
	}

	/**
	 * Remove tags from quicktags that don't work for the forums, such as img.
	 *
	 * @param array $settings quicktags settings array
	 * @return array
	 */
	public function quicktags_settings( $settings ) {

		$tags = explode( ',', $settings['buttons'] );
		$tags = array_diff( $tags, array('img') );
		$settings['buttons'] = implode( ',', $tags );

		return $settings;
	}

	/**
	 * Optimize the all-topics view by not fetching the found rows, and limiting it to 99 pages displayed.
	 * In the event the site has more than 99 pages, it's cached for a week, else a day.
	 *
	 * @link https://meta.trac.wordpress.org/ticket/3414
	 */
	public function has_topics_all_topics( $r ) {
		if ( bbp_is_single_view() && 'all-topics' === bbp_get_view_id() ) {
			if ( get_transient( __CLASS__ . '_total_all-topics' ) ) {
				// We already know how many pages this site has.
				$r['no_found_rows'] = true;
			}
			add_filter( 'bbp_topic_pagination', array( $this, 'forum_pagination_all_topics' ) );
		}

		return $r;
	}

	public function forum_pagination_all_topics( $r ) {
		$pages = get_transient( __CLASS__ . '_total_all-topics' );
		if ( ! $pages ) {
			set_transient( __CLASS__ . '_total_all-topics', $r['total'], $r['total'] > 99 ? WEEK_IN_SECONDS : DAY_IN_SECONDS );
		}

		$r['total'] = min( 99, $pages );

		return $r;
	}

	/**
	 * Remove the redundant prefixes in the bbPress <title>.
	 *
	 * @param array $title The title format
	 * @return array
	 */
	public function bbp_raw_title_array( $title ) {
		if ( bbp_is_single_forum() || bbp_is_single_topic() || bbp_is_single_view() ) {
			$title['format'] = '%s';
		}

		return $title;
	}

	/**
	 * Don't 404 for user profile pages. Fixed in bbPress 2.6: https://bbpress.trac.wordpress.org/ticket/3047
	 */
	function disable_404_for_user_profile() {
		if ( bbpress()->displayed_user && bbpress()->displayed_user->exists() ) {
			status_header( 200 );
		}
	}
}
