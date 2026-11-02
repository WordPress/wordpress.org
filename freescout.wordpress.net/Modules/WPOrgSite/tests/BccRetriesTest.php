<?php
/**
 * Tests for not sending an email with Bcc again after Gmail stopped it partway through.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSite
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSite\Tests;

use App\Thread;
use Modules\WPOrgSite\Providers\WPOrgSiteServiceProvider;
use WordPressdotorg\FreeScout\Tests\TestCase;

/**
 * Covers which emails the reply job retries after Gmail's daily limit.
 */
final class BccRetriesTest extends TestCase {

	/**
	 * Gmail's error, as the reply job got it for #1300695.
	 *
	 * @var string
	 */
	private const GMAIL_ERROR = 'Expected response code 354 but got code "550", with message "550-5.4.5 Daily user sending limit exceeded. For more information on Gmail 550-5.4.5 sending limits go to 550 5.4.5 https://support.google.com/a/answer/166852 - gsmtp "';

	/**
	 * Registers the module.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->app->register( WPOrgSiteServiceProvider::class );
	}

	/**
	 * An email with Bcc isn't retried after the daily limit; one without is, even right after one with.
	 *
	 * Runs the filter the reply job runs just before sending, then matches the error as the job does.
	 *
	 * @dataProvider data_emails
	 *
	 * @param string $bcc      The email's Bcc.
	 * @param string $auto_bcc The mailbox's Auto Bcc.
	 * @param bool   $retried  Whether the reply job retries it.
	 * @return void
	 */
	public function test_retries_only_emails_without_bcc( string $bcc, string $auto_bcc, bool $retried ): void {
		$mailbox = $this->create_mailbox();
		$sender  = $this->create_sender();

		$this->send( $this->create_reply( $this->create_conversation( $mailbox, $sender ), 'earlier@example.org' ) );

		$mailbox->auto_bcc = $auto_bcc;
		$mailbox->save();

		$this->send( $this->create_reply( $this->create_conversation( $mailbox, $sender ), $bcc ) );

		$this->assertSame( $retried, ! preg_match( '/' . config( 'app.no_retry_mail_errors' ) . '/i', self::GMAIL_ERROR ) );
	}

	/**
	 * Emails, and whether they're retried.
	 *
	 * @return array<string, array{string, string, bool}>
	 */
	public static function data_emails(): array {
		return array(
			'Bcc'      => array( 'person@example.org', '', false ),
			'Auto Bcc' => array( '', 'archive@example.org', false ),
			'No Bcc'   => array( '', '', true ),
		);
	}

	/**
	 * Adds an agent's reply to a conversation.
	 *
	 * @param \App\Conversation $conversation Conversation.
	 * @param string            $bcc          The reply's Bcc.
	 * @return Thread
	 */
	private function create_reply( \App\Conversation $conversation, string $bcc ): Thread {
		$reply = $this->create_thread( $conversation, Thread::TYPE_MESSAGE, 'An announcement.', $this->create_user(), now()->toDateTimeString() );
		$reply->setBcc( $bcc );
		$reply->save();

		return $reply;
	}

	/**
	 * Runs the filter the reply job runs just before sending a reply.
	 *
	 * @param Thread $reply The reply.
	 * @return void
	 */
	private function send( Thread $reply ): void {
		\Eventy::filter( 'email.reply_to_customer.threads', collect( array( $reply ) ), $reply->conversation, $reply->conversation->mailbox );
	}
}
