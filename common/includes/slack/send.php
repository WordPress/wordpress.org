<?php

namespace Dotorg\Slack;

class Send {

	/**
	 * The constant overrides the property,
	 * which is set with the testing() method.
	 */
	const testing = false;
	protected $testing;

	protected $webhook;
	protected $user;
	protected $icon;
	protected $username;
	protected $text = '';
	protected $attachments = array();
	protected $link_names = 0;

	function __construct( $webhook ) {
		$this->webhook = $webhook;
	}

	function set_user( User $user ) {
		$this->user = $user;
	}

	function set_icon( $icon ) {
		$this->icon = $icon;
	}

	function get_icon() {
		if ( $this->icon ) {
			return $this->icon;
		} elseif ( $this->user ) {
			return $this->user->get_icon();
		}
		return ':wordpress:';
	}

	function set_username( $username ) {
		$this->username = $username;
	}

	function get_username() {
		if ( $this->username ) {
			return $this->username;
		} elseif ( $this->user ) {
			return $this->user->get_name();
		}
		return 'Bot';
	}

	function get_text() {
		return $this->text;
	}

	function set_text( $text ) {
		$this->text = $text;
	}

	function add_attachment( $attachment ) {
		$this->attachments[] = $attachment;
	}

	function get_attachments() {
		return $this->attachments;
	}

	function set_link_names( $bool = false ) {
		$this->link_names = (int) $bool;
	}

	function get_payload() {
		$icon = $this->get_icon();
		$icon_type = ':' === substr( $icon, 0, 1 ) ? 'icon_emoji' : 'icon_url';

		$payload = array(
			$icon_type    => $icon,
			'username'    => $this->get_username(),
			'attachments' => $this->get_attachments(),
			'link_names'  => $this->link_names,
		);

		if ( $text = $this->get_text() ) {
			$payload['text'] = $text;
		}
		return $payload;
	}

	function testing( $enabled = null ) {
		if ( null === $enabled ) {
			// If the constant is true, it overrides a public testing(false) call.
			if ( self::testing ) {
				return true;
			}
			// testing(true)
			if ( isset( $this->testing ) ) {
				return $this->testing;
			}
			return false;
		}
		$this->testing = (bool) $enabled;
	}

	function send( $channel, $thread = false ) {
		if ( $this->testing() ) {
			$this->set_text( "[$channel] " . $this->get_text() );
			$channel = '#testing-slack';
		}

		$payload = $this->get_payload();
		$payload['channel'] = $channel;

		if ( $thread ) {
			$payload['thread_ts'] = $thread;

			// Setting this to true will broacast a threaded reply to the channel too.
			// Since outgoing webhooks don't send the broadcast status, this is skipped for now.
			// $payload['reply_broadcast'] = true;
		}

		// Filter to allow short-circuit by Matrix
		if ( apply_filters( 'skip_slack_posting', false, $channel, $payload[ 'text' ] ) ) {
			return;
		}

		# error_log( print_r( $payload, true ) );
		$payload = json_encode( $payload );
		$content = http_build_query( compact( 'payload' ) );

		$context = stream_context_create( array(
			'http' => array(
				'method'  => 'POST',
				'header'  => 'Content-Type: application/x-www-form-urlencoded' . PHP_EOL,
				'content' => $content,
			),
		) );

		return file_get_contents( $this->webhook, false, $context );
	}
}
