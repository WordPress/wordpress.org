<?php
namespace WordPressdotorg\Forums;

/**
 * Support Forum Email modifications.
 * 
 * This provides an override to filter outgoing bbPress emails to 'unroll'
 * subscription emails from a set of BCC's to individual emails, and sets up the
 * plugin/theme context for notifications bbPress sends after moderation.
 *
 * It also gives topic subscription emails a link to unsubscribe without logging in.
 */
class Emails {

	/**
	 * Placeholder in topic subscription emails for the recipient's unsubscription link.
	 *
	 * @var string
	 */
	const TOPIC_UNSUBSCRIBE_PLACEHOLDER = '####TOPIC_UNSUB_LINK####';

	/**
	 * Length of time topic unsubscription links are valid.
	 *
	 * @var int
	 */
	const TOPIC_UNSUBSCRIBE_LIFETIME = WEEK_IN_SECONDS;

	/**
	 * The topic whose subscribers are being notified.
	 *
	 * @var int
	 */
	protected $notify_topic_id = 0;

	public function __construct() {
		// Forum subscribers. This is also for Term (Plugin/Theme/Tag) subscriptions new topics.
		add_action( 'bbp_pre_notify_forum_subscribers',  [ $this, 'start_unroll' ] );
		add_action( 'bbp_post_notify_forum_subscribers', [ $this, 'stop_unroll' ] );

		// Topic subscribers. This is also for Term (Plugin/Theme/Tag) subscriptions new replies.
		add_action( 'bbp_pre_notify_subscribers',  [ $this, 'start_unroll' ] );
		add_action( 'bbp_post_notify_subscribers', [ $this, 'stop_unroll' ] );

		// Give topic subscribers a link to unsubscribe from the topic.
		add_action( 'bbp_pre_notify_subscribers', [ $this, 'start_topic_unsubscribe_links' ], 10, 2 );
		add_action( 'bbp_post_notify_subscribers', [ $this, 'stop_topic_unsubscribe_links' ] );
		add_action( 'bbp_template_redirect', [ $this, 'topic_unsubscribe_handler' ] );

		// Before any subscription notification for an approved or unspammed topic/reply goes out.
		add_action( 'bbp_deferred_topic_published', [ $this, 'init_compat_for_deferred_topic' ], 5 );
		add_action( 'bbp_deferred_reply_published', [ $this, 'init_compat_for_deferred_reply' ], 5, 2 );
	}

	// --- Notifications sent after moderation...

	/**
	 * Sets up the plugin/theme context for a topic that bbPress announces after moderation.
	 *
	 * Email subjects are prefixed with the plugin or theme name, which is otherwise only
	 * known when the request came from that plugin or theme's support forum.
	 *
	 * @param int $topic_id Topic ID.
	 */
	public function init_compat_for_deferred_topic( $topic_id ) {
		$this->init_compat_for_topic( $topic_id );
	}

	/**
	 * Sets up the plugin/theme context for a reply that bbPress announces after moderation.
	 *
	 * @param int $reply_id Reply ID.
	 * @param int $topic_id Topic ID.
	 */
	public function init_compat_for_deferred_reply( $reply_id, $topic_id ) {
		$this->init_compat_for_topic( $topic_id );
	}

	/**
	 * Initializes the plugin and theme compat classes for a topic.
	 *
	 * @param int $topic_id Topic ID.
	 */
	private function init_compat_for_topic( $topic_id ) {
		$forums = Plugin::get_instance();

		// Only loaded on the main forums.
		if ( ! $forums->plugins ) {
			return;
		}

		$forums->plugins->init_for_topic( $topic_id );
		$forums->themes->init_for_topic( $topic_id );
	}

	// --- Email BCC unrolling...

	/**
	 * Attached to the filter to start filtering outgoing emails on.
	 */
	public function start_unroll() {
		add_filter( 'pre_wp_mail', [ $this, 'pre_wp_mail_unroll' ], 1, 2 );
	}

	/**
	 * Attached to the filter to stop filtering outgoing emails on.
	 */
	public function stop_unroll() {
		remove_filter( 'pre_wp_mail', [ $this, 'pre_wp_mail_unroll' ], 1 );
	}

	/**
	 * Unroll wp_mail() BCC'd emails into individual emails.
	 */
	public function pre_wp_mail_unroll( $filter_return, $attrs ) {
		static $recursive = false;
		// Short circuit if we're the one who called wp_mail().
		if ( $recursive ) {
			return $filter_return;
		}

		$attrs = wp_parse_args(
			$attrs,
			[
				'to'          => [],
				'subject'     => '',
				'message'     => '',
				'headers'     => [],
				'attachments' => [],
			]
		);

		if ( ! is_array( $attrs['headers'] ) ) {
			$attrs['headers'] = explode( "\n", str_replace( "\r\n", "\n", $attrs['headers'] ) );
		}

		$no_reply    = apply_filters( 'bbp_subscription_to_email', bbp_get_do_not_reply_address() );
		$dest_emails = is_array( $attrs['to'] ) ? $attrs['to'] : explode( ',', $attrs['to'] );

		// Pull out CC's and BCC's to a separate array.
		foreach ( $attrs['headers'] as $i => $header ) {
			if ( ! str_contains( $header, ':' ) ) {
				return;
			}

			list( $name, $value ) = explode( ':', trim( $header ), 2 );
			$name                 = strtolower( trim( $name ) );
	
			if ( 'cc' === $name || 'bcc' === $name ) {
				unset( $attrs['headers'][ $i ] );
				$dest_emails[] = trim( $value );
			}
		}

		// Remove the noreply email, it was probably set as the $to address.
		$dest_emails = array_diff( $dest_emails, [ $no_reply ] );

		// This shouldn't happen, but if it does, let wp_mail() do it's thing instead.
		// NOTE: We still process if there was only one to/cc/bcc address, to ensure the below custom filter gets run.
		if ( empty( $dest_emails ) ) {
			return $filter_return;
		}

		// Prevent recursive calls.
		$recursive = true;
		foreach ( $dest_emails as $to ) {
			// Use a fresh copy of the attrs.
			$email_attrs = $attrs;

			// The to is always going to be the current recipient.
			$email_attrs['to'] = $to;

			// Filter for w.org plugins to personalize emails.
			$email_attrs = apply_filters( 'wporg_bbp_subscription_email', $email_attrs );

			// Send the filtered email.
			$filter_return = wp_mail(
				$email_attrs['to'],
				$email_attrs['subject'],
				$email_attrs['message'],
				$email_attrs['headers'],
				$email_attrs['attachments']
			);
		}

		$recursive = false;

		// We've sent the emails, we return the last emails result here, even tho calling function probably won't look at it.
		return $filter_return;
	}

	// --- Topic subscription unsubscription links...

	/**
	 * Starts adding unsubscription links to the topic subscription emails about to be sent.
	 *
	 * @param int $reply_id Reply ID.
	 * @param int $topic_id Topic ID.
	 */
	public function start_topic_unsubscribe_links( $reply_id, $topic_id ) {
		$this->notify_topic_id = (int) $topic_id;

		add_filter( 'wporg_bbp_subscription_email', [ $this, 'add_topic_unsubscribe_link' ] );
	}

	/**
	 * Stops adding unsubscription links to topic subscription emails.
	 */
	public function stop_topic_unsubscribe_links() {
		remove_filter( 'wporg_bbp_subscription_email', [ $this, 'add_topic_unsubscribe_link' ] );

		$this->notify_topic_id = 0;
	}

	/**
	 * Adds the recipient's unsubscription link and headers to a topic subscription email.
	 *
	 * Term subscription emails replace the message, placeholder included, and add their own link.
	 *
	 * @param array $email_parts The email parts.
	 * @return array The email parts.
	 */
	public function add_topic_unsubscribe_link( $email_parts ) {
		if ( ! str_contains( $email_parts['message'], self::TOPIC_UNSUBSCRIBE_PLACEHOLDER ) ) {
			return $email_parts;
		}

		$user       = get_user_by( 'email', $email_parts['to'] );
		$unsub_link = $user ? self::get_topic_unsubscribe_url( $user, $this->notify_topic_id ) : '';

		// Without a link, point to the topic, where a logged-in user can unsubscribe.
		if ( ! $unsub_link ) {
			$email_parts['message'] = str_replace(
				self::TOPIC_UNSUBSCRIBE_PLACEHOLDER,
				bbp_get_topic_permalink( $this->notify_topic_id ),
				$email_parts['message']
			);

			return $email_parts;
		}

		$email_parts['message'] = str_replace(
			self::TOPIC_UNSUBSCRIBE_PLACEHOLDER,
			'<' . esc_url_raw( $unsub_link ) . '>',
			$email_parts['message']
		);

		$email_parts['headers'][] = 'List-Unsubscribe: <' . esc_url_raw( $unsub_link ) . '>';
		$email_parts['headers'][] = 'List-Unsubscribe-Post: List-Unsubscribe=One-Click';

		return $email_parts;
	}

	/**
	 * Generates a tokenized link that unsubscribes a user from a topic without logging in.
	 *
	 * @param \WP_User $user     The user to generate the link for.
	 * @param int      $topic_id The topic to generate the link for.
	 * @return string The URL, or an empty string if the topic doesn't exist.
	 */
	public static function get_topic_unsubscribe_url( $user, $topic_id ) {
		$topic_link = bbp_get_topic_permalink( $topic_id );
		if ( ! $topic_link ) {
			return '';
		}

		// The token is verified against the topic in the URL path, not a query arg.
		return add_query_arg(
			array(
				'action' => 'wporg_bbp_unsubscribe_topic',
				'token'  => self::generate_topic_unsubscribe_token( $user, $topic_id ),
			),
			$topic_link
		);
	}

	/**
	 * Generates a topic unsubscription token for a user.
	 *
	 * The token form is 'user_id|expiry|hash', and depends on the topic, the user's email, and password.
	 *
	 * @param \WP_User $user     The user the token is for.
	 * @param int      $topic_id The topic the token is for.
	 * @param int      $expiry   Optional. The expiry of the token, only required for verifying tokens.
	 * @return string The token.
	 */
	protected static function generate_topic_unsubscribe_token( $user, $topic_id, $expiry = 0 ) {
		if ( ! $expiry ) {
			$expiry = time() + self::TOPIC_UNSUBSCRIBE_LIFETIME;
		}

		$data      = 'topic|' . (int) $topic_id . '|' . $user->user_email . '|' . (int) $expiry;
		$pass_frag = substr( $user->user_pass, 8, 4 ); // Password fragment used by cookie auth.
		$key       = wp_hash( $data . '|' . $pass_frag, 'forum_subscriptions' );

		return $user->ID . '|' . (int) $expiry . '|' . hash_hmac( 'sha256', $data, $key );
	}

	/**
	 * Gets the user a topic unsubscription token is valid for.
	 *
	 * @param string $token    The provided token.
	 * @param int    $topic_id The topic the token has to be for.
	 * @return int The user ID, or 0 if the token is invalid or expired.
	 */
	protected static function get_topic_unsubscribe_token_user_id( $token, $topic_id ) {
		if ( 2 !== substr_count( $token, '|' ) ) {
			return 0;
		}

		list( $user_id, $expiry ) = explode( '|', $token );

		$user = get_user_by( 'id', (int) $user_id );
		if ( ! $user || (int) $expiry <= time() ) {
			return 0;
		}

		$expected_token = self::generate_topic_unsubscribe_token( $user, $topic_id, (int) $expiry );

		return hash_equals( $expected_token, $token ) ? $user->ID : 0;
	}

	/**
	 * Unsubscribes a user from a topic through the tokenized link in a topic subscription email.
	 *
	 * Opening the link asks for confirmation. Mail clients unsubscribe in one click by POSTing to it.
	 */
	public function topic_unsubscribe_handler() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- The token authorizes the request.
		if (
			! isset( $_GET['action'], $_GET['token'] ) ||
			'wporg_bbp_unsubscribe_topic' !== $_GET['action'] ||
			! bbp_is_subscriptions_active() ||
			! bbp_is_single_topic()
		) {
			return;
		}

		$topic_id = bbp_get_topic_id();
		$token    = rtrim( wp_unslash( $_GET['token'] ), '>' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Compared against the expected token.
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$user_id = self::get_topic_unsubscribe_token_user_id( $token, $topic_id );
		if ( ! $user_id ) {
			bbp_add_error( 'wporg_bbp_unsubscribe_topic_token', __( '<strong>Error:</strong> Link expired!', 'wporg-forums' ) );
			return;
		}

		$topic_title = wp_strip_all_tags( bbp_get_topic_title( $topic_id ) ); // Drops the NSFW <abbr> prefix markup.
		$topic_link  = bbp_get_topic_permalink( $topic_id );

		// Require a POST request, so that link scanners and prefetching don't unsubscribe anyone.
		if ( ! bbp_is_post_request() ) {
			wp_die(
				sprintf(
					'<h1>%1$s</h1>' .
					'<p>%2$s</p>' .
					'<form method="POST" action="%3$s">' .
						'<input type="submit" value="%4$s"> ' .
						'<a href="%5$s">%6$s</a>' .
					'</form>',
					esc_html( get_bloginfo( 'name' ) ),
					sprintf(
						/* translators: %s: Topic title. */
						esc_html__( 'Do you wish to unsubscribe from future emails for “%s”?', 'wporg-forums' ),
						esc_html( $topic_title )
					),
					esc_url(
						add_query_arg(
							array(
								'action' => 'wporg_bbp_unsubscribe_topic',
								'token'  => $token,
							),
							$topic_link
						)
					),
					esc_attr__( 'Yes, unsubscribe me', 'wporg-forums' ),
					esc_url( $topic_link ),
					esc_html__( 'No, take me to the topic.', 'wporg-forums' )
				),
				esc_html( get_bloginfo( 'name' ) ),
				array( 'response' => 200 )
			);
		}

		bbp_remove_user_subscription( $user_id, $topic_id );

		wp_die(
			sprintf(
				'<h1>%1$s</h1>' .
				'<p>%2$s</p>' .
				'<p><a href="%3$s">%4$s</a></p>',
				esc_html( get_bloginfo( 'name' ) ),
				sprintf(
					/* translators: %s: Topic title. */
					esc_html__( 'You have been unsubscribed from future emails for “%s”.', 'wporg-forums' ),
					esc_html( $topic_title )
				),
				esc_url( $topic_link ),
				esc_html__( 'Return to the topic.', 'wporg-forums' )
			),
			esc_html( get_bloginfo( 'name' ) ),
			array( 'response' => 200 )
		);
	}
}
