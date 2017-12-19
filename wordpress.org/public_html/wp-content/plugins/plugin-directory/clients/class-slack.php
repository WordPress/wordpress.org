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
	private $attachment = [];

	/**
	 * Holds the text property.
	 *
	 * @var array
	 */
	private $text = [];

	/**
	 * Holds Emoji codes for success.
	 *
	 * @var array
	 */
	private $success_emoji = [
		':green_heart:',
		':white_check_mark:',
		':smiley:',
		':ok: ',
	];

	/**
	 * Holds Emoji codes for failure.
	 *
	 * @var array
	 */
	private $failure_emoji = [
		':broken_heart:',
		':umbrella_with_rain_drops:',
		':cry:',
		':sos:',
	];

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
	 * @param mixed  $value Value of the attachment property.
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
	 * Returns a random emoji for a failure message.
	 *
	 * @return string Emoji code.
	 */
	public function get_failure_emoji() {
		$index = array_rand( $this->failure_emoji, 1 );
		return $this->failure_emoji[ $index ];
	}

	/**
	 * Returns a random emoji for a success message.
	 *
	 * @return string Emoji code.
	 */
	public function get_success_emoji() {
		$index = array_rand( $this->success_emoji, 1 );
		return $this->success_emoji[ $index ];
	}

	/**
	 * Publishes a Slack notifcation to a channel.
	 *
	 * @param string $channel The channel to publish the notification to.
	 * @return string|false The read data or false on failure.
	 */
	public function send( $channel ) {
		$text = $this->get_text();
		if ( ! empty( $text ) ) {
			$this->add_attachment( 'text', $text );
		}

		$this->add_attachment( 'mrkdwn_in', [ 'text', 'fields' ] );

		$payload = [
			'icon_emoji'  => ':wordpress:',
			'username'    => 'Plugin Imports',
			'channel'     => $channel,
			'attachments' => $this->get_attachments(),
		];

		$payload = json_encode( $payload );
		$content = http_build_query( compact( 'payload' ) );

		$context = stream_context_create( [
			'http' => [
				'method'  => 'POST',
				'header'  => 'Content-Type: application/x-www-form-urlencoded' . PHP_EOL,
				'content' => $content,
			],
		] );

		$this->flush();

		return file_get_contents( $this->webhook, false, $context );
	}

	/**
	 * Resets internal data variables.
	 */
	public function flush() {
		$this->text       = [];
		$this->attachment = [];
	}
}
