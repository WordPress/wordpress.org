<?php
/**
 * Tests for WordPress.org's copy of FreeScout's conversations.
 *
 * The plugin directory reads the copy, which began with HelpScout's emails, so FreeScout's conversations have to fill
 * its columns the way HelpScout's did.
 *
 * @package WordPressdotorg\API\FreeScout
 */

declare( strict_types = 1 );

namespace WordPressdotorg\API\FreeScout;

use PHPUnit\Framework\TestCase;

/**
 * Tests for get_email_row().
 */
class Email_Row_Test extends TestCase {

	/**
	 * A conversation fills the row as HelpScout's emails did.
	 *
	 * @return void
	 */
	public function test_fills_the_row_as_helpscout_did(): void {
		$this->assertSame(
			array(
				'id'       => 42,
				'number'   => 1234,
				'user_id'  => 7,
				'mailbox'  => 'plugins',
				'status'   => 'closed',
				'email'    => 'Jane Author <jane@example.org>',
				'subject'  => '[WordPress Plugin Directory] Review in Progress: My Plugin',
				'preview'  => 'Thanks for your submission',
				'created'  => '2026-08-01 10:00:00',
				'closed'   => '2026-08-03 12:00:00',
				'modified' => '2026-08-03 12:00:00',
			),
			get_email_row( $this->request(), null, 7 )
		);
	}

	/**
	 * What the event doesn't say is kept from the row, and a conversation that was never closed has no closing date.
	 *
	 * @return void
	 */
	public function test_keeps_what_the_event_does_not_say(): void {
		$request = $this->request();
		unset( $request->email->sender, $request->email->created_at, $request->email->subject );
		$request->email->closed_at       = null;
		$request->email->user_updated_at = '2026-08-02T08:00:00Z';

		$row = get_email_row(
			$request,
			(object) array(
				'email'   => 'Old <old@example.org>',
				'subject' => 'Old subject',
				'created' => '2026-07-01 00:00:00',
			),
			0
		);

		$this->assertSame( array( 'Old <old@example.org>', 'Old subject' ), array( $row['email'], $row['subject'] ) );
		$this->assertSame( array( '2026-07-01 00:00:00', '', '2026-08-02 08:00:00' ), array( $row['created'], $row['closed'], $row['modified'] ) );
	}

	/**
	 * A sender without a name is their address.
	 *
	 * @return void
	 */
	public function test_sender_without_a_name_is_their_address(): void {
		$request                            = $this->request();
		$request->email->sender->first_name = '';
		$request->email->sender->last_name  = '';

		$this->assertSame( 'jane@example.org', get_email_row( $request, null, 0 )['email'] );
	}

	/**
	 * The row is in the mailbox the conversation is in now, rather than the one a late event happened in.
	 *
	 * @return void
	 */
	public function test_row_is_in_the_current_mailbox(): void {
		$request                 = $this->request();
		$request->email->mailbox = (object) array(
			'id'   => 4,
			'name' => 'Themes',
		);

		$this->assertSame( 'themes', get_email_row( $request, null, 0 )['mailbox'] );
	}

	/**
	 * A webhook payload, as WPOrgWebhooks' EventPayload builds it.
	 *
	 * @return object
	 */
	private function request(): object {
		return json_decode(
			(string) wp_json_encode(
				array(
					'event'        => 'conversation.user_replied',
					'conversation' => array( 'id' => 42 ),
					'mailbox'      => array(
						'id'   => 3,
						'name' => 'Plugins',
					),
					'email'        => array(
						'number'          => 1234,
						'subject'         => '[WordPress Plugin Directory] Review in Progress: My Plugin',
						'status'          => 'closed',
						'preview'         => 'Thanks for your submission',
						'created_at'      => '2026-08-01T10:00:00Z',
						'closed_at'       => '2026-08-03T12:00:00Z',
						'user_updated_at' => '2026-08-02T08:00:00Z',
						'sender'          => array(
							'email'      => 'jane@example.org',
							'emails'     => array( 'jane@example.org' ),
							'first_name' => 'Jane',
							'last_name'  => 'Author',
						),
						'threads'         => array(),
					),
				)
			)
		);
	}
}
