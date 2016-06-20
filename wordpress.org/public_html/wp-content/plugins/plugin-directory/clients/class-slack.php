<?php
namespace WordPressdotorg\Plugin_Directory\Clients;

/**
 * Simple Slack client.
 *
 * @package WordPressdotorg\Plugin_Directory\Clients
 */
class Slack {

	/**
	 * Holds the incoming webhook.
	 *
	 * @var string
	 */
	private $webhook = '';

	/**
	 * Holds the attachment property.
	 *
	 * @var array
	 */
	private $attachment = array();

	/**
	 * Holds the text property.
	 *
	 * @var array
	 */
	private $text = array();

	/**
	 * Constructor.
	 *
	 * @param string $webhook Slack webhook.
	 */
	public function __construct( $webhook ) {
		$this->webhook = $webhook;
	}

	/**
	 * Adds a line the the text property.
	 *
	 * @param string $text A line of text.
	 */
	public function add_text( $text ) {
		$this->text[] = $text;
	}

	/**
	 * Returns the current text property for an attachment.
	 *
	 * @return string The current text property.
	 */
	public function get_text() {
		return trim( implode( "\n", $this->text ) );
	}

	/**
	 * Adds an attachment entry.
	 *
	 * @see https://api.slack.com/docs/attachments
	 *
	 * @param string $key   Key of the attachment property.
	 * @param string $value Value of the attachment property.
	 */
	public function add_attachment( $key, $value ) {
		$this->attachment[ $key ] = $value;
	}

	/**
	 * Returns the current attachment.
	 *
	 * @return array The current attachment data
	 */
	public function get_attachments() {
		return array( $this->attachment );
	}

	/**
	 * Adds an attachment for the color property of the notification.
	 *
	 * @param string $status The status/color of the notification.
	 */
	public function set_status( $status ) {
		switch ( $status ) {
			case 'success':
				$this->add_attachment( 'color', '#31843F' );
				break;
			case 'failure':
				$this->add_attachment( 'color', '#9A2323' );
				break;
			case 'warning':
				$this->add_attachment( 'color', '#EE8E0D' );
				break;
		}
	}

	/**
	 * Publishes a Slack notifcation to a channel.
	 *
	 * @param string $channel The channel to publish the notification to.
	 * @return string|false The read data or false on failure.
	 */
	public function send( $channel ) {
		$text = $this->get_text();
		if ( empty( $text ) ) {
			return false;
		}

		$this->add_attachment( 'text', $text );
		$this->add_attachment( 'mrkdwn_in', array( 'text' ) );

		$payload = array(
			'icon_emoji'  => ':wordpress:',
			'username'    => 'Plugin Imports',
			'channel'     => $channel,
			'attachments' => $this->get_attachments(),
		);

		$payload = json_encode( $payload );
		$content = http_build_query( compact( 'payload' ) );

		$context = stream_context_create( array(
			'http' => array(
				'method'  => 'POST',
				'header'  => 'Content-Type: application/x-www-form-urlencoded' . PHP_EOL,
				'content' => $content,
			),
		) );

		$this->flush();

		return file_get_contents( $this->webhook, false, $context );
	}

	/**
	 * Resets internal data variables.
	 */
	public function flush() {
		$this->text       = array();
		$this->attachment = array();
	}
}
