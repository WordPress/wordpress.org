<?php
/**
 * Tests for whose WordPress.org account the sidebar shows.
 *
 * Bounces and Slack notifications are about someone else, and the sender writes everything that names them: that
 * account may only be shown once an agent asks for it, and never if it's someone at WordPress.org.
 *
 * @package WordPressdotorg\API\FreeScout
 */

declare( strict_types = 1 );

namespace WordPressdotorg\API\FreeScout;

use PHPUnit\Framework\TestCase;

/**
 * Tests for get_user_email_for_email() and get_related_user().
 */
class Related_User_Test extends TestCase {

	/**
	 * Sets up the fixture users.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['freescout_test_users'] = array(
			new \WP_User( 1, 'sender@example.org', 'sender' ),
			new \WP_User( 2, 'victim@example.com', 'victim' ),
			new \WP_User( 3, 'staff@wordpress.org', 'staff' ),
		);
	}

	/**
	 * Clears the fixture users.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		unset( $GLOBALS['freescout_test_users'] );

		parent::tearDown();
	}

	/**
	 * Conversations that name someone else show the sender until the agent asks, and that someone after.
	 *
	 * @dataProvider data_someone_else
	 *
	 * @param string $from    Sender's address.
	 * @param string $subject Subject.
	 * @param string $body    Body of the first message.
	 * @return void
	 */
	public function test_shows_someone_else_only_on_request( string $from, string $subject, string $body ): void {
		$request = self::request( $from, $subject, $body );

		$this->assertSame( $from, get_user_email_for_email( $request ) );
		$this->assertSame( 'victim@example.com', get_related_user( $request )->user_email );

		$request->related = true;
		$this->assertSame( 'victim@example.com', get_user_email_for_email( $request ) );
	}

	/**
	 * Supplies conversations about someone else.
	 *
	 * @return array
	 */
	public function data_someone_else(): array {
		return array(
			'slack notification'   => array( 'notification@slack.example', 'victim@chat.wordpress.org deactivated', '' ),
			'bounce'               => array( 'mailer-daemon@mx.example', 'Undeliverable', 'To: victim@example.com' ),
			'bounce, plus address' => array( 'mailer-daemon@mx.example', 'Undeliverable', 'To: victim+wporg@example.com' ),
		);
	}

	/**
	 * Conversations that name nobody else show the sender, whatever is asked.
	 *
	 * @dataProvider data_sender
	 *
	 * @param string $from     Sender's address.
	 * @param string $subject  Subject.
	 * @param string $body     Body of the first message.
	 * @param string $expected Address of the account shown.
	 * @return void
	 */
	public function test_shows_the_sender( string $from, string $subject, string $body, string $expected ): void {
		$request = self::request( $from, $subject, $body );

		$this->assertFalse( get_related_user( $request ) );

		$request->related = true;
		$this->assertSame( $expected, get_user_email_for_email( $request ) );
	}

	/**
	 * Supplies conversations about the sender.
	 *
	 * @return array
	 */
	public function data_sender(): array {
		return array(
			'ordinary email'             => array( 'sender@example.org', 'Hello', 'To: victim@example.com', 'sender@example.org' ),
			'bounce from an account'     => array( 'sender@example.org', 'Undeliverable', 'To: victim@example.com', 'sender@example.org' ),
			'old plugins email subject'  => array( 'stranger@example.net', 'Are your plugins ready, victim?', '', 'stranger@example.net' ),
			'bounce naming staff'        => array( 'mailer-daemon@mx.example', 'Undeliverable', 'Original-Recipient: rfc822; staff@wordpress.org', 'mailer-daemon@mx.example' ),
			'slack notification, staff'  => array( 'notification@slack.example', 'staff@chat.wordpress.org deactivated', '', 'notification@slack.example' ),
			'slack notification, sender' => array( 'sender@example.org', 'sender@chat.wordpress.org', '', 'sender@example.org' ),
		);
	}

	/**
	 * A bounce of WordPress.org's own email still finds who it was for.
	 *
	 * @return void
	 */
	public function test_bounce_to_a_wordpress_org_sender_names_the_recipient(): void {
		$request = self::request( 'staff@wordpress.org', 'Undeliverable', 'To: victim@example.com' );

		$this->assertSame( 'victim@example.com', get_related_user( $request )->user_email );
	}

	/**
	 * Builds a request payload like WPOrgSidebar's.
	 *
	 * @param string $from    Sender's address.
	 * @param string $subject Subject.
	 * @param string $body    Body of the first message.
	 * @return object
	 */
	private static function request( string $from, string $subject, string $body ): object {
		return (object) array(
			'conversation' => (object) array( 'subject' => $subject ),
			'sender'       => (object) array(
				'email'  => $from,
				'emails' => array( $from ),
			),
			'threads'      => array(
				(object) array(
					'type'        => 'customer',
					'body'        => $body,
					'attachments' => array(),
				),
			),
		);
	}
}
