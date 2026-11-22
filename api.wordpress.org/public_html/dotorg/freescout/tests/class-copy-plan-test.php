<?php
/**
 * Tests for planning writes to WordPress.org's copy of conversations.
 *
 * @package WordPressdotorg\API\FreeScout
 */

declare( strict_types = 1 );

namespace WordPressdotorg\API\FreeScout;

use PHPUnit\Framework\TestCase;

/**
 * Tests for get_replaced_ids(), needs_all_threads(), plan_copy_replacement(), and get_copy_mailbox_slug().
 */
class Copy_Plan_Test extends TestCase {

	/**
	 * FreeScout conversation the events are about.
	 *
	 * @var int
	 */
	private const ID = 42;

	/**
	 * HelpScout conversation it was imported from; made up.
	 *
	 * @var int
	 */
	private const HELPSCOUT_ID = 1000000001;

	/**
	 * HelpScout conversation a conversation merged into it was imported from; made up.
	 *
	 * @var int
	 */
	private const MERGED_HELPSCOUT_ID = 1000000002;

	/**
	 * FreeScout conversation merged into it.
	 *
	 * @var int
	 */
	private const MERGED_ID = 43;

	/**
	 * A merge names the conversation merged away, and the HelpScout conversations both were imported from.
	 *
	 * @return void
	 */
	public function test_names_the_copies_an_event_replaces(): void {
		$this->assertSame(
			array(
				'freescout' => array( self::MERGED_ID ),
				'helpscout' => array( self::HELPSCOUT_ID, self::MERGED_HELPSCOUT_ID ),
			),
			get_replaced_ids( $this->request() )
		);

		$this->assertSame(
			array(
				'freescout' => array(),
				'helpscout' => array(),
			),
			get_replaced_ids( (object) array( 'event' => 'conversation.user_replied' ) )
		);
	}

	/**
	 * Only a conversation the copy has nothing of yet needs all of its threads, unless the event carries them.
	 *
	 * @return void
	 */
	public function test_asks_for_all_threads_only_for_a_new_copy(): void {
		$request = (object) array( 'email' => (object) array( 'all_threads' => false ) );

		$this->assertTrue( needs_all_threads( $request, null, false ) );
		$this->assertFalse( needs_all_threads( $request, (object) array( 'id' => self::ID ), false ) );
		$this->assertFalse( needs_all_threads( $request, null, true ) );

		$request->email->all_threads = true;
		$this->assertFalse( needs_all_threads( $request, null, false ) );
	}

	/**
	 * Pointing the copy at an imported conversation renames HelpScout's copy, or merges it into FreeScout's; one
	 * deleted, or spam, goes; one already pointed at is left alone.
	 *
	 * @return void
	 */
	public function test_plans_replacing_a_copy(): void {
		$copy = (object) array(
			'helpscout_id' => self::HELPSCOUT_ID,
			'id'           => self::ID,
			'number'       => 1234,
			'delete'       => false,
		);

		$this->assertSame( 'rename', plan_copy_replacement( $copy, true, false ) );
		$this->assertSame( 'merge', plan_copy_replacement( $copy, true, true ) );
		$this->assertSame( 'skip', plan_copy_replacement( $copy, false, true ) );
		$this->assertSame( 'skip', plan_copy_replacement( $copy, false, false ) );

		$copy->delete = true;
		$this->assertSame( 'delete', plan_copy_replacement( $copy, true, false ) );
		$this->assertSame( 'delete', plan_copy_replacement( $copy, false, true ) );
		$this->assertSame( 'skip', plan_copy_replacement( $copy, false, false ) );

		$copy->id = self::HELPSCOUT_ID;
		$this->assertSame( 'skip', plan_copy_replacement( $copy, true, true ) );
	}

	/**
	 * The copy keeps a conversation in the mailbox it's in now; events from before it moved still name theirs, which
	 * the stats count.
	 *
	 * @return void
	 */
	public function test_copy_takes_the_current_mailbox(): void {
		$request = json_decode(
			(string) wp_json_encode(
				array(
					'mailbox' => array( 'name' => 'Plugins' ),
					'email'   => array( 'mailbox' => array( 'name' => 'Themes' ) ),
				)
			)
		);

		$this->assertSame( 'themes', get_copy_mailbox_slug( $request ) );
		$this->assertSame( 'plugins', get_mailbox_slug( $request ) );

		unset( $request->email->mailbox );
		$this->assertSame( 'plugins', get_copy_mailbox_slug( $request ) );
	}

	/**
	 * A merge event of a conversation imported from HelpScout.
	 *
	 * @return object
	 */
	private function request(): object {
		return (object) array(
			'event'               => 'conversation.merged',
			'conversation'        => (object) array( 'id' => self::ID ),
			'merged_id'           => self::MERGED_ID,
			'helpscout_id'        => self::HELPSCOUT_ID,
			'merged_helpscout_id' => self::MERGED_HELPSCOUT_ID,
		);
	}
}
