<?php

namespace WordPressdotorg\Forums\Term_Subscription;

class Plugin {

	/**
	 * @todo AJAXify subscription action.
	 */

	public $taxonomy  = false;
	public $labels    = array();
	public $directory = false;

	protected $term        = false;
	protected $subscribers = array();

	const META_KEY = '_bbp_term_subscription';

	/**
	 * Valid actions for this plugin.
	 */
	const VALID_ACTIONS = array(
		'wporg_bbp_subscribe_term',
		'wporg_bbp_unsubscribe_term',
	);

	/**
	 * Length of time the unsubscription links are valid.
	 * 
	 * @var int
	 */
	const UNSUBSCRIBE_LIFETIME = 604800; // WEEK_IN_SECONDS

	public function __construct( $args = array() ) {
		$r = wp_parse_args( $args, array(
			'taxonomy'  => 'topic-tag',
			'directory' => false,
			'labels'    => array(
				'subscribed_header'      => __( 'Subscribed Topic Tags', 'wporg-forums' ),
				'subscribed_user_notice' => __( 'You are not currently subscribed to any topic tags.', 'wporg-forums' ),
				'subscribed_anon_notice' => __( 'This user is not currently subscribed to any topic tags.', 'wporg-forums' ),
				'receipt'                => __( "You are receiving this email because you are subscribed to the %s tag.", 'wporg-forums'),
			),
		) );

		$this->taxonomy  = $r['taxonomy'];
		$this->labels    = $r['labels'];
		$this->directory = $r['directory'];

		// If no taxonomy was provided, there's nothing we can do.
		if ( ! $this->taxonomy ) {
			return;
		}

		add_action( 'bbp_init', array( $this, 'bbp_init' ) );
	}

	/**
	 * Initialize the plugin.
	 */
	public function bbp_init() {
		// Add views and actions for users.
		add_action( 'bbp_get_request', array( $this, 'term_subscribe_handler' ) );
		add_action( 'bbp_post_request', array( $this, 'term_subscribe_handler' ) );
		add_action( 'bbp_template_redirect', array( $this, 'fix_bbpress_post_actions' ), 9 ); // before bbp_get_request/bbp_post_request

		// Notify subscribers when a topic or reply with a given term is added.
		add_action( 'bbp_new_topic', array( $this, 'notify_term_subscribers_of_new_topic' ), 10, 2 );
		add_action( 'bbp_new_reply', array( $this, 'notify_term_subscribers_of_new_reply' ), 10, 3 );

		// Replace the title of subscription emails with the term-specific prefix.
		// This applies to all notification emails sent related to the topic, not just the term-specific emails.
		add_filter( 'bbp_forum_subscription_mail_title', array( $this, 'replace_forum_subscription_mail_title' ), 10, 2 );
		add_filter( 'bbp_subscription_mail_title',       array( $this, 'replace_topic_subscription_mail_title' ), 10, 3 );

		// Add a section to the user subscriptions list to allow management of subscriptions.
		add_action( 'bbp_template_after_user_subscriptions', array( $this, 'user_subscriptions' ) );

		// Add a banner above the 'forum' about being subscribed to it.
		add_action( 'bbp_template_before_topics_index', array( $this, 'before_view' ) );
		add_action( 'wporg_compat_before_single_view',  array( $this, 'before_view' ) );

		// Use a custom salt
		add_filter( 'salt', array( $this, 'forum_subscriptions_salt' ), 10, 2 );
	}

	/**
	 * bbPress has two action handlers, GET and POST, a POST action cannot work with ?action= from the URL.
	 * 
	 * This is used by the email-unsubscription handler.
	 */
	public function fix_bbpress_post_actions() {
		if (
			bbp_is_post_request() &&
			empty( $_POST['action'] ) &&
			! empty( $_GET['action'] ) &&
			in_array( $_GET['action'], self::VALID_ACTIONS, true )
		) {
			$_POST['action'] = $_GET['action'];
		}
	}

	/**
	 * Add a notice that you're subscribed to the tag/plugin/theme, or have just unsubscribed.
	 */
	public function before_view() {
		$term = $this->get_current_term();

		if ( ! $term || $term->taxonomy !== $this->taxonomy ) {
			return;
		}

		do_action( 'bbp_template_notices' );

		$is_subscribed = self::is_user_subscribed_to_term( get_current_user_id(), $term->term_id );

		if ( $is_subscribed ) {
			$message = sprintf(
				__( 'You are subscribed to this forum, and will receive emails for future topic activity. <a href="%1$s">Unsubscribe from %2$s</a>.', 'wporg-forums' ),
				self::get_subscription_url( get_current_user_id(), $term->term_id, $this->taxonomy ),
				esc_html( $term->name )
			);
		} elseif ( ! empty( $_GET['success'] ) && 'subscription-removed' === $_GET['success'] ) {
			$message = sprintf(
				__( 'You have been unsubscribed from future emails for %1$s.', 'wporg-forums' ),
				esc_html( $term->name )
			);
		} else {
			return;
		}

		echo '<div class="notice notice-info notice-alt with-dashicon">';
		echo '<span class="dashicons dashicons-email-alt"></span>';
		echo "<p>{$message}</p>";
		echo '</div>';
	}

	/**
	 * Handle a user subscribing to and unsubscribing from a term that is
	 * in the taxonomy of this instance.
	 *
	 * @param string $action The requested action to compare this function to
	 */
	public function term_subscribe_handler( $action = '' ) {
		// Bail if the actions aren't meant for this function.
		if ( ! in_array( $action, self::VALID_ACTIONS ) ) {
			return;
		}

		if ( ! bbp_is_subscriptions_active() ) {
			return false;
		}

		// Determine the term the request is for, overwrite with ?term_id if specified.
		$term = $this->get_current_term();
		if ( ! empty( $_GET['term_id'] ) ) {
			$term = get_term( intval( $_GET['term_id'] ), $this->taxonomy );
		}
		if ( ! $term ) {
			return;
		}

		$term_id = $term->term_id;
		$auth    = 'nonce';
		$user_id = isset( $_REQUEST['user_id'] ) ? $_REQUEST['user_id'] : get_current_user_id(); // Must pass nonce check below.

		// If a user_id + token is provided, verify the request and maybe use the provided user_id.
		if ( isset( $_GET['token'] ) ) {
			$auth    = 'token';
			$user_id = $this->has_valid_unsubscription_token();

			if ( ! $user_id ) {
				bbp_add_error( 'wporg_bbp_subscribe_invalid_token', __( '<strong>Error:</strong> Link expired!', 'wporg-forums' ) );
				return false;
			}

			// Require a POST request for verification. Gmail will bypass this for one-click unsubscriptions by POST'ing to the URL.
			if ( empty( $_POST ) ) {
				wp_die(
					sprintf(
						'<h1>%1$s</h1>' .
						'<p>%2$s</p>' .
						'<form method="POST" action="%3$s">' .
							'<input type="hidden" name="_wp_http_referer" value="%4$s" />' .
							'<input type="submit" name="confirm" value="%5$s">' .
							'&nbsp<a href="%6$s">%7$s</a>' .
						'</form>',
						get_bloginfo('name'),
						sprintf(
							/* translators: 1: Plugin, Theme, or Tag name. */
							esc_html__( 'Do you wish to unsubscribe from future emails for %s?', 'wporg-forums' ),
							$term->name
						),
						esc_attr( $_SERVER['REQUEST_URI'] ),
						esc_attr( wp_get_raw_referer() ),
						esc_attr__( 'Yes, unsubscribe me', 'wporg-forums' ),
						esc_url( get_term_link( $term ) ),
						esc_attr( sprintf(
							/* translators: 1: Plugin, Theme, or Tag name. */
							__( 'No, take me to the %s forum.', 'wporg-forums' ),
							$term->name
						) )
					)
				);
				exit;
			}

		}

		// Check for empty term id.
		if ( empty( $user_id ) ) {
			bbp_add_error( 'wporg_bbp_subscribe_logged_id', __( '<strong>Error:</strong> You must be logged in to do this!', 'wporg-forums' ) );

		// Check nonce.
		} elseif ( 'token' !== $auth && ! bbp_verify_nonce_request( 'toggle-term-subscription_' . $user_id . '_' . $term_id . '_' . $this->taxonomy ) ) {
			bbp_add_error( 'wporg_bbp_subscribe_nonce', __( '<strong>Error:</strong> Are you sure you wanted to do that?', 'wporg-forums' ) );

		// Check user's ability to spectate if attempting to subscribe to a term.
		} elseif ( ! user_can( $user_id, 'spectate' ) && 'wporg_bbp_subscribe_term' === $action ) {
			bbp_add_error( 'wporg_bbp_subscribe_permissions', __( '<strong>Error:</strong> You don\'t have permission to do this!', 'wporg-forums' ) );
		}

		if ( bbp_has_errors() ) {
			return;
		}

		$success       = false;
		$is_subscribed = self::is_user_subscribed_to_term( $user_id, $term_id );

		if ( 'wporg_bbp_unsubscribe_term' === $action ) {
			$success = ! $is_subscribed || self::remove_user_subscription( $user_id, $term_id );
		} elseif ( 'wporg_bbp_subscribe_term' === $action ) {
			$success = $is_subscribed || self::add_user_subscription( $user_id, $term_id );
		}

		// Redirect
		if ( bbp_is_subscriptions() ) {
			$redirect = bbp_get_subscriptions_permalink( $user_id );
		} else {
			$redirect = get_term_link( $term_id );
		}

		// Success!
		if ( true === $success ) {
			if ( 'wporg_bbp_unsubscribe_term' === $action ) {
				$redirect = add_query_arg( 'success', 'subscription-removed', $redirect );
			}

			bbp_redirect( $redirect );
		} elseif ( true === $is_subscribed && 'wporg_bbp_subscribe_term' === $action ) {
			/* translators: Term: topic tag */
			bbp_add_error( 'wporg_bbp_subscribe_user', __( '<strong>Error:</strong> There was a problem subscribing to that term!', 'wporg-forums' ) );
		} elseif ( false === $is_subscribed && 'wporg_bbp_unsubscribe_term' === $action ) {
			/* translators: Term: topic tag */
			bbp_add_error( 'wporg_bbp_unsubscribe_user', __( '<strong>Error:</strong> There was a problem unsubscribing from that term!', 'wporg-forums' ) );
		}
	}

	/**
	 * Use the existing bbp_notify_forum_subscribers() to send out term subscriptions for new topics.
	 * Avoid duplicate notifications for forum subscribers through the judicious use of filters within
	 * the function.
	 *
	 * @param int $topic_id The topic id
	 * @param int $forum_id The forum id
	 */
	public function notify_term_subscribers_of_new_topic( $topic_id, $forum_id ) {
		$terms = get_the_terms( $topic_id, $this->taxonomy );
		if ( ! $terms ) {
			return;
		}

		// Users that will be notified another way, or have already been notified.
		$notified_users = array();

		// Remove the author from being notified of their own topic.
		$notified_users[] = bbp_get_topic_author_id( $topic_id );

		// Get users who were already notified and exclude them.
		$forum_subscribers = bbp_get_forum_subscribers( $forum_id, true );
		if ( ! empty( $forum_subscribers ) ) {
			$notified_users = array_merge( $notified_users, $forum_subscribers );
		}

		// Replace forum-specific messaging with term subscription messaging.
		add_filter( 'bbp_forum_subscription_mail_message', array( $this, 'replace_forum_subscription_mail_message' ), 10, 4 );

		// Replace forum subscriber list with term subscribers, avoiding duplicates.
		add_filter( 'bbp_forum_subscription_user_ids', array( $this, 'add_term_subscribers' ) );

		// Personalize the emails.
		add_filter( 'wporg_bbp_subscription_email', array( $this, 'personalize_subscription_email' ) );

		foreach ( $terms as $term ) {
			$subscribers = $this->get_term_subscribers( $term->term_id );
			if ( ! $subscribers ) {
				continue;
			}

			$subscribers = array_diff( $subscribers, $notified_users );
			if ( ! $subscribers ) {
				continue;
			}

			$this->term        = $term;
			$this->subscribers = $subscribers;

			// Actually notify the term subscribers.
			bbp_notify_forum_subscribers( $topic_id, $forum_id );

			// Don't email them twice.
			$notified_users = array_merge( $notified_users, $subscribers );
		}

		// Reset
		$this->term        = false;
		$this->subscribers = array();

		// Remove filters.
		remove_filter( 'bbp_forum_subscription_mail_message', array( $this, 'replace_forum_subscription_mail_message' ) );
		remove_filter( 'bbp_forum_subscription_user_ids',     array( $this, 'add_term_subscribers' ) );
		remove_filter( 'wporg_bbp_subscription_email',        array( $this, 'personalize_subscription_email' ) );
	}

	/**
	 * Temporarily replace the forum subscriber list with any unincluded term subscribers.
	 */
	public function add_term_subscribers( $users ) {
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

		$message = sprintf(
			/* translators: 1: Author, 2: Forum message, 3: Link to topic, 4: Descriptive text of why they're getting the email */
			__( '%1$s wrote:

%2$s

Link: %3$s

-----------

To reply, visit the above link and log in.
Note that replying to this email has no effect.

%4$s

To unsubscribe from future emails, click here:
####UNSUB_LINK####', 'wporg-forums' ),
			$topic_author_name,
			$topic_content,
			$topic_url,
			sprintf(
				$this->labels['receipt'],
				$this->get_current_term()->name
			)
		);

		return $message;
	}

	/**
	 * Replace the forum subscription subject/title with term-specific messaging.
	 *
	 * Eg, before it would be similar to `[Plugins] This is my thread title`.
	 * This changes it to `[Plugin Name] Thread title`.
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
	 */
	public function notify_term_subscribers_of_new_reply( $reply_id, $topic_id, $forum_id ) {
		$terms = get_the_terms( $topic_id, $this->taxonomy );
		if ( ! $terms ) {
			return;
		}

		// Users that will be notified another way, or have already been notified.
		$notified_users = array();

		// Remove the author from being notified of their own topic.
		$notified_users[] = bbp_get_reply_author_id( $reply_id );

		// Get users who were already notified and exclude them.
		$topic_subscribers = bbp_get_topic_subscribers( $topic_id, true );
		if ( ! empty( $topic_subscribers ) ) {
			$notified_users = array_merge( $notified_users, $topic_subscribers );
		}

		// Replace topic-specific messaging with term subscription messaging.
		add_filter( 'bbp_subscription_mail_message', array( $this, 'replace_topic_subscription_mail_message' ), 10, 3 );

		// Replace forum subscriber list with term subscribers, avoiding duplicates.
		add_filter( 'bbp_topic_subscription_user_ids', array( $this, 'add_term_subscribers' ) );

		// Personalize the emails.
		add_filter( 'wporg_bbp_subscription_email', array( $this, 'personalize_subscription_email' ) );

		foreach ( $terms as $term ) {
			$subscribers = $this->get_term_subscribers( $term->term_id );
			if ( ! $subscribers ) {
				continue;
			}

			$subscribers = array_diff( $subscribers, $notified_users );
			if ( ! $subscribers ) {
				continue;
			}

			$this->term        = $term;
			$this->subscribers = $subscribers;

			// Actually notify our term subscribers.
			bbp_notify_topic_subscribers( $reply_id, $topic_id, $forum_id );

			// Don't email them twice.
			$notified_users = array_merge( $notified_users, $subscribers );
		}

		// Reset
		$this->term        = false;
		$this->subscribers = array();

		// Remove filters.
		remove_filter( 'bbp_subscription_mail_message',   array( $this, 'replace_topic_subscription_mail_message' ) );
		remove_filter( 'bbp_topic_subscription_user_ids', array( $this, 'add_term_subscribers' ) );
		remove_filter( 'wporg_bbp_subscription_email',    array( $this, 'personalize_subscription_email' ) );
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

		$message = sprintf(
			/* translators: 1: Author, 2: Forum message, 3: Link to topic, 4: Descriptive text of why they're getting the email */
			__( '%1$s wrote:

%2$s

Link: %3$s

-----------

To reply, visit the above link and log in.
Note that replying to this email has no effect.

%4$s

To unsubscribe from future emails, click here:
####UNSUB_LINK####', 'wporg-forums' ),
			$reply_author_name,
			$reply_content,
			$reply_url,
			sprintf(
				$this->labels['receipt'],
				$this->get_current_term()->name
			)
		);

		return $message;
	}

	/**
	 * Replace the topic subscription subject/title with term-specific messaging.
	 *
	 * Eg, before it would be similar to `[Plugins] This is my thread title`.
	 * This changes it to `[Plugin Name] Thread title`.
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
	 * Personalize subscription emails by adding an unsubscription link.
	 * 
	 * @param array $email_parts The email parts.
	 * @return The email parts.
	 */
	public function personalize_subscription_email( $email_parts ) {
		// get_tokenised_unsubscribe_url() will validate the user object.
		$user       = get_user_by( 'email', $email_parts['to'] );
		$unsub_link = $this->get_tokenised_unsubscribe_url( $user, $this->term );

		if ( ! $unsub_link ) {
			return $email_parts;
		}

		$email_parts['message'] = str_replace(
			'####UNSUB_LINK####',
			'<' . esc_url_raw( $unsub_link ) . '>',
			$email_parts['message']
		);

		$email_parts['headers'][] = 'List-Unsubscribe: <' . esc_url_raw( $unsub_link ) . '>';
		$email_parts['headers'][] = 'List-Unsubscribe-Post: List-Unsubscribe=One-Click';

		return $email_parts;
	}

	/**
	 * Get the WP_Term instance for the currently displayed item.
	 */
	protected function get_current_term() {
		if ( $this->term ) {
			return $this->term;
		}

		// The currently queried tag.
		if (
			bbp_is_topic_tag() &&
			( $term = get_queried_object() ) &&
			( $term instanceof \WP_Term )
		) {
			return $term;
		}

		// The current directory loaded.
		if (
			$this->directory &&
			bbp_is_single_view() &&
			$this->directory->compat() == bbp_get_view_id()
		) {
			return $this->directory->term;
		}

		return false;
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
					$unsub_url = self::get_subscription_url( $user_id, $term->term_id, $this->taxonomy );
					echo '<a href="' . esc_url( get_term_link( $term->term_id ) ) . '">' . esc_html( $term->name ) . '</a>';
					echo ' (<a href="' . esc_url( $unsub_url ) . '">' . esc_html( 'Unsubscribe', 'wporg-forums' ) . '</a>)';
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
		if ( empty( $user_id ) || empty( $taxonomy ) ) {
			return false;
		}

		$subscriptions = get_terms( array(
			'taxonomy'   => $taxonomy,
			'meta_key'   => self::META_KEY,
			'meta_value' => $user_id,
		) );

		// Default to false if empty.
		$subscriptions = $subscriptions ?: false;

		return apply_filters( 'wporg_bbp_get_user_taxonomy_subscriptions', $subscriptions, $user_id, $taxonomy );
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
	public static function add_user_subscription( $user_id, $term_id ) {
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
	public static function remove_user_subscription( $user_id, $term_id ) {
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
	public static function get_subscription_url( $user_id, $term_id, $taxonomy ) {
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
			$action = 'wporg_bbp_unsubscribe_term';
		} else {
			$action = 'wporg_bbp_subscribe_term';
		}

		if ( bbp_is_subscriptions() ) {
			$permalink = bbp_get_subscriptions_permalink( $user_id );
		} else {
			$permalink = get_term_link( $term_id );
		}

		$url = wp_nonce_url(
			add_query_arg( compact( 'action', 'term_id' ), $permalink ),
			'toggle-term-subscription_' . $user_id . '_' . $term_id . '_' . $taxonomy
		);

		if ( $user_id != get_current_user_id() ) {
			$url = add_query_arg( 'user_id', $user_id, $url );
		}

		return esc_url( $url );
	}

	/**
	 * Define a custom salt for wp_hash( ..., 'forum_subcriptions' ).
	 */
	public function forum_subscriptions_salt( $salt, $scheme ) {
		if ( 'forum_subscriptions' === $scheme && defined( 'FORUM_SUBSCRIPTIONS_SALT' ) ) {
			$salt = FORUM_SUBSCRIPTIONS_SALT;
		}

		return $salt;
	}

	/**
	 * Generates a unsubscription token for a user.
	 *
	 * The token form is 'user_id|expiry|hash', and dependant upon the user email, password, and term.
	 *
	 * @param WP_Term $term   The user the token should be for.
	 * @param WP_User $user   The user the token should be for.
	 * @param int     $expiry The expiry of the token. Optional, only required for verifying tokens.
	 * @return string|bool The hashed token, false on failure.
	 */
	protected static function generate_unsubscribe_token( $term, $user, $expiry = 0 ) {
		if ( ! $term || ! $user ) {
			return false;
		}
		if ( ! $expiry ) {
			$expiry = time() + self::UNSUBSCRIBE_LIFETIME;
		}

		$expiry    = intval( $expiry );
		$pass_frag = substr( $user->user_pass, 8, 4 ); // Password fragment used by cookie auth.
		$key       = wp_hash( $term->term_id . '|' . $term->taxonomy . '|' . $user->user_email . '|' . $pass_frag . '|' . $expiry, 'forum_subscriptions' );
		$hash      = hash_hmac( 'sha256',  $term->term_id . '|' . $term->taxonomy . '|' . $user->user_email . '|' . $expiry, $key );

		return $user->ID . '|' . $expiry . '|' . $hash;
	}

	/**
	 * Validate if the current request has a valid tokenised unsubscription link.
	 * 
	 * @return bool|int User ID on success, false on failure.
	 */
	protected function has_valid_unsubscription_token() {
		if (
			! isset( $_GET['token'] ) ||
			2 !== substr_count( $_GET['token'], '|' )
		) {
			return false;
		}

		$provided_token            = rtrim( $_GET['token'], '>' );
		list( $user_id, $expiry, ) = explode( '|', $provided_token );
		$term                      = $this->get_current_term();
		$user                      = get_user_by( 'id', intval( $user_id ) );
		$expected_token            = self::generate_unsubscribe_token( $term, $user, $expiry );

		if (
			$expiry > time() &&
			hash_equals( $expected_token, $provided_token )
		) {
			return $user->ID;
		}

		return false;
	}

	/**
	 * Generate a tokenised unsubscription link for a given user & term.
	 * 
	 * This link can be used without being logged in.
	 * 
	 * @param \WP_User $user The user to generate the link for.
	 * @param \WP_Term $term The term to generate the link for.
	 * 
	 * @return bool|string The URL, or false upon failure.
	 */
	public static function get_tokenised_unsubscribe_url( $user, $term ) {
		$token  = self::generate_unsubscribe_token( $term, $user );
		if ( ! $token ) {
			return false;
		}

		return add_query_arg(
			array(
				'action'   => 'wporg_bbp_unsubscribe_term',
				'token'    => $token,
			),
			// We don't include the term_id in the URL, and instead rely upon the term coming from the URL
			get_term_link( $term )
		);
	}

	/**
	 * Generate an unsubscription link for use in a Template.
	 * 
	 * @param array $args
	 * @return string
	 */
	public static function get_subscription_link( $args ) {
		$r = bbp_parse_args( $args, array(
			'user_id'     => get_current_user_id(),
			'term_id'     => 0,
			'taxonomy'    => 'topic-tag',
			'class'       => 'button',
			'subscribe'   => esc_html__( 'Subscribe to this topic tag', 'wporg-forums' ),
			'unsubscribe' => esc_html__( 'Unsubscribe from this topic tag', 'wporg-forums' ),
			'js_confirm'  => esc_html__( 'Are you sure you wish to subscribe by email to all future topics created in this tag?', 'wporg-forums' ),
		), 'get_term_subscription_link' );

		$user_id  = $r['user_id'];
		$term_id  = $r['term_id'];
		$taxonomy = $r['taxonomy'];

		if ( empty( $user_id ) || empty( $term_id ) || empty( $taxonomy ) ) {
			return false;
		}

		if ( ! user_can( $user_id, 'spectate' ) ) {
			return false;
		}

		$url = self::get_subscription_url( $user_id, $term_id, $taxonomy );
		if ( self::is_user_subscribed_to_term( $user_id, $term_id ) ) {
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
}
