<?php
/**
 * Tests for keeping emails from going to more people than Gmail lets the mailbox send to.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSite
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSite\Tests;

use Modules\WPOrgSite\Providers\WPOrgSiteServiceProvider;
use WordPressdotorg\FreeScout\Tests\TestCase;

/**
 * Covers the most people an email can go to.
 */
final class LimitRecipientsTest extends TestCase {

	/**
	 * The message the reply form shows when there are too many.
	 *
	 * @var string
	 */
	private const MESSAGE = 'An email can go to up to 100 people, in To, Cc, and Bcc together. Gmail counts each of them towards the mailbox’s daily limit, and a long list uses it up, so the mailbox can’t send anything else. Send announcements from a mailing list instead.';

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
	 * Up to 100 people in To, Cc, Bcc, and the mailbox's Auto Bcc together reach FreeScout; more are refused.
	 *
	 * Its subject is too long for FreeScout, so nothing is sent either way.
	 *
	 * @dataProvider data_recipients
	 *
	 * @param int    $cc       People in Cc, besides one in To and 49 in Bcc.
	 * @param string $auto_bcc The mailbox's Auto Bcc.
	 * @param bool   $refused  Whether it's refused for them.
	 * @return void
	 */
	public function test_limits_recipients( int $cc, string $auto_bcc, bool $refused ): void {
		$people  = static fn( string $field, int $count ): array => array_map( static fn( int $i ): string => "$field-$i@example.org", range( 1, $count ) );
		$mailbox = $this->create_mailbox();

		$mailbox->auto_bcc = $auto_bcc;
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

		if ( $refused ) {
			$response->assertExactJson(
				array(
					'status' => 'error',
					'msg'    => self::MESSAGE,
				)
			);
		} else {
			// This Laravel's json() takes no key.
			$this->assertNotSame( self::MESSAGE, $response->json()['msg'] ?? null );
		}
	}

	/**
	 * People in Cc, the mailbox's Auto Bcc, and whether the email is refused.
	 *
	 * @return array<string, array{int, string, bool}>
	 */
	public static function data_recipients(): array {
		return array(
			'100 people'              => array( 50, '', false ),
			'101 people'              => array( 51, '', true ),
			'99 people and Auto Bcc' => array( 49, 'archive@example.org, audit@example.org', true ),
		);
	}
}
