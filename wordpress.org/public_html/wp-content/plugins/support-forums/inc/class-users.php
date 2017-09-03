<?php

namespace WordPressdotorg\Forums;

class Users {

	public function __construct() {
		// Add custom fields to user's profile.
		add_action( 'bbp_user_edit_after_name',        array( $this, 'add_custom_title_input' ) );
		add_action( 'bbp_user_edit_after',             array( $this, 'add_auto_topic_subscription_checkbox' ) );

		// Save custom field values.
		add_action( 'personal_options_update',         array( $this, 'save_custom_fields' ), 10, 2 );
		add_action( 'edit_user_profile_update',        array( $this, 'save_custom_fields' ), 10, 2 );

		// Custom user contact methods.
		add_filter( 'user_contactmethods',             array( $this, 'custom_contact_methods' ) );

		// Only allow 3 published topics from a user in the first 24 hours.
		add_action( 'bbp_new_topic_pre_insert',        array( $this, 'limit_new_user_topics' ) );

		// Add query vars and rewrite rules for user's topic and review queries.
		add_filter( 'query_vars',                      array( $this, 'add_query_vars' ) );
		add_action( 'bbp_add_rewrite_rules',           array( $this, 'add_rewrite_rules' ) );

		// Parse user's topic and review queries.
		add_action( 'parse_query',                     array( $this, 'parse_user_topics_query' ) );
		add_filter( 'posts_groupby',                   array( $this, 'parse_user_topics_posts_groupby' ), 10, 2 );
		add_filter( 'bbp_after_has_topics_parse_args', array( $this, 'parse_user_topics_query_args' ) );
		add_filter( 'bbp_topic_pagination',            array( $this, 'parse_user_topics_pagination_args' ) );
		add_filter( 'bbp_replies_pagination',          array( $this, 'parse_user_topics_pagination_args' ) );
		add_filter( 'bbp_before_title_parse_args',     array( $this, 'parse_user_topics_title_args' ) );
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
	 * Add query vars for user's "Reviews Written" and "Topics Replied To" views.
	 *
	 * @param array $query_vars Query vars.
	 * @return array Filtered query vars.
	 */
	public function add_query_vars( $query_vars ) {
		$query_vars[] = 'wporg_single_user_reviews';
		$query_vars[] = 'wporg_single_user_topics_replied_to';
		return $query_vars;
	}

	/**
	 * Add rewrite rules for user's "Reviews Written" and "Topics Replied To" views.
	 */
	public function add_rewrite_rules() {
		$priority   = 'top';

		$user_reviews_rule           = bbp_get_user_slug() . '/([^/]+)/reviews/';
		$user_topics_replied_to_rule = bbp_get_user_slug() . '/([^/]+)/replied-to/';

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
	}

	/**
	 * Set WP_Query::bbp_is_single_user_profile to false on user's "Reviews Written"
	 * and "Topics Replied To" views.
	 *
	 * @param WP_Query $query Current query object.
	 */
	public function parse_user_topics_query( $query ) {
		if (
			get_query_var( 'wporg_single_user_reviews' )
		||
			get_query_var( 'wporg_single_user_topics_replied_to' )
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
	 * Set 'base' argument for pagination links on user's "Reviews Written"
	 * and "Topics Replied To" views.
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

		return $args;
	}

	/**
	 * Set title for user's "Reviews Written" and "Topics Replied To" views.
	 *
	 * @param array $title Title parts.
	 * @return array Filtered title parts.
	 */
	public function parse_user_topics_title_args( $title ) {
		if ( get_query_var( 'wporg_single_user_reviews' ) ) {
			if ( bbp_is_user_home() ) {
				$title['text'] = __( 'Your Reviews Written', 'wporg-forums' );
			} else {
				$title['text'] = get_userdata( bbp_get_user_id() )->display_name;
				/* translators: user's display name */
				$title['format'] = __( "%s's Reviews Written", 'wporg-forums' );
			}
		}

		if ( get_query_var( 'wporg_single_user_topics_replied_to' ) ) {
			if ( bbp_is_user_home() ) {
				$title['text'] = __( "Topics You've Replied To", 'wporg-forums' );
			} else {
				$title['text'] = get_userdata( bbp_get_user_id() )->display_name;
				/* translators: user's display name */
				$title['format'] = __( 'Topics %s Has Replied To', 'wporg-forums' );
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

		return $count;
	}

	/**
	 * Cle the raw database count of reviews by a user.
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

		return $count;
	}

}
