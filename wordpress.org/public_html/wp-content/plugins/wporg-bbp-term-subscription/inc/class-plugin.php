<?php

namespace WordPressdotorg\Forums\Term_Subscription;

class Plugin {

	/**
	 * @todo AJAXify subscription action.
	 * @todo Add unsubscribe link to outgoing emails.
	 */

	private $subscribers = array();

	public $taxonomy = false;
	public $labels   = array();

	const META_KEY = '_bbp_term_subscription';

	public function __construct( $args = array() ) {
		$r = wp_parse_args( $args, array(
			'taxonomy' => 'topic-tag',
			'labels'   => array(
				'receipt' => __( 'You are receiving this email because you are subscribed to a topic tag.', 'wporg-forums'),
			),
		) );

		$this->taxonomy = $r['taxonomy'];
		$this->labels   = $r['labels'];

		add_action( 'bbp_init', array( $this, 'bbp_init' ) );
	}

	/**
	 * Initialize the plugin.
	 */
	public function bbp_init() {
		// If the user isn't logged in, there will be no topics or replies added.
		if ( ! is_user_logged_in() ) {
			return;
		}

		if ( ! $this->taxonomy ) {
			return;
		}

		// Add views and actions for users.
		add_action( 'bbp_get_request', array( $this, 'term_subscribe_handler' ) );

		// Notify subscribers when a topic or reply with a given term is added.
		add_action( 'bbp_new_topic', array( $this, 'notify_term_subscribers_of_new_topic' ), 10, 4 );
		add_action( 'bbp_new_reply', array( $this, 'notify_term_subscribers_of_new_reply' ), 10, 5 );
	}

	/**
	 * Handle a user subscribing to and unsubscribing from a term that is
	 * in the taxonomy of this instance.
	 *
	 * @param string $action The requested action to compare this function to
	 */
	public function term_subscribe_handler( $action = '' ) {
		if ( ! $this->taxonomy ) {
			return;
		}

		// Taxonomy mismatch; a different instance should handle this.
		if ( $this->taxonomy != $_GET['taxonomy'] ) {
			return;
		}

		if ( ! bbp_is_subscriptions_active() ) {
			return false;
		}

		// Bail if the actions aren't meant for this function.
		if ( ! in_array( $action, self::get_valid_actions() ) ) {
			return;
		}

		// Bail if no term id is passed.
		if ( empty( $_GET['term_id'] ) || empty( $_GET['taxonomy'] ) ) {
			return;
		}

		// Get required data.
		$user_id = get_current_user_id();
		$term_id = intval( $_GET['term_id'] );
		$term = get_term( $term_id, $this->taxonomy );

		// Check for empty term id.
		if ( ! $term ) {
			/* translators: Term: topic tag */
			bbp_add_error( 'wporg_bbp_subscribe_term_id', __( '<strong>ERROR</strong>: No term was found! Which term are you subscribing/unsubscribing to?', 'wporg-forums' ) );

		// Check for current user.
		} elseif ( empty( $user_id ) ) {
			bbp_add_error( 'wporg_bbp_subscribe_logged_id', __( '<strong>ERROR</strong>: You must be logged in to do this!', 'wporg-forums' ) );

		// Check nonce.
		} elseif ( ! bbp_verify_nonce_request( 'toggle-term-subscription_' . $user_id . '_' . $term_id . '_' . $this->taxonomy ) ) {
			bbp_add_error( 'wporg_bbp_subscribe_nonce', __( '<strong>ERROR</strong>: Are you sure you wanted to do that?', 'wporg-forums' ) );

		// Check current user's ability to spectate.
		} elseif ( ! current_user_can( 'spectate' ) ) {
			bbp_add_error( 'wporg_bbp_subscribe_permissions', __( '<strong>ERROR</strong>: You don\'t have permission to do this!', 'wporg-forums' ) );
		}

		if ( bbp_has_errors() ) {
			return;
		}

		$is_subscribed = self::is_user_subscribed_to_term( $user_id, $term_id );
		$success = false;

		if ( true === $is_subscribed && 'wporg_bbp_unsubscribe_term' === $action ) {
			$success = self::remove_user_subscription( $user_id, $term_id );
		} elseif ( false === $is_subscribed && 'wporg_bbp_subscribe_term' === $action ) {
			$success = self::add_user_subscription( $user_id, $term_id );
		}

		// Success!
		if ( true === $success ) {
			$redirect = get_term_link( $term_id );
			bbp_redirect( $redirect );
		} elseif ( true === $is_subscribed && 'wporg_bbp_subscribe_term' === $action ) {
			/* translators: Term: topic tag */
			bbp_add_error( 'wporg_bbp_subscribe_user', __( '<strong>ERROR</strong>: There was a problem subscribing to that term!', 'wporg-forums' ) );
		} elseif ( false === $is_subscribed && 'wporg_bbp_unsubscribe_term' === $action ) {
			/* translators: Term: topic tag */
			bbp_add_error( 'wporg_bbp_unsubscribe_user', __( '<strong>ERROR</strong>: There was a problem unsubscribing from that term!', 'wporg-forums' ) );
		}
	}

	/**
	 * Use the existing bbp_notify_forum_subscribers() to send out term subscriptions for new topics.
	 * Avoid duplicate notifications for forum subscribers through the judicious use of filters within
	 * the function.
	 *
	 * @param int $topic_id The topic id
	 * @param int $forum_id The forum id
	 * @param mixed $anonymous_data Array of anonymous user data
	 * @param int $topic_author The topic author id
	 */
	public function notify_term_subscribers_of_new_topic( $topic_id, $forum_id,  $anonymous_data = false, $topic_author = 0 ) {
		$terms = get_the_terms( $topic_id, $this->taxonomy );
		if ( ! $terms ) {
			return;
		}

		foreach ( $terms as $term ) {
			$subscribers = $this->get_term_subscribers( $term->term_id );
			if ( $subscribers ) {
				$this->subscribers = array_unique( array_merge( $subscribers, $this->subscribers ) );
			}
		}

		// Get users who were already notified and exclude them.
		$forum_subscribers = bbp_get_forum_subscribers( $forum_id, true );
		if ( ! empty( $forum_subscribers ) ) {
			$this->subscribers = array_diff( $this->subscribers, $forum_subscribers );
		}

		if ( empty( $this->subscribers ) ) {
			return;
		}

		// Replace forum-specific messaging with term subscription messaging.
		add_filter( 'bbp_forum_subscription_mail_message', array( $this, 'replace_forum_subscription_mail_message' ), 10, 4 );

		// Replace forum subscriber list with term subscribers, avoiding duplicates.
		add_filter( 'bbp_forum_subscription_user_ids', array( $this, 'add_term_subscribers_to_forum' ) );

		// Actually notify our term subscribers.
		bbp_notify_forum_subscribers( $topic_id, $forum_id );

		// Remove filters.
		remove_filter( 'bbp_forum_subscription_user_ids', array( $this, 'add_term_subscribers_to_forum' ) );
		remove_filter( 'bbp_forum_subscription_mail_message', array( $this, 'replace_forum_subscription_mail_message' ), 10 );
	}

	/**
	 * Temporarily replace the forum subscriber list with any unincluded term subscribers.
	 */
	public function add_term_subscribers_to_forum( $users ) {
		return array_diff( $this->subscribers, $users );
	}

	/**
	 * Replace the forum subscription message with term-specific messaging.
	 *
	 * @param string $message The message
	 * @param int $topic_id The topic id
	 * @param int $forum_id The forum id
	 * @param int $user_id 0
	 */
	public function replace_forum_subscription_mail_message( $message, $topic_id, $forum_id, $user_id ) {
		// Poster name.
		$topic_author_name = bbp_get_topic_author_display_name( $topic_id );

		remove_all_filters( 'bbp_get_topic_content' );

		// Strip tags from text and set up message body.
		$topic_content = strip_tags( bbp_get_topic_content( $topic_id ) );
		$topic_url     = get_permalink( $topic_id );

		$message = sprintf( __( '%1$s wrote:

%2$s

Topic Link: %3$s

-----------

%4$s

Login and visit the topic to unsubscribe from these emails.', 'wporg-forums' ),
			$topic_author_name,
			$topic_content,
			$topic_url,
			$this->labels['receipt']
		);

		return $message;
	}

	/**
	 * Use the existing bbp_notify_topic_subscribers() to send out term subscriptions for replies.
	 * Avoid duplicate notifications for topic subscribers through the judicious use of filters within
	 * the function.
	 *
	 * @param int $reply_id The reply id
	 * @param int $topic_id The topic id
	 * @param int $forum_id The forum id
	 * @param mixed $anonymous_data
	 * @param int $reply_author
	 */
	public function notify_term_subscribers_of_new_reply( $reply_id, $topic_id, $forum_id, $anonymous_data, $reply_author ) {
		$terms = get_the_terms( $topic_id, $this->taxonomy );
		if ( ! $terms ) {
			return;
		}

		foreach ( $terms as $term ) {
			$subscribers = $this->get_term_subscribers( $term->term_id );
			if ( $subscribers ) {
				$this->subscribers = array_unique( array_merge( $subscribers, $this->subscribers ) );
			}
		}

		// Get users who were already notified and exclude them.
		$topic_subscribers = bbp_get_topic_subscribers( $topic_id, true );
		if ( ! empty( $topic_subscribers ) ) {
			$this->subscribers = array_diff( $this->subscribers, $topic_subscribers );
		}

		if ( empty( $this->subscribers ) ) {
			return;
		}

		// Replace topic-specific messaging with term subscription messaging.
		add_filter( 'bbp_subscription_mail_message', array( $this, 'replace_topic_subscription_mail_message' ), 10, 3 );

		// Replace forum subscriber list with term subscribers, avoiding duplicates.
		add_filter( 'bbp_topic_subscription_user_ids', array( $this, 'add_term_subscribers_to_topic' ) );

		// Actually notify our term subscribers.
		bbp_notify_topic_subscribers( $reply_id, $topic_id, $forum_id );

		// Remove filters.
		remove_filter( 'bbp_topic_subscription_user_ids', array( $this, 'add_term_subscribers_to_topic' ) );
		remove_filter( 'bbp_subscription_mail_message', array( $this, 'replace_topic_subscription_mail_message' ), 10 );
	}

	/**
	 * Temporarily replace the forum subscriber list with any unincluded term subscribers.
	 */
	public function add_term_subscribers_to_topic( $users ) {
		return array_diff( $this->subscribers, $users );
	}

	/**
	 * Replace the topic subscription message with term-specific messaging.
	 *
	 * @param string $message The message
	 * @param int $reply_id The reply id
	 * @param int $topic_id The topic id
	 */
	public function replace_topic_subscription_mail_message( $message, $reply_id, $topic_id ) {
		// Poster name.
		$reply_author_name = bbp_get_reply_author_display_name( $reply_id );

		remove_all_filters( 'bbp_get_reply_content' );

		// Strip tags from text and set up message body.
		$reply_content = strip_tags( bbp_get_reply_content( $reply_id ) );
		$reply_url = bbp_get_reply_url( $reply_id );

		$message = sprintf( __( '%1$s wrote:

%2$s

Reply Link: %3$s

-----------

%4$s

Login and visit the topic to unsubscribe from these emails.', 'wporg-forums' ),
			$reply_author_name,
			$reply_content,
			$reply_url,
			$this->labels['receipt']
		);

		return $message;
	}

	/**
	 * Get the user's subscriptions for a given taxonomy; defaults to 'topic-tag'.
	 *
	 * @param $user_id int The user id
	 * @param $taxonomy string Optional. The taxonomy
	 * @return array|bool Results if user has subscriptions, otherwise false
	 */
	public static function get_user_taxonomy_subscriptions( $user_id = 0, $taxonomy = 'topic-tag' ) {
		$retval = false;

		if ( empty( $user_id ) || empty( $taxonomy ) ) {
			return false;
		}

		$terms = get_terms( array(
			'taxonomy'   => $taxonomy,
			'meta_key'   => self::META_KEY,
			'meta_value' => $user_id,
		) );

		if ( ! empty( $terms ) ) {
			$retval = $terms;
		}
		return apply_filters( 'wporg_bbp_get_user_taxonomy_subscriptions', $retval, $user_id, $taxonomy );
	}

	/**
	 * Get the user IDs of users subscribed to a given term.
	 *
	 * @param $term_id int The term id
	 * @return array|bool Results if the term is valid, otherwise false
	 */
	public static function get_term_subscribers( $term_id = 0 ) {
		if ( empty( $term_id ) ) {
			return false;
		}

		$subscribers = wp_cache_get( 'wporg_bbp_get_term_subscribers_' . $term_id, 'bbpress_users' );
		if ( false === $subscribers ) {
			$subscribers = get_term_meta( $term_id, self::META_KEY );
			wp_cache_set( 'wporg_bbp_get_term_subscribers_' . $term_id, $subscribers, 'bbpress_users' );
		}
		return apply_filters( 'wporg_bbp_get_term_subscribers', $subscribers, $term_id );
	}

	/**
	 * Is the user subscribed to a term?
	 *
	 * @param $user_id int The user id
	 * @param $term_id int The term id
	 * @return bool True if the user is subscribed, otherwise false
	 */
	public static function is_user_subscribed_to_term( $user_id = 0, $term_id = 0 ) {
		if ( empty( $user_id ) || empty( $term_id ) ) {
			return false;
		}

		$subscriptions = self::get_term_subscribers( $term_id );
		if ( in_array( $user_id, $subscriptions ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Add a term to a user's subscriptions.
	 *
	 * @param $user_id int The user id
	 * @param $term_id int The term id
	 * @return bool False if invalid, otherwise true
	 */
	public static function add_user_subscription( $user_id = 0, $term_id = 0 ) {
		if ( empty( $user_id ) || empty( $term_id ) ) {
			return false;
		}

		if ( ! self::is_user_subscribed_to_term( $user_id, $term_id ) ) {
			add_term_meta( $term_id, self::META_KEY, $user_id );
			wp_cache_delete( 'wporg_bbp_get_term_subscribers_' . $term_id, 'bbpress_users' );
		}
		do_action( 'wporg_bbp_add_user_term_subscription', $user_id, $term_id );

		return true;
	}

	/**
	 * Remove a term from a user's subscriptions.
	 *
	 * @param $user_id int The user id
	 * @param $term_id int The term id
	 * @return bool False if invalid, otherwise true
	 */
	public static function remove_user_subscription( $user_id = 0, $term_id = 0 ) {
		if ( empty( $user_id ) || empty( $term_id ) ) {
			return false;
		}

		if ( self::is_user_subscribed_to_term( $user_id, $term_id ) ) {
			delete_term_meta( $term_id, self::META_KEY, $user_id );
			wp_cache_delete( 'wporg_bbp_get_term_subscribers_' . $term_id, 'bbpress_users' );
		}
		do_action( 'wporg_bbp_remove_user_term_subscription', $user_id, $term_id );

		return true;
	}

	/**
	 * Create the link for subscribing to/unsubscribing from a given term.
	 *
	 * @param string $action The action
	 * @param int $user_id The user id
	 * @param int $term_id The term id
	 * @return string
	 */
	public static function get_subscription_link( $args = array() ) {
		if ( ! current_user_can( 'spectate' ) ) {
			return false;
		}

		$r = bbp_parse_args( $args, array(
			'user_id'     => get_current_user_id(),
			'term_id'     => 0,
			'taxonomy'    => 'topic-tag',
			'subscribe'   => esc_html__( 'Subscribe to this topic tag', 'wporg-forums' ),
			'unsubscribe' => esc_html__( 'Unsubscribe from this topic tag', 'wporg-forums' ),
		), 'get_term_subscription_link' );
		if ( empty( $r['user_id'] ) || empty( $r['term_id'] ) || empty( $r['taxonomy'] ) ) {
			return false;
		}
		$user_id  = $r['user_id'];
		$term_id  = $r['term_id'];
		$taxonomy = $r['taxonomy'];

		$term = get_term( $term_id, $taxonomy );
		if ( ! $term ) {
			return false;
		}

		if ( self::is_user_subscribed_to_term( $user_id, $term_id ) ) {
			$text = $r['unsubscribe'];
			$query_args = array( 'action' => 'wporg_bbp_unsubscribe_term', 'term_id' => $term_id, 'taxonomy' => $taxonomy );
		} else {
			$text = $r['subscribe'];
			$query_args = array( 'action' => 'wporg_bbp_subscribe_term', 'term_id' => $term_id, 'taxonomy' => $taxonomy );
		}

		$permalink = get_term_link( $term_id );

		$url = esc_url( wp_nonce_url( add_query_arg( $query_args, $permalink ), 'toggle-term-subscription_' . $user_id . '_' . $term_id . '_' . $taxonomy ) );
		return sprintf( "<div class='wporg-bbp-term-subscription'><a href='%s'>%s</a></div>",
			$url,
			esc_html( $text ) );
	}

	public static function get_valid_actions() {
		return array(
			'wporg_bbp_subscribe_term',
			'wporg_bbp_unsubscribe_term',
		);
	}
}
