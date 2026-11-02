<?php
/**
 * Tests for not sending an email again after Gmail stopped it partway through.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSite
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSite\Tests;

use App\Thread;
use Modules\WPOrgSite\Providers\WPOrgSiteServiceProvider;
use WordPressdotorg\FreeScout\Tests\TestCase;

/**
 * Covers which replies the reply job retries after Gmail's daily limit, and the note on who didn't get them.
 */
final class PartialSendsTest extends TestCase {

	/**
	 * Gmail's error, as the reply job got it for #1300695.
	 *
	 * @var string
	 */
	private const GMAIL_ERROR = 'Expected response code 354 but got code "550", with message "550-5.4.5 Daily user sending limit exceeded. For more information on Gmail 550-5.4.5 sending limits go to 550 5.4.5 https://support.google.com/a/answer/166852 - gsmtp "';

	/**
	 * Swift's SMTP transport, which the mailer sends with.
	 *
	 * @var \Swift_Transport_EsmtpTransport
	 */
	private \Swift_Transport_EsmtpTransport $transport;

	/**
	 * The transport's event dispatcher, which the test plays Gmail's side of the conversation through.
	 *
	 * @var \Swift_Events_SimpleEventDispatcher
	 */
	private \Swift_Events_SimpleEventDispatcher $events;

	/**
	 * Registers the module, and sends mail over Swift's SMTP transport, without connecting it.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->app->register( WPOrgSiteServiceProvider::class );

		$this->events    = new \Swift_Events_SimpleEventDispatcher();
		$this->transport = new \Swift_Transport_EsmtpTransport( new \Swift_Transport_StreamBuffer( new \Swift_StreamFilters_StringReplacementFilterFactory() ), array(), $this->events );
		\Mail::setSwiftMailer( new \Swift_Mailer( $this->transport ) );
	}

	/**
	 * A reply Gmail accepted part of before its daily limit isn't retried, and notes who didn't get it; one it refused
	 * from the start is retried.
	 *
	 * Each follows a reply Gmail accepted, which mustn't count for it.
	 *
	 * @dataProvider data_replies
	 *
	 * @param bool        $accepted Whether Gmail accepted the reply's first email, to To, before its limit.
	 * @param bool        $retried  Whether the reply job retries the reply.
	 * @param string|null $missed   Who the note says didn't get it, if there's one.
	 * @return void
	 */
	public function test_handles_replies_gmail_stopped( bool $accepted, bool $retried, ?string $missed ): void {
		$this->send( $this->create_reply(), true, false );
		$reply = $this->create_reply();
		$this->send( $reply, $accepted, true );

		$this->assertSame( $retried, ! preg_match( '/' . config( 'app.no_retry_mail_errors' ) . '/i', self::GMAIL_ERROR ) );

		$notes = Thread::where( 'conversation_id', $reply->conversation_id )->where( 'type', Thread::TYPE_NOTE )->pluck( 'body' )->all();
		if ( $missed ) {
			$this->assertCount( 1, $notes );
			$this->assertStringContainsString( "<p>$missed</p>", $notes[0] );
		} else {
			$this->assertSame( array(), $notes );
		}
	}

	/**
	 * Another email the mailer sends after a reply, like a notification, isn't taken for it when Gmail stops it.
	 *
	 * @return void
	 */
	public function test_ignores_other_emails(): void {
		$reply = $this->create_reply();
		$this->send( $reply, true, false );
		$this->send( null, true, true );

		$this->assertSame( 0, Thread::where( 'conversation_id', $reply->conversation_id )->where( 'type', Thread::TYPE_NOTE )->count() );
	}

	/**
	 * Replies, whether they're retried, and who the note says didn't get them.
	 *
	 * @return array<string, array{bool, bool, string|null}>
	 */
	public static function data_replies(): array {
		return array(
			'Accepted in part' => array( true, false, 'first@example.org, second@example.org' ),
			'Refused'          => array( false, true, null ),
		);
	}

	/**
	 * Creates an agent's reply.
	 *
	 * @return Thread
	 */
	private function create_reply(): Thread {
		$conversation = $this->create_conversation( $this->create_mailbox(), $this->create_sender( 'sender@example.org' ) );

		return $this->create_thread( $conversation, Thread::TYPE_MESSAGE, 'An announcement.', $this->create_user(), now()->toDateTimeString() );
	}

	/**
	 * Sends an email to the sender, with two people in Bcc, as the reply job and Swift do, and plays Gmail's answers.
	 *
	 * Swift sends the sender and recipients before it reads Gmail's answers, so they come in after all of them.
	 *
	 * @param Thread|null $reply    The reply, or null for an email that isn't one.
	 * @param bool        $accepted Whether Gmail accepts the email to To.
	 * @param bool        $limited  Whether Gmail then refuses the first Bcc email with its daily limit; otherwise, it's sent.
	 * @return void
	 */
	private function send( ?Thread $reply, bool $accepted, bool $limited ): void {
		if ( $reply ) {
			\Eventy::filter( 'email.reply_to_customer.threads', collect( array( $reply ) ), $reply->conversation, $reply->conversation->mailbox );
		}

		$message = ( new \Swift_Message( 'An announcement' ) )->setTo( 'sender@example.org' )->setBcc( array( 'first@example.org', 'second@example.org' ) );
		$this->events->dispatchEvent( $this->events->createSendEvent( $this->transport, $message ), 'beforeSendPerformed' );

		// Gmail's 250s to the sender and recipients don't mean it accepted the email.
		$this->exchange( array( 'MAIL FROM:<support@example.org>', 'RCPT TO:<sender@example.org>', 'DATA' ), array( '250 2.1.0 OK', '250 2.1.5 OK', $accepted ? '354 Go ahead' : '550-5.4.5 Daily user sending limit exceeded.' ) );
		if ( ! $accepted ) {
			return;
		}

		$this->exchange( array( "\r\n.\r\n" ), array( '250 2.0.0 OK' ) );

		if ( $limited ) {
			$this->exchange( array( 'MAIL FROM:<support@example.org>', 'RCPT TO:<first@example.org>', 'DATA' ), array( '250 2.1.0 OK', '250 2.1.5 OK', '550-5.4.5 Daily user sending limit exceeded.' ) );
		} else {
			$this->events->dispatchEvent( $this->events->createSendEvent( $this->transport, $message ), 'sendPerformed' );
		}
	}

	/**
	 * Dispatches Swift's events for commands, then for Gmail's answers to them, a 2xx or 3xx one being expected.
	 *
	 * @param string[] $commands  SMTP commands, without the line break, except the end of an email's data.
	 * @param string[] $responses Gmail's answers, in the same order.
	 * @return void
	 */
	private function exchange( array $commands, array $responses ): void {
		foreach ( $commands as $command ) {
			$command = "\r\n.\r\n" === $command ? $command : "$command\r\n";
			$this->events->dispatchEvent( $this->events->createCommandEvent( $this->transport, $command, array() ), 'commandSent' );
		}

		foreach ( $responses as $response ) {
			$this->events->dispatchEvent( $this->events->createResponseEvent( $this->transport, $response, ! preg_match( '/^[45]/', $response ) ), 'responseReceived' );
		}
	}
}
