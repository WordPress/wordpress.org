<?php
/**
 * Tests for what Akismet is told about an email.
 *
 * @package WordPressdotorg\FreeScout\WPOrgAkismet
 */

declare( strict_types = 1 );

namespace Modules\WPOrgAkismet\Tests;

use App\Thread;
use Modules\WPOrgAkismet\Services\Message;
use WordPressdotorg\FreeScout\Tests\TestCase;

/**
 * Covers finding the sender's IP address, and the fields sent.
 */
final class MessageTest extends TestCase {

	/**
	 * The oldest public address of a sending server wins: newer hops are WordPress.org's own servers, and receiving servers don't count.
	 *
	 * @return void
	 */
	public function test_sender_ip_is_the_oldest_public_address(): void {
		$headers = "Received: from mx.wordpress.org (mx.wordpress.org [10.0.0.5])\r\n"
			. "\tby imap.wordpress.org with ESMTP id 1; Wed, 1 Oct 2026 10:00:02 +0000\r\n"
			. "Received: from mail-sor-f41.google.com (mail-sor-f41.google.com. [209.85.220.41])\r\n"
			. "\tby mx.wordpress.org (93.184.216.34) with ESMTPS id 2; Wed, 1 Oct 2026 10:00:01 +0000\r\n"
			. "Received: from localhost (localhost [127.0.0.1])\r\n"
			. "\tby smtp.example.org (93.184.216.34) with ESMTP id 3; Wed, 1 Oct 2026 10:00:00 +0000\r\n"
			. "Subject: Hello\r\n";

		$this->assertSame( '209.85.220.41', Message::sender_ip( $headers ) );
	}

	/**
	 * Servers write the sending address in different ways.
	 *
	 * @return void
	 */
	public function test_sender_ip_formats(): void {
		$this->assertSame( '93.184.216.34', Message::sender_ip( "Received: from [93.184.216.34] (helo=example.org)\r\n\tby mx.wordpress.org\r\n" ) );
		$this->assertSame( '93.184.216.34', Message::sender_ip( "Received: from example.org (93.184.216.34) by mx.wordpress.org\r\n" ) );
		$this->assertSame( '2a00:1450:4864:20::22b', Message::sender_ip( "Received: from example.org (example.org [IPv6:2a00:1450:4864:20::22b]) by mx.wordpress.org\r\n" ) );
	}

	/**
	 * Without a public address, there's nothing to send.
	 *
	 * @return void
	 */
	public function test_no_sender_ip_without_a_public_address(): void {
		$this->assertNull( Message::sender_ip( "Received: from greenmail (greenmail [172.18.0.4]) by greenmail\r\nSubject: Hi\r\n" ) );
		$this->assertNull( Message::sender_ip( '' ) );
	}

	/**
	 * Akismet gets the sender, subject, and text of the email that started the conversation.
	 *
	 * @return void
	 */
	public function test_fields(): void {
		$sender                = $this->create_sender( 'jane@example.org' );
		$conversation          = $this->create_conversation( $this->create_mailbox( 'Themes' ), $sender );
		$conversation->subject = 'My theme';
		$thread                = $this->create_thread( $conversation, Thread::TYPE_CUSTOMER, '<p>Please <b>review</b> it.</p>', null, '2026-10-01 10:00:00' );
		$thread->headers       = "Received: from example.org (example.org [93.184.216.34]) by mx.wordpress.org\r\n";

		$fields = Message::fields( $conversation, $thread );

		$this->assertSame( '93.184.216.34', $fields['user_ip'] );
		$this->assertSame( 'contact-form', $fields['comment_type'] );
		$this->assertSame( 'Jane Sender', $fields['comment_author'] );
		$this->assertSame( 'jane@example.org', $fields['comment_author_email'] );
		$this->assertSame( "My theme\n\nPlease REVIEW it.", $fields['comment_content'] );
		$this->assertSame( '2026-10-01T10:00:00+00:00', $fields['comment_date_gmt'] );
		$this->assertSame( (string) config( 'app.url' ), $fields['blog'] );
	}

	/**
	 * Akismet requires an IP address.
	 *
	 * @return void
	 */
	public function test_no_fields_without_sender_ip(): void {
		$conversation = $this->create_conversation( $this->create_mailbox( 'Themes' ), $this->create_sender() );
		$thread       = $this->create_thread( $conversation, Thread::TYPE_CUSTOMER, 'Hi', null, '2026-10-01 10:00:00' );

		$this->assertNull( Message::fields( $conversation, $thread ) );
	}
}
