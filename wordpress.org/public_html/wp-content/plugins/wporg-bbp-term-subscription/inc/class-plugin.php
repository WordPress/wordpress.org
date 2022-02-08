<?php

namespace WordPressdotorg\Forums\Term_Subscription;

class Plugin {

	/**
	 * @todo AJAXify subscription action.
	 * @todo Add unsubscribe link to outgoing emails.
	 */

	private $subscribers = array();

	public $taxonomy  = false;
	public $labels    = array();
	public $directory = false;

	const META_KEY = '_bbp_term_subscription';

	public function __construct( $args = array() ) {
		$r = wp_parse_args( $args, array(
			'taxonomy'  => 'topic-tag',
			'directory' => false,
			'labels'    => array(
				'subscribed_header'      => __( 'Subscribed Topic Tags', 'wporg-forums' ),
				'subscribed_user_notice' => __( 'You are not currently subscribed to any topic tags.', 'wporg-forums' ),
				'subscribed_anon_notice' => __( 'This user is not currently subscribed to any topic tags.', 'wporg-forums' ),
				'receipt'                => __( 'You are receiving this email because you are subscribed to a topic tag.', 'wporg-forums'),
			),
		) );

		$this->taxonomy  = $r['taxonomy'];
		$this->labels    = $r['labels'];
		$this->directory = $r['directory'];

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

		// Replace the title of subscription emails with the term-specific prefix.
		// This applies to all notification emails sent related to the topic, not just the term-specific emails.
		add_filter( 'bbp_forum_subscription_mail_title', array( $this, 'replace_forum_subscription_mail_title' ), 10, 2 );
		add_filter( 'bbp_subscription_mail_title',       array( $this, 'replace_topic_subscription_mail_title' ), 10, 3 );

		add_action( 'bbp_template_after_user_subscriptions', array( $this, 'user_subscriptions' ) );

	}

	/**
	 * Handle a user subscribing to and unsubscribing from a term that is
	 * in the taxonomy of this instance.
	 *
	 * @param string $action The requested action to compare this function to
	 */
	public function term_subscribe_handler( $action = '' ) {
		// Bail if the actions aren't meant for this function.
		if ( ! in_array( $action, self::get_valid_actions() ) ) {
			return;
		}

		if ( ! $this->taxonomy ) {
			return;
		}

		// Taxonomy mismatch; a different instance should handle this.
		if ( ! isset( $_GET['taxonomy'] ) || $this->taxonomy != $_GET['taxonomy'] ) {
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
		if ( ! isset( $_GET['term_id'] ) || empty( $_GET['term_id'] ) ) {
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

		// Redirect
		if ( bbp_is_subscriptions() ) {
			$redirect = bbp_get_subscriptions_permalink( $user_id );
		} else {
			$redirect = get_term_link( $term_id );
		}

		// Success!
		if ( true === $success ) {
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
	public function notify_term_subscribers_of_new_topic( $topic_id, $forum_id, $anonymous_data = false, $topic_author = 0 ) {
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

		// Remove the author from being notified of their own topic.
		$this->subscribers = array_diff( $this->subscribers, array( bbp_get_topic_author_id( $topic_id ) ) );

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
		remove_filter( 'bbp_forum_subscription_user_ids',     array( $this, 'add_term_subscribers_to_forum' ) );
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

Log in and visit the topic to reply to the topic or unsubscribe from these emails. Note that replying to this email has no effect.', 'wporg-forums' ),
			$topic_author_name,
			$topic_content,
			$topic_url,
			// String may not have placeholders, ie. in the case of tags.
			sprintf( $this->labels['receipt'], $this->directory ? $this->directory->title() : '' )
		);

		return $message;
	}

	/**
	 * Replace the forum subscription subject/title with term-specific messaging.
	 *
	 * @param string $title The current title
	 * @param int $topic_id The topic id
	 */
	public function replace_forum_subscription_mail_title( $title, $topic_id ) {
		$terms = get_the_terms( $topic_id, $this->taxonomy );
		if ( ! $terms ) {
			return $title;
		}

		if ( $this->directory && $this->directory->title() ) {
			// [Plugin Name] This is my thread title
			$title = sprintf(
				'[%s] %s',
				$this->directory->title(),
				strip_tags( bbp_get_topic_title( $topic_id ) )
			);
		}

		return $title;
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

		// Remove the author from being notified of their own reply.
		$this->subscribers = array_diff( $this->subscribers, array( bbp_get_reply_author_id( $reply_id ) ) );

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
		remove_filter( 'bbp_subscription_mail_message',   array( $this, 'replace_topic_subscription_mail_message' ) );
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

Log in and visit the topic to reply to the topic or unsubscribe from these emails. Note that replying to this email has no effect.', 'wporg-forums' ),
			$reply_author_name,
			$reply_content,
			$reply_url,
			// String may not have placeholders, ie. in the case of tags.
			sprintf( $this->labels['receipt'], $this->directory ? $this->directory->title() : '' )
		);

		return $message;
	}

	/**
	 * Replace the topic subscription subject/title with term-specific messaging.
	 *
	 * @param string $title    The current title
	 * @param int    $reply_id The reply id
	 * @param int    $topic_id The topic id
	 */
	public function replace_topic_subscription_mail_title( $title, $reply_id, $topic_id ) {
		$terms = get_the_terms( $topic_id, $this->taxonomy );
		if ( ! $terms ) {
			return $title;
		}

		if ( $this->directory && $this->directory->title() ) {
			// [Plugin Name] This is my thread title
			$title = sprintf(
				'[%s] %s',
				$this->directory->title(),
				strip_tags( bbp_get_topic_title( $topic_id ) )
			);
		}

		return $title;
	}

	/**
	 * Add a term subscription block to the user's profile.
	 */
	public function user_subscriptions() {
		$user_id = bbp_get_user_id();
		if ( empty( $user_id ) ) {
			return;
		}

		// Don't display the subscriptions unless it's the current user, or they can edit that user.
		if ( ! bbp_is_user_home() && ! current_user_can( 'edit_user', bbp_get_displayed_user_id() ) ) {
			return;
		}

		$terms = self::get_user_taxonomy_subscriptions( $user_id, $this->taxonomy );
		?>

		<div class="bbp-user-subscriptions">
			<h2 class="entry-title"><?php echo esc_html( $this->labels['subscribed_header'] ); ?></h2>
			<div class="bbp-user-section">
			<?php
			if ( $terms ) {
				echo '<p id="bbp-term-' . esc_attr( $this->taxonomy ) . '">' . "\n";
				foreach ( $terms as $term ) {
					echo '<a href="' . esc_url( get_term_link( $term->term_id ) ) . '">' . esc_html( $term->slug ) . '</a>';
					if ( get_current_user_id() == $user_id ) {
						$url = self::get_subscription_url( $user_id, $term->term_id, $this->taxonomy );
						echo ' (<a href="' . esc_url( self::get_subscription_url( $user_id, $term->term_id, $this->taxonomy ) ) . '">' . esc_html( 'Unsubscribe', 'wporg-forums' ) . '</a>)';
					}
					echo "</br>\n";
				}
				echo "</p>\n";
			} else {
				if ( bbp_get_user_id() == get_current_user_id() ) {
					echo '<p>' . esc_html( $this->labels['subscribed_user_notice'] ) . '</p>';
				} else {
					echo '<p>' . esc_html( $this->labels['subscribed_anon_notice'] ) . '</p>';
				}
			}
			?>
			</div>
		</div>

		<?php
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
	 * Create the url for subscribing to/unsubscribing from a given term.
	 *
	 * @param int $user_id The user id
	 * @param int $term_id The term id
	 * @param int $taxonomy The taxonomy
	 * @return string
	 */
	public static function get_subscription_url( $user_id = 0, $term_id, $taxonomy ) {
		if ( empty( $user_id ) ) {
			$user_id = bbp_get_user_id();
		}

		if ( empty( $user_id ) ) {
			return false;
		}

		$term = get_term( $term_id, $taxonomy );
		if ( ! $term ) {
			return false;
		}

		if ( self::is_user_subscribed_to_term( $user_id, $term_id ) ) {
			$query_args = array( 'action' => 'wporg_bbp_unsubscribe_term', 'term_id' => $term_id, 'taxonomy' => $taxonomy );
		} else {
			$query_args = array( 'action' => 'wporg_bbp_subscribe_term', 'term_id' => $term_id, 'taxonomy' => $taxonomy );
		}

		if ( bbp_is_subscriptions() ) {
			$permalink = bbp_get_subscriptions_permalink( $user_id );
		} else {
			$permalink = get_term_link( $term_id );
		}

		$url = esc_url( wp_nonce_url( add_query_arg( $query_args, $permalink ), 'toggle-term-subscription_' . $user_id . '_' . $term_id . '_' . $taxonomy ) );
		return $url;
	}

	public static function get_subscription_link( $args ) {
		if ( ! current_user_can( 'spectate' ) ) {
			return false;
		}

		$r = bbp_parse_args( $args, array(
			'user_id'     => get_current_user_id(),
			'term_id'     => 0,
			'taxonomy'    => 'topic-tag',
			'class'       => 'button',
			'subscribe'   => esc_html__( 'Subscribe to this topic tag', 'wporg-forums' ),
			'unsubscribe' => esc_html__( 'Unsubscribe from this topic tag', 'wporg-forums' ),
			'js_confirm'  => esc_html__( 'Are you sure you wish to subscribe by email to all future topics created in this tag?', 'wporg-forums' ),
		), 'get_term_subscription_link' );
		if ( empty( $r['user_id'] ) || empty( $r['term_id'] ) || empty( $r['taxonomy'] ) ) {
			return false;
		}

		$user_id  = $r['user_id'];
		$term_id  = $r['term_id'];
		$taxonomy = $r['taxonomy'];

		$url = self::get_subscription_url( $r['user_id'], $r['term_id'], $r['taxonomy'] );
		if ( self::is_user_subscribed_to_term( $r['user_id'], $r['term_id'] ) ) {
			$text       = $r['unsubscribe'];
			$js_confirm = '';
		} else {
			$text       = $r['subscribe'];
			$js_confirm = 'javascript:return confirm(' . json_encode( $r['js_confirm'] ) . ');';
		}

		return sprintf(
			"<div class='wporg-bbp-term-subscription'><a href='%s' class='%s' onclick='%s'>%s</a></div>",
			$url,
			esc_attr( $r['class'] ),
			esc_attr( $js_confirm ),
			esc_html( $text )
		);
	}

	public static function get_valid_actions() {
		return array(
			'wporg_bbp_subscribe_term',
			'wporg_bbp_unsubscribe_term',
		);
	}
}
