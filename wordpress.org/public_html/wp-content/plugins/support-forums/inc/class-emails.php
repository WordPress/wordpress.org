<?php
namespace WordPressdotorg\Forums;

/**
 * Support Forum Email modifications.
 * 
 * This filters outgoing bbPress emails to 'unroll' subscription emails from a set of BCC's to individual emails.
 */
class Emails {

	public function __construct() {
		// Forum subscribers. This is also for Term (Plugin/Theme/Tag) subscriptions new topics.
		add_action( 'bbp_pre_notify_forum_subscribers',  [ $this, 'start_unroll' ] );
		add_action( 'bbp_post_notify_forum_subscribers', [ $this, 'stop_unroll' ] );

		// Topic subscribers. This is also for Term (Plugin/Theme/Tag) subscriptions new replies.
		add_action( 'bbp_pre_notify_subscribers',  [ $this, 'start_unroll' ] );
		add_action( 'bbp_post_notify_subscribers', [ $this, 'stop_unroll' ] );
	}

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

		$no_reply    = apply_filters( 'bbp_subscription_to_email', bbp_get_do_not_reply_address() );
		$dest_emails = is_array( $attrs['to'] ) ? $attrs['to'] : explode( ',', $attrs['to'] );
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
			// The to is always the current recipient.
			$attrs['to'] = $to;

			// Filter for w.org plugins to personalize emails.
			$attrs = apply_filters( 'wporg_bbp_subscription_email', $attrs );
	
			$filter_return = wp_mail(
				$attrs['to'],
				$attrs['subject'],
				$attrs['message'],
				$attrs['headers'],
				$attrs['attachments']
			);
		}

		$recursive = false;

		// We've sent the emails, we return the last emails result here, even tho calling function probably won't look at it.
		return $filter_return;
	}
}