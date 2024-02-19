<?php

namespace WordPressdotorg\Forums;

class Users {

	public function __construct() {
		// Add custom fields to user's profile.
		add_action( 'bbp_user_edit_after_name',         array( $this, 'add_custom_title_input' ) );
		add_action( 'bbp_user_edit_after',              array( $this, 'add_options_section_header' ), 0 );
		add_action( 'bbp_user_edit_after',              array( $this, 'add_auto_topic_subscription_checkbox' ) );

		// Save custom field values.
		add_action( 'personal_options_update',          array( $this, 'save_custom_fields' ), 10, 2 );
		add_action( 'edit_user_profile_update',         array( $this, 'save_custom_fields' ), 10, 2 );

		// Adjust display of user fields
		add_filter( 'bbp_get_displayed_user_field',     array( $this, 'modify_user_fields' ), 10, 3 );

		// Custom user contact methods.
		add_filter( 'user_contactmethods',              array( $this, 'custom_contact_methods' ) );

		// Add "My Account" submenu items to admin bar for quick access.
		add_action( 'admin_bar_menu',                   array( $this, 'add_my_account_submenu_items' ) );

		// Only allow 3 published topics from a user in the first 24 hours.
		add_action( 'bbp_new_topic_pre_insert',         array( $this, 'limit_new_user_topics' ) );

		// Add query vars and rewrite rules for user's topic and review queries.
		add_filter( 'query_vars',                       array( $this, 'add_query_vars' ) );
		add_action( 'bbp_add_rewrite_rules',            array( $this, 'add_rewrite_rules' ) );

		// Don't allow attempting to set an email to one that is banned-from-use on WordPress.org.
		add_action( 'bbp_post_request',                 array( $this, 'check_email_safe_for_use' ), 0 ); // bbPress is at 1

		// Parse user's topic and review queries.
		add_action( 'parse_query',                      array( $this, 'parse_user_topics_query' ) );
		add_filter( 'posts_groupby',                    array( $this, 'parse_user_topics_posts_groupby' ), 10, 2 );
		add_filter( 'bbp_after_has_topics_parse_args',  array( $this, 'parse_user_topics_query_args' ) );
		add_filter( 'bbp_after_has_replies_parse_args', array( $this, 'parse_user_replies_query_args' ) );
		add_filter( 'bbp_topic_pagination',             array( $this, 'parse_user_topics_pagination_args' ) );
		add_filter( 'bbp_replies_pagination',           array( $this, 'parse_user_topics_pagination_args' ) );
		add_filter( 'bbp_before_title_parse_args',      array( $this, 'parse_user_topics_title_args' ) );

		// Clear user's topics and reviews count cache.
		add_action( 'bbp_new_topic',                    array( $this, 'clear_user_topics_count_cache' ) );
		add_action( 'bbp_spammed_topic',                array( $this, 'clear_user_topics_count_cache' ) );
		add_action( 'bbp_unspammed_topic',              array( $this, 'clear_user_topics_count_cache' ) );
		add_action( 'bbp_approved_topic',               array( $this, 'clear_user_topics_count_cache' ) );
		add_action( 'bbp_unapproved_topic',             array( $this, 'clear_user_topics_count_cache' ) );
		add_action( 'wporg_bbp_archived_topic',         array( $this, 'clear_user_topics_count_cache' ) );
		add_action( 'wporg_bbp_unarchived_topic',       array( $this, 'clear_user_topics_count_cache' ) );

		// Add bulk topic unsubscribe.
		add_action( 'bbp_template_before_user_subscriptions', array( $this, 'bulk_topic_unsubscribe_process' ) );
		add_action( 'bbp_template_after_user_subscriptions', array( $this, 'bulk_topic_unsubscribe' ) );
	}

	/**
	 * Custom contact methods
	 *
	 * @link https://codex.wordpress.org/Plugin_API/Filter_Reference/user_contactmethods
	 *
	 * @param array $user_contact_method Array of contact methods.
	 * @return array An array of contact methods.
	 */
	public function custom_contact_methods( $user_contact_method ) {
		/* Remove legacy user contact methods */
		unset( $user_contact_method['aim'] );
		unset( $user_contact_method['yim'] );
		unset( $user_contact_method['jabber'] );

		return $user_contact_method;
	}

	/**
	 * Add a Custom Title input (only available to moderators) to user's profile.
	 */
	public function add_custom_title_input() {
		if ( ! current_user_can( 'moderate' ) ) {
			return;
		}

		$title = get_user_option( 'title', bbp_get_displayed_user_id() );
		?>
        <div>
            <label for="title"><?php esc_html_e( 'Custom Title', 'wporg-forums' ); ?></label>
            <input type="text" name="title" id="title" value="<?php echo esc_attr( $title ); ?>" class="regular-text" />
        </div>
		<?php
	}

	/**
	 * Add a section header to the extra user options.
	 */
	public function add_options_section_header() {
		printf(
			'<h2 id="user-settings" class="entry-title">%s</h2>',
			esc_html__( 'User Options', 'wporg-forums' )
		);
	}

	/**
	 * Add an auto topic subscription checkbox to user's profile.
	 */
	public function add_auto_topic_subscription_checkbox() {
		$auto_topic_subscription = get_user_option( 'auto_topic_subscription', bbp_get_displayed_user_id() );
		?>
        <p>
            <input name="auto_topic_subscription" id="auto_topic_subscription" type="checkbox" value="yes" <?php checked( $auto_topic_subscription ); ?> />
            <label for="auto_topic_subscription"><?php esc_html_e( 'Always notify me via email of follow-up posts in any topics I reply to', 'wporg-forums' ); ?></label>
        </p>
		<?php
	}

	/**
	 * Save custom field values.
	 *
	 * @param int $user_id The user ID.
	 */
	public function save_custom_fields( $user_id ) {
		if ( current_user_can( 'moderate' ) && isset( $_POST['title'] ) ) {
			update_user_option( $user_id, 'title', sanitize_text_field( $_POST['title'] ) );
		}

		$auto_topic_subscription = isset( $_POST['auto_topic_subscription'] );
		update_user_option( $user_id, 'auto_topic_subscription', $auto_topic_subscription );
	}

	public function modify_user_fields( $value, $field, $filter ) {
		if ( $field === 'description'  && $filter === 'display' ) {
			if ( ! is_user_logged_in() ) {
				$value = '';
			} else {
				$value = bbp_rel_nofollow( $value );
			}
		}
		return $value;
	}

	/**
	 * Add "My Account" submenu items to admin bar for quick access.
	 *
	 * @param WP_Admin_Bar $wp_admin_bar WP_Admin_Bar instance, passed by reference.
	 */
	function add_my_account_submenu_items( $wp_admin_bar ) {
		$user_id = bbp_get_current_user_id();

		$wp_admin_bar->add_group( array(
			'parent' => 'my-account',
			'id'     => 'user-topics',
			'meta'   => array(
				'class' => 'ab-sub-secondary',
			),
		) );

		$wp_admin_bar->add_menu( array(
			'parent' => 'user-topics',
			'id'     => 'topics-started',
			'title'  => __( 'Topics Started', 'wporg-forums' ),
			'href'   => bbp_get_user_topics_created_url( $user_id ),
		) );

		$wp_admin_bar->add_menu( array(
			'parent' => 'user-topics',
			'id'     => 'replies-created',
			'title'  => __( 'Replies Created', 'wporg-forums' ),
			'href'   => bbp_get_user_replies_created_url( $user_id ),
		) );

		if ( defined( 'WPORG_SUPPORT_FORUMS_BLOGID' ) && WPORG_SUPPORT_FORUMS_BLOGID == get_current_blog_id() ) {
			$wp_admin_bar->add_menu( array(
				'parent' => 'user-topics',
				'id'     => 'reviews-written',
				'title'  => __( 'Reviews Written', 'wporg-forums' ),
				'href'   => bbp_get_user_profile_url( $user_id ) . 'reviews/',
			) );
		}

		$wp_admin_bar->add_menu( array(
			'parent' => 'user-topics',
			'id'     => 'replied-to',
			'title'  => __( 'Topics Replied To', 'wporg-forums' ),
			'href'   => bbp_get_user_profile_url( $user_id ) . 'replied-to/',
		) );

		if ( function_exists( 'bbp_is_engagements_active' ) && bbp_is_engagements_active() ) {
			$wp_admin_bar->add_menu( array(
				'parent' => 'user-topics',
				'id'     => 'engagements',
				'title'  => __( 'Engagements', 'wporg-forums' ),
				'href'   => bbp_get_user_engagements_url( $user_id ),
			) );
		}

		$wp_admin_bar->add_menu( array(
			'parent' => 'user-topics',
			'id'     => 'subscriptions',
			'title'  => __( 'Subscriptions', 'wporg-forums' ),
			'href'   => bbp_get_subscriptions_permalink( $user_id ),
		) );

		$wp_admin_bar->add_menu( array(
			'parent' => 'user-topics',
			'id'     => 'favorites',
			'title'  => __( 'Favorite Topics', 'wporg-forums' ),
			'href'   => bbp_get_favorites_permalink( $user_id ),
		) );

		if ( current_user_can( 'moderate' ) ) {
			$wp_admin_bar->add_group( array(
				'parent' => 'my-account',
				'id'     => 'moderator-views',
				'meta'   => array(
					'class' => 'ab-sub-secondary',
				),
			) );

			$moderator_views = array(
				'all-topics',
				'no-replies',
				'support-forum-no',
				'taggedmodlook',
			);

			foreach ( $moderator_views as $view ) {
				if ( ! bbp_get_view_id( $view ) ) {
					continue;
				}

				$wp_admin_bar->add_menu( array(
					'parent' => 'moderator-views',
					'id'     => $view,
					'title'  => bbp_get_view_title( $view ),
					'href'   => bbp_get_view_url( $view ),
				) );
			}
		}
	}

	/**
	 * Only allow 3 published topics from a user in the first 24 hours.
	 *
	 * If the user has exceeded their limit, move any new topics to moderation queue.
	 *
	 * @param array $topic_data Topic data.
	 * @return array Filtered topic data.
	 */
	public function limit_new_user_topics( $topic_data ) {
		$current_user = wp_get_current_user();

		if ( time() - strtotime( $current_user->user_registered ) >= DAY_IN_SECONDS ) {
			return $topic_data;
		}

		if ( 'publish' === $topic_data['post_status'] && bbp_get_user_topic_count_raw( $current_user->ID ) >= 3 ) {
			$topic_data['post_status'] = 'pending';
		}

		return $topic_data;
	}

	/**
	 * Add query vars for user's "Reviews Written",
	 * "Topics Replied To", and "Reports Submitted" views.
	 *
	 * @param array $query_vars Query vars.
	 * @return array Filtered query vars.
	 */
	public function add_query_vars( $query_vars ) {
		$query_vars[] = 'wporg_single_user_reviews';
		$query_vars[] = 'wporg_single_user_topics_replied_to';
		$query_vars[] = 'wporg_single_user_reported_topics';
		return $query_vars;
	}

	/**
	 * Add rewrite rules for user's "Reviews Written",
	 * "Topics Replied To", and "Reports Submitted" views.
	 */
	public function add_rewrite_rules() {
		$priority   = 'top';

		$user_reviews_rule           = bbp_get_user_slug() . '/([^/]+)/reviews/';
		$user_topics_replied_to_rule = bbp_get_user_slug() . '/([^/]+)/replied-to/';
		$user_reports_submitted      = bbp_get_user_slug() . '/([^/]+)/reports/';

		$feed_id    = 'feed';
		$user_id    = bbp_get_user_rewrite_id();
		$paged_id   = bbp_get_paged_rewrite_id();

		$feed_slug  = 'feed';
		$paged_slug = bbp_get_paged_slug();

		$base_rule  = '?$';
		$feed_rule  = $feed_slug . '/?$';
		$paged_rule = $paged_slug . '/?([0-9]{1,})/?$';

		// Add user's "Reviews Written" page rewrite rules.
		add_rewrite_rule( $user_reviews_rule . $base_rule,  'index.php?' . $user_id . '=$matches[1]&wporg_single_user_reviews=1',                               $priority );
		add_rewrite_rule( $user_reviews_rule . $paged_rule, 'index.php?' . $user_id . '=$matches[1]&wporg_single_user_reviews=1&' . $paged_id . '=$matches[2]', $priority );
		add_rewrite_rule( $user_reviews_rule . $feed_rule,  'index.php?' . $user_id . '=$matches[1]&wporg_single_user_reviews=1&' . $feed_id  . '=$matches[2]', $priority );

		// Add user's "Topics Replied To" page rewrite rules.
		add_rewrite_rule( $user_topics_replied_to_rule . $base_rule,  'index.php?' . $user_id . '=$matches[1]&wporg_single_user_topics_replied_to=1',                               $priority );
		add_rewrite_rule( $user_topics_replied_to_rule . $paged_rule, 'index.php?' . $user_id . '=$matches[1]&wporg_single_user_topics_replied_to=1&' . $paged_id . '=$matches[2]', $priority );
		add_rewrite_rule( $user_topics_replied_to_rule . $feed_rule,  'index.php?' . $user_id . '=$matches[1]&wporg_single_user_topics_replied_to=1&' . $feed_id  . '=$matches[2]', $priority );

		// Add users "Reports Submitted" page rewrite rules.
		add_rewrite_rule( $user_reports_submitted . $base_rule,  'index.php?' . $user_id . '=$matches[1]&wporg_single_user_reported_topics=1',                               $priority );
		add_rewrite_rule( $user_reports_submitted . $paged_rule, 'index.php?' . $user_id . '=$matches[1]&wporg_single_user_reported_topics=1&' . $paged_id . '=$matches[2]', $priority );
		add_rewrite_rule( $user_reports_submitted . $feed_rule,  'index.php?' . $user_id . '=$matches[1]&wporg_single_user_reported_topics=1&' . $feed_id  . '=$matches[2]', $priority );
	}

	/**
	 * Verify that the a new email is valid for use.
	 *
	 * @param string $action The current action.
	 */
	function check_email_safe_for_use( $action = '' ) {
		$user_id    = bbp_get_displayed_user_id();
		$user_email = bbp_get_displayed_user_field( 'user_email', 'raw' );

		if (
			// Only on the front-end user edit form, and make sure the request is valid.
			'bbp-update-user' !== $action ||
			is_admin() ||
			empty( $_POST['email'] ) ||
			! current_user_can( 'edit_user', $user_id ) ||
			! bbp_verify_nonce_request( 'update-user_' . $user_id )
		) {
			return;
		}

		if (
			$user_email !== $_POST['email'] &&
			is_email( $_POST['email'] ) &&
			is_email_address_unsafe( $_POST['email'] )
		) {
			bbp_add_error( 'bbp_user_email_invalid', __( '<strong>Error:</strong> That email address cannot be used.', 'wporg-forums' ), array( 'form-field' => 'email' ) );

			// Override the post variable to ensure that bbPress & core doesn't use it.
			$_POST['email'] = $_REQUEST['email'] = $user_email;
		}
	}

	/**
	 * Set WP_Query::bbp_is_single_user_profile to false on user's "Reviews Written",
	 * "Topics Replied To", and "Reports Submitted" views.
	 *
	 * @param WP_Query $query Current query object.
	 */
	public function parse_user_topics_query( $query ) {
		if (
			get_query_var( 'wporg_single_user_reviews' )
			||
			get_query_var( 'wporg_single_user_topics_replied_to' )
			||
			get_query_var( 'wporg_single_user_reported_topics' )
		) {
			$query->bbp_is_single_user_profile = false;
		}
	}

	/**
	 * Filter the GROUP BY clause on user's "Topics Replied To" page
	 * in order to group replies by topic.
	 *
	 * @param string   $groupby The GROUP BY clause of the query.
	 * @param WP_Query $query   The WP_Query instance.
	 * @return string Filtered GROUP BY clause.
	 */
	public function parse_user_topics_posts_groupby( $groupby, $query ) {
		global $wpdb;

		if ( 'reply' === $query->get( 'post_type' ) && get_query_var( 'wporg_single_user_topics_replied_to' ) ) {
			$groupby = "$wpdb->posts.post_parent";
		}

		return $groupby;
	}

	/**
	 * Set the arguments for user's "Reviews Written" query.
	 *
	 * @param array $args WP_Query arguments.
	 * @return array Filtered query arguments.
	 */
	public function parse_user_topics_query_args( $args ) {
		// Forums at https://wordpress.org/support/.
		if ( defined( 'WPORG_SUPPORT_FORUMS_BLOGID' ) && WPORG_SUPPORT_FORUMS_BLOGID == get_current_blog_id() ) {
			// Set forum ID for topic and review queries.
			if ( get_query_var( 'wporg_single_user_reviews' ) ) {
				$args['post_parent'] = Plugin::REVIEWS_FORUM_ID;
			} elseif ( bbp_is_single_user_topics() ) {
				$args['post_parent__not_in'] = array( Plugin::REVIEWS_FORUM_ID );
			}
		}

		return $args;
	}

	/**
	 * Set the arguments for user's "Reports Submitted" query.
	 *
	 * @param array $args WO_Query arguments.
	 * @return array Filtered query arguments.
	 */
	public function parse_user_replies_query_args( $args ) {
		if ( get_query_var( 'wporg_single_user_reported_topics' ) ) {
			$args['post_type'] = 'reported_topics';
			unset( $args['meta_key'] );
			unset( $args['meta_type'] );
		}

		return $args;
	}

	/**
	 * Set 'base' argument for pagination links on user's "Reviews Written",
	 * "Topics Replied To", and "Reports Submitted" views.
	 *
	 * @param array $args Pagination arguments.
	 * @return array Filtered pagination arguments.
	 */
	public function parse_user_topics_pagination_args( $args ) {
		if ( get_query_var( 'wporg_single_user_reviews' ) ) {
			$args['base']  = bbp_get_user_profile_url( bbp_get_displayed_user_id() ) . 'reviews/';
			$args['base'] .= bbp_get_paged_slug() . '/%#%/';
		}

		if ( get_query_var( 'wporg_single_user_topics_replied_to' ) ) {
			$args['base']  = bbp_get_user_profile_url( bbp_get_displayed_user_id() ) . 'replied-to/';
			$args['base'] .= bbp_get_paged_slug() . '/%#%/';
		}

		if ( get_query_var( 'wporg_single_user_reported_topics' ) ) {
			$args['base']  = bbp_get_user_profile_url( bbp_get_displayed_user_id() ) . 'reports/';
			$args['base'] .= bbp_get_paged_slug() . '/%#%/';
		}

		return $args;
	}

	/**
	 * Set title for user's "Reviews Written", "Topics Replied To", and "Reports Submitted" views.
	 *
	 * @param array $title Title parts.
	 * @return array Filtered title parts.
	 */
	public function parse_user_topics_title_args( $title ) {
		if ( get_query_var( 'wporg_single_user_reviews' ) ) {
			if ( bbp_is_user_home() ) {
				$title['text'] = __( 'Your Reviews Written', 'wporg-forums' );
			} elseif ( bbp_get_user_id() ) {
				$title['text'] = get_userdata( bbp_get_user_id() )->display_name;
				/* translators: user's display name */
				$title['format'] = __( "%s's Reviews Written", 'wporg-forums' );
			}
		}

		if ( get_query_var( 'wporg_single_user_topics_replied_to' ) ) {
			if ( bbp_is_user_home() ) {
				$title['text'] = __( "Topics You've Replied To", 'wporg-forums' );
			} elseif ( bbp_get_user_id() ) {
				$title['text'] = get_userdata( bbp_get_user_id() )->display_name;
				/* translators: user's display name */
				$title['format'] = __( 'Topics %s Has Replied To', 'wporg-forums' );
			}
		}

		if ( get_query_var( 'wporg_single_user_reported_topics' ) ) {
			if ( bbp_is_user_home() ) {
				$title['text'] = __( "Reports You've Submitted", 'wporg-forums' );
			} elseif ( bbp_get_user_id() ) {
				$title['text'] = get_userdata( bbp_get_user_id() )->display_name;
				/* translators: user's display name */
				$title['format'] = __( 'Reports %s Has Submitted', 'wporg-forums' );
			}
		}

		return $title;
	}

	/**
	 * Return the raw database count of topics by a user, excluding reviews.
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param int $user_id User ID to get count for.
	 * @return int Raw DB count of topics.
	 */
	public function get_user_topics_count( $user_id = 0 ) {
		global $wpdb;

		$user_id = bbp_get_user_id( $user_id );
		if ( empty( $user_id ) ) {
			return 0;
		}

		if ( ! class_exists( 'WordPressdotorg\Forums\Plugin' ) ) {
			return 0;
		}

		// Check cache.
		$count = wp_cache_get( $user_id, 'user-topics-count' );
		if ( false === $count ) {
			$count = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*)
					FROM {$wpdb->posts}
					WHERE post_type = 'topic'
						AND post_status IN ( 'publish', 'closed' )
						AND post_parent <> %d
						AND post_author = %d",
				Plugin::REVIEWS_FORUM_ID,
				$user_id
			) );
			wp_cache_set( $user_id, $count, 'user-topics-count' );
		}

		return $count;
	}

	/**
	 * Return the raw database count of reports by a user.
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param int $user_id User ID to get count for.
	 * @return int Raw DB count of reports.
	 */
	public function get_user_report_count( $user_id = 0 ) {
		global $wpdb;

		$user_id = bbp_get_user_id( $user_id );
		if ( empty( $user_id ) ) {
			return 0;
		}

		if ( ! class_exists( 'WordPressdotorg\Forums\Plugin' ) ) {
			return 0;
		}

		// Check cache.
		$count = wp_cache_get( $user_id, 'user-report-count' );
		if ( false === $count ) {
			$count = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*)
					FROM {$wpdb->posts}
					WHERE post_type = 'reported_topics'
						AND post_status IN ( 'publish', 'closed' )
						AND post_author = %d",
				$user_id
			) );
			wp_cache_set( $user_id, $count, 'user-report-count' );
		}

		return $count;
	}

	/**
	 * Return the raw database count of reviews by a user.
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param int $user_id User ID to get count for.
	 * @return int Raw DB count of reviews.
	 */
	public function get_user_reviews_count( $user_id = 0 ) {
		global $wpdb;

		$user_id = bbp_get_user_id( $user_id );
		if ( empty( $user_id ) ) {
			return 0;
		}

		if ( ! class_exists( 'WordPressdotorg\Forums\Plugin' ) ) {
			return 0;
		}

		// Check cache.
		$count = wp_cache_get( $user_id, 'user-reviews-count' );
		if ( false === $count ) {
			$count = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*)
					FROM {$wpdb->posts}
					WHERE post_type = 'topic'
						AND post_status IN ( 'publish', 'closed' )
						AND post_parent = %d
						AND post_author = %d",
				Plugin::REVIEWS_FORUM_ID,
				$user_id
			) );
			wp_cache_set( $user_id, $count, 'user-reviews-count' );
		}

		return $count;
	}

	/**
	 * Clear user's topics and reviews count cache.
	 *
	 * @param int $topic_id Topic ID.
	 */
	public function clear_user_topics_count_cache( $topic_id ) {
		$post = get_post( $topic_id );

		if ( Plugin::REVIEWS_FORUM_ID != $post->post_parent ) {
			wp_cache_delete( $post->post_author, 'user-topics-count' );
		} else {
			wp_cache_delete( $post->post_author, 'user-reviews-count' );
		}
	}

	/**
	 * Allow bulk unsubscribe from all topics.
	 */
	public function bulk_topic_unsubscribe() {
		$user_id = bbp_get_displayed_user_id();
		if ( ! bbp_is_user_home() && ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}
		if ( ! bbp_get_user_topic_subscriptions() ) {
			return;
		}

		echo '<form method="post" style="margin-top: -2em;margin-bottom: 1em;">';
		wp_nonce_field( 'bulk_unsubscribe_' . $user_id );
		echo '<input type="submit" name="bulk-topic-unsub" class="button" value="' . esc_attr__( 'Unsubscribe from all topics', 'wporg-forums' ) . '">';
		echo '</form>';
	}

	/**
	 * Bulk topic unsubscription handler for `bulk_topic_unsubscribe()`.
	 */
	public function bulk_topic_unsubscribe_process() {
		$user_id = bbp_get_displayed_user_id();
		if (
			isset( $_POST['bulk-topic-unsub'], $_POST['_wpnonce'] ) &&
			( bbp_is_user_home() || current_user_can( 'edit_user', $user_id ) ) &&
			wp_verify_nonce( $_POST['_wpnonce'], 'bulk_unsubscribe_' . $user_id )
		) {
			bbp_remove_user_from_all_objects( $user_id, '_bbp_subscription' );
		}
	}

}
