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
		add_filter( 'redirect_canonical',              array( $this, 'handle_bbpress_root_pages' ), 10, 2 );
		add_filter( 'old_slug_redirect_post_id',       array( $this, 'disable_wp_old_slug_redirect' ) );
		add_action( 'template_redirect',               array( $this, 'redirect_update_php_page' ) );
		add_action( 'template_redirect',               array( $this, 'redirect_legacy_urls' ), 5 );
		add_action( 'template_redirect',               array( $this, 'redirect_ask_question_plugin_forum' ) );
		add_filter( 'wp_insert_post_data',             array( $this, 'set_post_date_gmt_for_pending_posts' ) );
		add_action( 'wp_print_footer_scripts',         array( $this, 'replace_quicktags_blockquote_button' ) );
		add_filter( 'bbp_show_user_profile',           array( $this, 'allow_mods_to_view_inactive_users' ), 10, 2 );
		add_action( 'init',                            array( $this, 'add_rewrite_rules' ) );


		// Add bbPress support to the WordPress.org SEO plugin.
		add_filter( 'wporg_canonical_base_url', array( $this, 'wporg_canonical_base_url' ) );
		add_filter( 'wporg_canonical_url',      array( $this, 'wporg_canonical_url' ) );
		// Correct the number of pages, to respect the bbPress queries.
		add_filter( 'wporg_rel_next_pages',     array( $this, 'rel_next_prev_max_pages' ) );
		// Add extra conditionals to the noindexing.
		add_filter( 'wporg_noindex_request',    array( $this, 'should_noindex_robots' ) );

		// Output meta description.
		add_action( 'wp_head', array( $this, 'meta_description' ) );

		// Link to create new topics atop topic list.
		add_filter( 'bbp_template_before_pagination_loop', array( $this, 'new_topic_link' ) );

		// Gravatar suppression on lists of topics and revision logs.
		add_filter( 'bbp_after_get_topic_author_link_parse_args', array( $this, 'get_author_link' ) );
		add_filter( 'bbp_after_get_reply_author_link_parse_args', array( $this, 'get_author_link' ) );
		add_filter( 'bbp_after_get_author_link_parse_args',       array( $this, 'get_author_link' ) );

		// Mark avatar images as being lazy-loadable.
		add_filter( 'get_avatar', array( $this, 'avatar_lazy_load' ) );

		// remove nofollow filter from topic and reply author links, since those are wordpress.org profile urls, not user inputs
		remove_filter( 'bbp_get_topic_author_link', 'bbp_rel_nofollow' );
		remove_filter( 'bbp_get_reply_author_link', 'bbp_rel_nofollow' );

		// add ugc to links in topics and replies. These already have nofollow, this adds ugc as well
		add_filter( 'bbp_get_reply_content', array( $this, 'add_rel_ugc' ), 80 );
		add_filter( 'bbp_get_topic_content', array( $this, 'add_rel_ugc' ), 80 );

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

		// Allow topics with the OP adding more details to show up in no-replies view.
		add_filter( 'bbp_register_view_no_replies', array( $this, 'make_no_replies_consider_voices' ), 20 );

		// Remove the description from the CPT to avoid Jetpack using it as the og:description.
		add_filter( 'bbp_register_forum_post_type', array( $this, 'bbp_register_forum_post_type' ) );

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

		// Deindex Support Forum Feeds. bbPress hooks in way earlier than most Core feed functions..
		add_filter( 'request', array( $this, 'deindex_forum_feeds' ), 5 );

		add_filter( 'request', array( $this, 'ignore_empty_query_vars' ) );

		// Support ```...``` and {{{...}}} for code blocks.
		add_filter( 'bbp_new_reply_pre_content',  array( $this, 'support_slack_trac_code_trick' ),  19 );
		add_filter( 'bbp_new_topic_pre_content',  array( $this, 'support_slack_trac_code_trick' ),  19 );
		add_filter( 'bbp_new_forum_pre_content',  array( $this, 'support_slack_trac_code_trick' ),  19 );
		add_filter( 'bbp_edit_reply_pre_content', array( $this, 'support_slack_trac_code_trick' ),  19 );
		add_filter( 'bbp_edit_topic_pre_content', array( $this, 'support_slack_trac_code_trick' ),  19 );
		add_filter( 'bbp_edit_forum_pre_content', array( $this, 'support_slack_trac_code_trick' ),  19 );

		// Freshness links should have the datetime as a title rather than the thread title.
		add_filter( 'bbp_get_topic_freshness_link', array( $this, 'bbp_get_topic_freshness_link' ), 10, 5 );

		// Add a no-reply-to-email suggestion to topic subscription emails
		add_filter( 'bbp_subscription_mail_message', array( $this, 'bbp_subscription_mail_message'), 5, 3 );

		// Don't embed WordPress.org links with anchors included.
		add_filter( 'pre_oembed_result', array( $this, 'pre_oembed_result_dont_embed_wordpress_org_anchors' ), 20, 2 );

		// Break users sessions / passwords when they get blocked, on the main forums only.
		if ( 'wordpress.org' === get_blog_details()->domain ) {
			add_filter( 'bbp_set_user_role', array( $this, 'user_blocked_password_handler' ), 10, 3 );
		}
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
				if ( ! $post ) {
					break;
				}

				// Reviews can be edited at any time.
				if ( 'topic' === $post->post_type && Plugin::REVIEWS_FORUM_ID == $post->post_parent ) {
					break;
				}

				if (
					bbp_past_edit_lock( $post->post_date_gmt )
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
	 * Allow moderators to view anonymized user account details.
	 */
	function allow_mods_to_view_inactive_users( $filter_value, $user_id ) {
		if (
			! $filter_value &&
			bbp_is_user_inactive( $user_id ) &&
			current_user_can( 'moderate' )
		) {
			$filter_value = true;
		}

		return $filter_value;
	}

	/**
	 * Add rewrite rules.
	 *
	 * This function needs to live in this file, so that it's ran no matter what theme is dynamically activated by
	 * the `template` / `stylesheet` callbacks above.
	 */
	function add_rewrite_rules() {
		if ( ! function_exists( 'bbp_get_user_slug' ) ) {
			return;
		}

		// e.g., https://wordpress.org/support/users/foo/edit/account/
		add_rewrite_rule(
			bbp_get_user_slug() . '/([^/]+)/' . bbp_get_edit_slug() . '/account/?$',
			'index.php?' . bbp_get_user_rewrite_id() . '=$matches[1]&edit_account=1',
			'top'
		);
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
	 * Prevent a redirect for some base bbPress pages to random topics.
	 */
	public function handle_bbpress_root_pages( $redirect_url, $requested_url ) {
		$url = str_replace( home_url('/'), '', $requested_url );

		if ( $url && preg_match( '!^(topic|topic-[^/]+|reply|users|view)(/[?]|/?$)!i', $url ) ) {
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

			if ( $hidden_topic &&
				 in_array( $hidden_topic->post_status, array( 'spam', 'pending', 'archived' ) ) &&
				 ! current_user_can( 'read_topic', $hidden_topic->ID )
			   ) {
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
	 * Redirect legacy urls to their new permastructure.
	 *  - /users/$id & /profile/$slug to /users/$slug
	 *  - /users/profile/* => /users/$slug/*
	 *
	 * See also: Support_Compat in inc/class-support-compat.php
	 */
	public function redirect_legacy_urls() {
		global $wp_query, $wp;

		// A user called 'profile' exists, but override it.
		if ( 'profile' === get_query_var( 'bbp_user' ) ) {
			if ( is_user_logged_in() ) {
				$user = wp_get_current_user();
				$url  = str_replace( '/profile/', "/{$user->user_nicename}/", $_SERVER['REQUEST_URI'] );
			} else {
				$url  = wp_login_url( home_url( $wp->request ) );
			}

			wp_safe_redirect( $url );
			exit;
		}

		if ( ! is_404() ) {
			return;
		}

		// Legacy /profile/$user route
		if (
			! empty( $wp_query->query['pagename'] ) &&
			preg_match( '!^profile/(?P<user>.+)(/|$)!', $wp_query->query['pagename'], $m )
		) {
			wp_safe_redirect( home_url( '/users/' . urlencode( $m['user'] ) . '/' ), 301 );
			exit;
		}

		// Legacy /users/$user_id route
		if (
			get_query_var( 'bbp_user' ) &&
			is_numeric( get_query_var( 'bbp_user' ) )
		) {
			$user = get_user_by( 'id', (int) get_query_var( 'bbp_user' ) );
			if ( $user ) {
				wp_safe_redirect( home_url( '/users/' . $user->user_nicename . '/' ), 301 );
				exit;
			}
		}

	}

	/**
	 * Redirect some problematic defunct plugin support forums to the how to / troubleshooting forum
	 * as it's a better destination for the users reaching the plugin forum from search engines.
	 */
	public function redirect_ask_question_plugin_forum() {
		if ( 'plugin' !== get_query_var( 'bbp_view' ) ) {
			return;
		}

		if ( in_array( get_query_var( 'wporg_plugin' ), array( 'ask-question', 'technical-support', 'email' ) ) ) {
			wp_safe_redirect( home_url( '/forum/how-to-and-troubleshooting/' ) );
			exit;

		} elseif ( in_array( get_query_var( 'wporg_plugin' ), array( 'developer' ) ) ) {
			wp_safe_redirect( home_url( '/forum/wp-advanced/' ) );
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
	 * Add bbPress support to the WordPress.org SEO plugin for Canonical locations.
	 */
	public function wporg_canonical_base_url( $canonical_url ) {
		if ( bbp_is_topic_tag() ) {
			$canonical_url = bbp_get_topic_tag_link();
		} elseif ( bbp_is_single_view() ) {
			$canonical_url = bbp_get_view_url();
		} elseif ( bbp_is_forum_archive() ) {
			$canonical_url = get_post_type_archive_link( 'forum' );
		} elseif ( bbp_is_single_topic() ) {
			$canonical_url = bbp_get_topic_permalink();
		} elseif ( bbp_is_single_forum() ) {
			$canonical_url = bbp_get_forum_permalink();
		} elseif ( bbpress()->displayed_user && bbpress()->displayed_user->exists() ) {
			// This covers all user pages rather than using 6 different bbp_is_*() calls.
			$canonical_url  = 'https://profiles.wordpress.org/' . bbpress()->displayed_user->user_nicename . '/';
		}

		return $canonical_url;
	}

	/**
	 * Add bbPress support to the WordPress.org SEO plugin for Canonical locations.
	 */
	public function wporg_canonical_url( $canonical_url ) {
		global $wp_rewrite;

		// profiles links don't support pagination.
		if ( false !== stripos( $canonical_url, 'profiles.wordpress.org' ) ) {
			$canonical_url = remove_query_arg( 'paged', $canonical_url );
			$canonical_url = preg_replace( "#/{$wp_rewrite->pagination_base}/\d+/?($|\?)#", '$1', $canonical_url );
		}

		return $canonical_url;
	}

	/**
	 * Enables the noindex robots headers.
	 */
	public function should_noindex_robots( $robots ) {
		if ( bbp_is_search() ) {
			// #3955
			$robots = true;
		} elseif ( bbp_is_single_user() ) {
			// Blocked users are not indexed.
			if ( bbpress()->displayed_user && bbpress()->displayed_user->has_cap( bbp_get_blocked_role() ) ) {
				$robots = true;

			// Users with no Topics/Replies/Reviews are not indexed.
			} elseif (
				! wporg_support_get_user_topics_count() &&
				! bbp_get_user_reply_count_raw() &&
				! wporg_support_get_user_reviews_count()
			) {
				$robots = true;

			// Noindex all of the single user subpages.
			} elseif ( ! bbp_is_single_user_profile() ) {
				$robots = true;
			}
		} elseif (
			bbp_is_single_view() &&
			! in_array( bbp_get_view_id(), array( 'plugin', 'theme', 'reviews' ) )
		) {
			$robots = true;
		} elseif ( bbp_is_single_view() ) {
			if ( ! bbpress()->topic_query->query ) {
				bbp_view_query(); // Populate bbpress()->topic_query.
			}

			bbpress()->topic_query->is_tax = false;

			// Empty views
			if ( ! bbpress()->topic_query->have_posts() ) {
				$robots = true;
			}
		} elseif ( bbp_is_topic_tag() ) {
			if ( ! bbpress()->topic_query->query ) {
				bbp_has_topics(); // Populate bbpress()->topic_query.
			}

			// Check all threads
			$individually_stale = array_map(
				function( $topic ) {
					// Thread is 'thin' and hasn't been replied to.
					if (
						strlen( $topic->post_content ) <= 100
						&& ! bbp_get_topic_reply_count( $topic->ID, true )
					) {
						return 'stale';
					}

					// Thread is old
					$last_modified_date = get_post_meta( $topic->ID, '_bbp_last_active_time', true ) ?: $topic->post_date;
					if ( strtotime( $last_modified_date ) <= time() - 2*YEAR_IN_SECONDS ) {
						return 'stale';
					}

					return 'fresh';
				},
				bbpress()->topic_query->posts
			);

			// See if all posts 'stale' by checking that no fresh topics exists
			$all_topics_noindexed = array_search( 'fresh', $individually_stale, true ) === false;

			// #4324, #4338
			// Post count is <= 1
			// Last Modified <= -2 years
			// All topics are also noindexed ( See bbp_is_single_topic() logic )
			if (
				bbpress()->topic_query->post_count <= 1 ||
				$all_topics_noindexed
			) {
				$robots = true;
			}

		} elseif ( bbp_is_single_topic() ) {
			if ( ! bbpress()->reply_query->query ) {
				bbp_has_replies(); // Populate bbpress()->reply_query.
			}

			// If no replies and short content - #4283
			if (
				! bbpress()->reply_query->has_posts() &&
				strlen( bbp_get_topic_content() ) <= 100
			) {
				$robots = true;
			}

			// If topic is marked as NSFW.
			$is_nsfw = get_post_meta( bbp_get_topic_id(), '_topic_is_nsfw', true );

			if ( $is_nsfw ) {
				$robots = true;
			}
		}

		return $robots;
	}

	/**
	 * Add bbPress support to the WordPress.org SEO plugin for rel="next|prev" archive tags.
	 */
	public function rel_next_prev_max_pages( $max_pages ) {
		if ( bbp_is_single_view() ) {
			if ( ! bbpress()->topic_query->query ) {
				bbp_view_query();  // Populate bbpress()->topic_query.
			}
			bbpress()->topic_query->is_tax = false;
			$max_pages = bbpress()->topic_query->max_num_pages;

		} elseif ( bbp_is_single_topic() ) {
			if ( ! bbpress()->reply_query->query ) {
				bbp_has_replies(); // Populate bbpress()->reply_query.
			}
			$max_pages = bbpress()->reply_query->max_num_pages;

		} elseif ( bbp_is_single_forum() ) {
			$topic_count = get_post_meta( get_queried_object_id(), '_bbp_topic_count', true );
			if ( $topic_count ) {
				$max_pages = $topic_count / bbp_get_topics_per_page();
			}
		}

		return $max_pages;
	}

	/**
	 * Outputs meta description tags.
	 */
	public function meta_description() {
		$description = '';
		$max_length  = 150;

		// Single topic.
		if ( bbp_is_single_topic() ) {
			$topic_id = bbp_get_topic_id();

			// Prepend label if thread is closed.
			if ( bbp_is_topic_closed( $topic_id ) ) {
				/* translators: %s: Excerpt of the topic's first post. */
				$description = __( '[This thread is closed.] %s', 'wporg-forums' );
			} else {
				$description = '%s '; // trailing space is intentional
			}

			// Determine remaining available description length.
			$length = $max_length - mb_strlen( $description ) + 3; // 3 === strlen( ' %s' )

			// Get the excerpt for the topic's first post (similar to
			// `bbp_get_topic_excerpt()`).
			$excerpt = get_post_field( 'post_excerpt', $topic_id );
			if ( ! $excerpt  ) {
				$excerpt = bbp_get_topic_content( $topic_id );
			}
			// Remove tags, condense whitespace, then trim.
			$excerpt = trim( preg_replace( '/\s+/', ' ', strip_tags( $excerpt ) ) );

			// If excerpt length exceeds description limit, truncate it to end of nearest
			// word or sentence.
			if ( mb_strlen( $excerpt ) > $length ) {
				// Truncate string at max length.
				$excerpt = substr( $excerpt, 0, $length );
				// Reverse string for easier handling.
				$rev = strrev( $excerpt );
				// Find first reasonable and natural break.
				preg_match( '/[\s\.\?!\)\]\}]/', $rev, $match, PREG_OFFSET_CAPTURE );
				$pos = ! empty( $match[0][1] ) ? $match[0][1] : 0;
				// Get the string up to that natural break and reverse it.
				$excerpt = trim( strrev( substr( $rev, $pos ) ) );
				// Append ellipsis.
				$excerpt .= '&hellip;';
			}

			$description = sprintf( $description, $excerpt );
		}

		// Output description meta tags if a description has been set.
		if ( $description ) {
			$description = trim( $description );

			printf( '<meta name="og:description" content="%s" />' . "\n", esc_attr( $description ) );
			printf( '<meta name="description" content="%s" />' . "\n", esc_attr( $description ) );
		}
	}

	/**
	 * Displays a link to the new topic form.
	 */
	public function new_topic_link() {
		if (
			bbp_is_single_forum()
		||
			bbp_is_single_view() && in_array( bbp_get_view_id(), array( 'plugin', 'reviews', 'theme' ) )
		) {
			$btn = null;
			$is_reviews = 'reviews' === bbp_get_view_id();

			if ( bbp_current_user_can_access_create_topic_form() ) {
				$btn = sprintf(
					'<a class="button button-primary create-topic" href="#new-topic-0">%s</a>',
					$is_reviews ? __( 'Create Review', 'wporg-forums' ) : __( 'Create Topic', 'wporg-forums' )
				);
			} elseif ( ! bbp_is_forum_closed() && ! is_user_logged_in() ) {
				$btn = sprintf(
					'<a class="button button-primary create-topic login" href="%s">%s</a>',
					wp_login_url(),
					$is_reviews ? __( 'Log in to Create a Review', 'wporg-forums' ) : __( 'Log in to Create a Topic', 'wporg-forums' )
				);
			}

			if ( $btn ) {
				$searchform = get_search_form( [ 'echo' => false ] );

				echo '<div class="bbp-create-topic-wrapper">';
				if ( $searchform ) {

					// Output create button alongside search form except for reviews, which already have the button in a section rendered above this one.
					if( $is_reviews ) {
						echo $searchform;
					} else {
						echo $searchform;
						echo $btn;
					}
				} else {
					echo $btn;
				}
				echo "</div>\n";
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
			bbp_is_single_view() && in_array( bbp_get_view_id(), array( 'spam', 'pending', 'archived', 'all-replies' ) )
		) {
			return $r;
		}

		if ( ! bbp_is_single_topic() || bbp_is_topic_edit() || wp_is_post_revision( $r['post_id'] ) ) {
			$r['type'] = 'name';
		}

		return $r;
	}

	/**
	 * Change avatar `img` markup to indicate lazy loading of image.
	 */
	public function avatar_lazy_load( $markup ) {
		return str_replace( '<img ', '<img loading="lazy" ', $markup );
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
	 * Add nofollow ugc to links in content, for post-save processing. Compare to wp_rel_ugc().
	 */
	public function add_rel_ugc( $text ) {
		$text = preg_replace_callback(
			'|<a (.+?)>|i',
			function( $matches ) {
				return wp_rel_callback( $matches, 'nofollow ugc' );
			},
			$text
		);
		return $text;
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
	 * Limits No Replies view to 21 days by default and hides closed/resolved topics.
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

		// Exclude closed/hidden/spam/etc topics.
		$args['post_status'] = 'publish';

		return $args;
	}

	/**
	 * Modifies the No Replies view to look at the amount of voices instead of replies.
     *
     * This allows a topic OP to provide additional details without their topic
     * going away from the No Replies view.
	 *
	 * @param array $args Array of query args for the view.
	 * @return array
	 */
	public function make_no_replies_consider_voices( $args ) {
		/*
		 * Remove the default view arguments, in favor of a new meta_query instead.
		 * Looping over an array of defined keys allows us to be forward compatible
		 * if bbPress implements meta queries in the future.
		 */
		$default_keys = array( 'meta_key', 'meta_type', 'meta_value', 'meta_compare' );
		foreach ( $default_keys as $key ) {
			if ( isset( $args[ $key ] ) ) {
				unset( $args[ $key ] );
			}
		}

		$args['meta_query'][] = array(
			'key'     => '_bbp_voice_count',
			'type'    => 'NUMERIC',
			'value'   => 2,
			'compare' => '<',
		);

		return $args;
	}

	/**
	 * Remove the Forum CPT description field to prevent Jetpack using it as the og:description on /forums/.
	 */
	public function bbp_register_forum_post_type( $args ) {
		$args['description'] = '';

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
					printf( '<p class="wporg-bbp-topic-site-url">%1$s <a href="%2$s" rel="nofollow ugc">%2$s</a></p>',
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

			if( wporg_support_is_single_review() ) {
				$page_title = wporg_support_get_single_review_title();

				if( $page_title ) {
					$title['text'] .= sprintf( ' &#45; [%s] %s',
						$page_title,
							__( 'Review', 'wporg-forums' )
						);
				}
			}
		}

		return $title;
	}

	/**
	 * Don't 404 for user profile pages.
	 *
	 * @see https://bbpress.trac.wordpress.org/ticket/3047
	 */
	function disable_404_for_user_profile() {
		if ( bbpress()->displayed_user && bbpress()->displayed_user->exists() ) {
			status_header( 200 );
		}
	}

	/**
	 * Deindex Forum feeds.
	 *
	 * bbPress hooks in way earlier than most Core feed functions, so this is hooked to 'request' at priority 5.
	 */
	function deindex_forum_feeds( $query_vars ) {
		if ( isset( $query_vars['feed'] ) ) {
			header( 'X-Robots-Tag: noindex, follow' );
		}

		return $query_vars;
	}

	/**
	 * Ignore certain empty set query vars.
	 *
	 * TODO: This is probably a bbPress bug in that it doesn't handle "empty" QVs well.
	 */
	function ignore_empty_query_vars( $query_vars ) {
		// bbPress query vars that sometimes have weird urls as a result.
		$ignore_emptyish_values = [ 'topic', 'reply', 'forum', 'topic-tag', 'bbp_view' ];

		foreach ( $ignore_emptyish_values as $q ) {
			if ( isset( $query_vars[ $q ] ) && empty( $query_vars[ $q ] ) ) {
				// It's set, but empty so not a useful QV?
				unset( $query_vars[ $q ] );
			}
		}

		return $query_vars;
	}

	/**
	 * Add support for Slack and Trac style code formatting.
	 *
	 * Upon edit, the blocks will be unwrapped to bbPress style blocks.
	 *
	 * See `bbp_code_trick()` for the regex below.
	 */
	function support_slack_trac_code_trick( $content ) {
		$content = str_replace( array( "\r\n", "\r" ), "\n", $content );

		// Slack style ```...``` Inline and Multiline.
		$content = preg_replace_callback( '|(`)``(.*?)```|', 'bbp_encode_callback', $content );
		$content = preg_replace_callback( "!(^|\n)```(.*?)```!s", 'bbp_encode_callback', $content );

		// Trac style {{{...}}} Inline and Multiline.
		$content = preg_replace_callback( '|({){{(.*?)}}}|', function( $matches ) {
			 // bbPress expects `...` for inline code blocks.
			$matches[1] = '`';
			return bbp_encode_callback( $matches );
		}, $content );
		$content = preg_replace_callback( "!(^|\n){{{(.*?)}}}!s", 'bbp_encode_callback', $content );

		return $content;
	}

	/**
	 * Alter the bbPress topic freshness links to use the date in the title attribute rather than thread title.
	 */
	public function bbp_get_topic_freshness_link( $anchor, $topic_id, $time_since, $link_url, $title ) {

		// Copy-paste from bbp_get_topic_last_active_time() which only returns humanized times.
		// Try to get the most accurate freshness time possible
		$last_active = get_post_meta( $topic_id, '_bbp_last_active_time', true );
		if ( empty( $last_active ) ) {
			$last_active = get_post_field( 'post_date', $topic_id );
		}

		// This is for translating the date components. $last_active is based on non-gmt fields, so the timezone must be passed.
		$datetime = date_create_immutable_from_format( 'Y-m-d H:i:s', $last_active, wp_timezone() );
		if ( ! $datetime ) {
			return $anchor;
		}

		$date = wp_date( get_option( 'date_format' ), $datetime->getTimestamp() );
		$time = wp_date( get_option( 'time_format' ), $datetime->getTimestamp() );

		return str_replace(
			'title="' . esc_attr( $title ) . '"',
			'title="' . esc_attr(
				// bbPress string from bbp_get_reply_post_date()
				sprintf( _x( '%1$s at %2$s', 'date at time', 'wporg-forums' ), $date, $time )
			) . '"',
			$anchor
		);
	}

	/**
	 * Filter the topic subscription message to
	 */
	public function bbp_subscription_mail_message( $message, $reply_id, $topic_id ) {
		$reply_author_name = bbp_get_reply_author_display_name( $reply_id );

		remove_all_filters( 'bbp_get_reply_content' );

		// Strip tags from text and set up message body.
		$reply_content = strip_tags( bbp_get_reply_content( $reply_id ) );
		$reply_url = bbp_get_reply_url( $reply_id );

		$message = sprintf( __( '%1$s wrote:

%2$s

Post Link: %3$s
-----------
You are receiving this email because you subscribed to a forum topic.

Log in and visit the topic to reply to the topic or unsubscribe from these emails. Note that replying to this email has no effect.', 'wporg-forums' ),
                $reply_author_name,
                $reply_content,
                $reply_url
        );

		return $message;
	}

	/**
	 * Don't embed WordPress.org links when anchors are included.
	 *
	 * NOTE: `$return` will have HTML when the link points to the current site, as WordPress uses
	 *       the same filter to preempt HTTP requests, ignoring any earlier filtered results.
	 *
	 * TODO: It may be better wanted to inject the actual hash into the oEmbed iframe instead of
	 *       disabling this rich embed entirely.
	 *
	 * @see https://meta.trac.wordpress.org/ticket/4346
	 */
	public function pre_oembed_result_dont_embed_wordpress_org_anchors( $return, $url ) {
		// Match WordPress.org + Subdomains which includes a Hash character in the URL.
		if ( preg_match( '!^https?://([^/]+[.])?wordpress[.]org/.*#!i', $url ) ) {
			$return = false;
		}

		return $return;
	}

	/**
	 * Catch a user being blocked / unblocked and set their password appropriately.
	 *
	 * Note: This method is called even when the users role is not changed.
	 *
	 * See Audit_Log class for where the note is set/updated.
	 */
	public function user_blocked_password_handler( $new_role, $user_id, \WP_User $user ) {
		global $wpdb;

		// ~~~ is a reset password on WordPress.org. Let's ignore those.
		if ( '~~~' === $user->user_pass ) {
			return $new_role;
		}

		// bbPress 1.x used `{$user_pass}---{$secret}` while we're using the reverse here.
		// This is to ensure that anything that uses the password hash as part of a cookie no longer validates.
		$blocked_prefix  = 'BLOCKED' . substr( wp_hash( 'bb_break_password' ), 0, 13 ) . '---';
		$blocked_role    = bbp_get_blocked_role();
		$password_broken = ( 0 === strpos( $user->user_pass, $blocked_prefix ) );

		// WP_User::has_role() does not exist, and WP_User::has_cap( 'bbp_blocked' ) will be truthful for super admins.
		$user_has_blocked_role = ! empty( $user->roles ) && in_array( $blocked_role, $user->roles, true );

		if (
			( $blocked_role === $new_role || $user_has_blocked_role ) &&
			! $password_broken
		) {
			// User has been blocked, break their password and sessions.
			// WordPress doesn't have a way to edit a user password without re-hashing it.
			$wpdb->update(
				$wpdb->users,
				array(
					'user_pass' => $blocked_prefix . $user->user_pass,
				),
				array(
					'ID' => $user->ID
				)
			);

			clean_user_cache( $user );

			// Destroy all of their WordPress sessions.
			$manager = \WP_Session_Tokens::get_instance( $user->ID );
			$manager->destroy_all();
		} else if (
			$password_broken &&
			! $user_has_blocked_role
		) {
			// User was blocked (broken password) but no longer is.
			// WordPress doesn't have a way to edit a user password without re-hashing it.
			$wpdb->update(
				$wpdb->users,
				array(
					'user_pass' => substr( $user->user_pass, strlen( $blocked_prefix ) ),
				),
				array(
					'ID' => $user->ID
				)
			);

			clean_user_cache( $user );
		}

		// It's a filter, return the value.
		return $new_role;
	}

}
