<?php
/**
 * Tests for keeping emails from going to more people than Gmail lets the mailbox send to.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSite
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSite\Tests;

use App\Mailbox;
use Modules\WPOrgSite\Providers\WPOrgSiteServiceProvider;
use WordPressdotorg\FreeScout\Tests\TestCase;

/**
 * Covers the most people an email can go to.
 */
final class LimitRecipientsTest extends TestCase {

	/**
	 * The end of the message the reply form shows when there are too many, after the limit.
	 *
	 * @var string
	 */
	private const WHY = 'Gmail counts each of them towards the mailbox’s daily limit, and a long list uses it up, so the mailbox can’t send anything else. Send announcements from a mailing list instead.';

	/**
	 * Registers the module, and logs in an administrator.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->app->register( WPOrgSiteServiceProvider::class );
		$this->actingAs( $this->create_user() );
	}

	/**
	 * Up to 100 people in To, Cc, Bcc, and the mailbox's Auto Bcc together reach FreeScout; more are refused, if the
	 * mailbox sends through Gmail.
	 *
	 * Its subject is too long for FreeScout, so nothing is sent either way.
	 *
	 * @dataProvider data_recipients
	 *
	 * @param int         $cc         People in Cc, besides one in To and 49 in Bcc.
	 * @param string      $auto_bcc   The mailbox's Auto Bcc.
	 * @param string      $out_server The mailbox's SMTP server.
	 * @param string|null $limit      How the message gives the limit, if it's refused.
	 * @return void
	 */
	public function test_limits_recipients( int $cc, string $auto_bcc, string $out_server, ?string $limit ): void {
		$people  = static fn( string $field, int $count ): array => array_map( static fn( int $i ): string => "$field-$i@example.org", range( 1, $count ) );
		$mailbox = $this->create_mailbox();

		$mailbox->auto_bcc   = $auto_bcc;
		$mailbox->out_method = Mailbox::OUT_METHOD_SMTP;
		$mailbox->out_server = $out_server;
		$mailbox->save();

		$response = $this->post(
			route( 'conversations.ajax' ),
			array(
				'_token'     => csrf_token(),
				'action'     => 'send_reply',
				'mailbox_id' => $mailbox->id,
				'to'         => array( 'sender@example.org' ),
				'subject'    => str_repeat( 'a', 999 ),
				'body'       => 'An announcement.',
				'cc'         => $people( 'cc', $cc ),
				'bcc'        => $people( 'bcc', 49 ),
			)
		);

		if ( $limit ) {
			$response->assertExactJson(
				array(
					'status' => 'error',
					'msg'    => $limit . ' ' . self::WHY,
				)
			);
		} else {
			// This Laravel's json() takes no key.
			$this->assertStringEndsNotWith( self::WHY, (string) ( $response->json()['msg'] ?? '' ) );
		}
	}

	/**
	 * People in Cc, the mailbox's Auto Bcc and SMTP server, and how the message gives the limit, if the email is refused.
	 *
	 * @return array<string, array{int, string, string, string|null}>
	 */
	public static function data_recipients(): array {
		return array(
			'100 people'                 => array( 50, '', 'smtp.gmail.com', null ),
			'101 people'                 => array( 51, 'archive@example.org', 'smtp.gmail.com', 'An email can go to up to 100 people, in To, Cc, and Bcc together.' ),
			'99 people and Auto Bcc'     => array( 49, 'archive@example.org, audit@example.org', 'smtp.gmail.com', 'An email can go to up to 100 people, in To, Cc, and Bcc together, and this mailbox’s Auto Bcc adds 2, which makes 101.' ),
			'101 people, another server' => array( 51, '', 'smtp.example.org', null ),
		);
	}
}
