<?php
/**
 * Tests for not sending an email again after Gmail stopped it partway through.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSite
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSite\Tests;

use Modules\WPOrgSite\Providers\WPOrgSiteServiceProvider;
use WordPressdotorg\FreeScout\Tests\TestCase;

/**
 * Covers which replies the reply job retries after Gmail's daily limit.
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
	 * A reply Gmail accepted part of before its daily limit isn't retried; one it refused from the start is.
	 *
	 * Each reply comes after one that Gmail accepted, so the earlier one mustn't count for it.
	 *
	 * @dataProvider data_replies
	 *
	 * @param bool $accepted Whether Gmail accepted one of the reply's emails before its limit.
	 * @param bool $retried  Whether the reply job retries the reply.
	 * @return void
	 */
	public function test_retries_only_replies_gmail_refused_from_the_start( bool $accepted, bool $retried ): void {
		$this->send( true );
		$this->send( $accepted );

		$this->assertSame( $retried, ! preg_match( '/' . config( 'app.no_retry_mail_errors' ) . '/i', self::GMAIL_ERROR ) );
	}

	/**
	 * Replies, and whether they're retried.
	 *
	 * @return array<string, array{bool, bool}>
	 */
	public static function data_replies(): array {
		return array(
			'Accepted in part' => array( true, false ),
			'Refused'          => array( false, true ),
		);
	}

	/**
	 * Runs the filter the reply job runs just before sending, then Swift's events for Gmail's answers.
	 *
	 * @param bool $accepted Whether Gmail accepts the first email, before refusing the next with its daily limit.
	 * @return void
	 */
	private function send( bool $accepted ): void {
		\Eventy::filter( 'email.reply_to_customer.threads', collect() );

		// Gmail accepts the sender and recipient even when it then refuses the email.
		$this->exchange( "MAIL FROM:<support@example.org>\r\n", array( 250 ), '250 2.1.0 OK gsmtp', true );
		$this->exchange( "RCPT TO:<person@example.org>\r\n", array( 250, 251, 252 ), '250 2.1.5 OK gsmtp', true );

		if ( $accepted ) {
			$this->exchange( "DATA\r\n", array( 354 ), '354 Go ahead gsmtp', true );
			$this->exchange( "\r\n.\r\n", array( 250 ), '250 2.0.0 OK 1793574420 gsmtp', true );
		}

		$this->exchange( "DATA\r\n", array( 354 ), '550-5.4.5 Daily user sending limit exceeded.', false );
	}

	/**
	 * Dispatches the events Swift dispatches for a command and its response.
	 *
	 * @param string $command  SMTP command.
	 * @param int[]  $codes    Response codes the command expects.
	 * @param string $response Server's response.
	 * @param bool   $valid    Whether the response has one of the expected codes.
	 * @return void
	 */
	private function exchange( string $command, array $codes, string $response, bool $valid ): void {
		$this->events->dispatchEvent( $this->events->createCommandEvent( $this->transport, $command, $codes ), 'commandSent' );
		$this->events->dispatchEvent( $this->events->createResponseEvent( $this->transport, $response, $valid ), 'responseReceived' );
	}
}
