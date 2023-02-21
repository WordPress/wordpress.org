<?php
namespace WordPressdotorg\Forums;

/**
 * Support Forum Email modifications.
 * 
 * This provides some overrides to work around limitations / bugs in bbPress related to email.
 *  1. Filters outgoing bbPress emails to 'unroll' subscription emails from a set of BCC's to individual emails.
 *  2. Sends subscription emails upon approval for topics/replies.
 */
class Emails {
	const SUBSCRIPTIONS_TRIGGER_KEY = '_wporg_trigger_notifications_on_approve';

	public function __construct() {
		// Forum subscribers. This is also for Term (Plugin/Theme/Tag) subscriptions new topics.
		add_action( 'bbp_pre_notify_forum_subscribers',  [ $this, 'start_unroll' ] );
		add_action( 'bbp_post_notify_forum_subscribers', [ $this, 'stop_unroll' ] );

		// Topic subscribers. This is also for Term (Plugin/Theme/Tag) subscriptions new replies.
		add_action( 'bbp_pre_notify_subscribers',  [ $this, 'start_unroll' ] );
		add_action( 'bbp_post_notify_subscribers', [ $this, 'stop_unroll' ] );

		// Subscriptions - Send subscriptions after approval/unspam.
		add_action( 'bbp_new_topic', [ $this, 'bbp_new_topic' ], 20, 2 );
		add_action( 'bbp_new_reply', [ $this, 'bbp_new_reply' ], 20, 3 );

		add_action( 'bbp_unspammed_topic', [ $this, 'maybe_trigger_bbp_new_topic' ], 10, 1 );
		add_action( 'bbp_approved_topic',  [ $this, 'maybe_trigger_bbp_new_topic' ], 10, 1 );
		//add_action( 'bbp_untrashed_topic', [ $this, 'maybe_trigger_bbp_new_topic' ], 10, 1 );

		add_action( 'bbp_unspammed_reply', [ $this, 'maybe_trigger_bbp_new_reply' ], 10, 1 );
		add_action( 'bbp_approved_reply',  [ $this, 'maybe_trigger_bbp_new_reply' ], 10, 1 );
		//add_action( 'bbp_untrashed_reply', [ $this, 'maybe_trigger_bbp_new_reply' ], 10, 1 );
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

	// --- bbPress not sending notifications if it's caught in spam/moderation queue

	/**
	 * Add postmeta to new unpublished replies, to send notifications once it's published.
	 */
	public function bbp_new_reply( $reply_id, $topic_id, $forum_id ) {
		if ( bbp_is_reply_published( $reply_id ) && bbp_is_topic_public( $topic_id ) ) {
			return;
		}

		add_post_meta( $reply_id, self::SUBSCRIPTIONS_TRIGGER_KEY, 1 );
	}

	/**
	 * Add postmeta to new unpublished topics, to send notifications once it's published.
	 */
	public function bbp_new_topic( $topic_id, $forum_id ) {
		if ( bbp_is_topic_public( $topic_id ) ) {
			return;
		}

		add_post_meta( $topic_id, self::SUBSCRIPTIONS_TRIGGER_KEY, 1 );
	}

	/**
	 * Send forum subscriptions for a previously-unpublished topic.
	 */
	public function maybe_trigger_bbp_new_topic( $topic_id ) {
		if ( ! get_post_meta( $topic_id, self::SUBSCRIPTIONS_TRIGGER_KEY, true) ) {
			return;
		}

		if ( ! bbp_is_topic_public( $topic_id ) ) {
			return;
		}

		$forum_id     = bbp_get_topic_forum_id( $topic_id );
		$topic_author = bbp_get_topic_author_id( $topic_id );

		// Setup the forum compat classes, as used by email filters for subject changes.
		$forums = Plugin::get_instance();
		if ( $forums->plugins ) {
			$forums->plugins->init_for_topic( $topic_id );
			$forums->themes->init_for_topic( $topic_id );
		}

		// For performance reasons, we've removed the bbPress bbp_update_topic() method, and replaced it with our slightly altered variant.
		$bbp_update_topic = [ Plugin::get_instance()->dropin, 'bbp_update_topic' ];
		if ( ! has_filter( 'bbp_new_topic', $bbp_update_topic ) ) {
			// Fallback to the default bbPress function if ours isn't present (ie. Rosetta, or when we've successfully removed our performance customizations).
			$bbp_update_topic = 'bbp_update_topic';
		}

		// Remove the bbPress topic update handler
		remove_action( 'bbp_new_topic', $bbp_update_topic );

		// Call the bbp_new_topic action..
		do_action( 'bbp_new_topic', $topic_id, $forum_id, array(), $topic_author );

		add_action( 'bbp_new_topic', $bbp_update_topic, 10, 5 ); // bbPress requests 5, but calls it with 4..

		delete_post_meta( $topic_id, self::SUBSCRIPTIONS_TRIGGER_KEY );
	}

	/**
	 * Send topic subscriptions for a previously-unpublished topic.
	 */
	public function maybe_trigger_bbp_new_reply( $reply_id ) {
		if ( ! get_post_meta( $reply_id, self::SUBSCRIPTIONS_TRIGGER_KEY, true ) ) {
			return;
		}

		$topic_id     = bbp_get_reply_topic_id( $reply_id );
		$forum_id     = bbp_get_topic_forum_id( $topic_id );
		$reply_author = bbp_get_reply_author_id( $reply_id );

		if ( ! bbp_is_reply_published( $reply_id ) || ! bbp_is_topic_public( $topic_id ) ) {
			return;
		}

		// Setup the forum compat classes, as used by email filters for subject changes.
		$forums = Plugin::get_instance();
		if ( $forums->plugins ) {
			$forums->plugins->init_for_topic( $topic_id );
			$forums->themes->init_for_topic( $topic_id );
		}

		// For performance reasons, we've removed the bbPress bbp_update_reply() method, and replaced it with our slightly altered variant.
		$bbp_update_reply = [ Plugin::get_instance()->dropin, 'bbp_update_reply' ];
		if ( ! has_filter( 'bbp_new_reply', $bbp_update_reply ) ) {
			// Fallback to the default bbPress function if ours isn't present (ie. Rosetta, or when we've successfully removed our performance customizations).
			$bbp_update_reply = 'bbp_update_reply';
		}

		// Remove the bbPress topic update handler
		remove_action( 'bbp_new_reply', $bbp_update_reply );

		do_action( 'bbp_new_reply', $reply_id, $topic_id, $forum_id, array(), $reply_author, false, false );

		add_action( 'bbp_new_reply',  $bbp_update_reply, 10, 7 );

		delete_post_meta( $reply_id, self::SUBSCRIPTIONS_TRIGGER_KEY );
	}

}
