<?php
/**
 * Tests for how WordPress.org's copy of conversations is kept: what an event writes and deletes, and what pointing the
 * copy at imported conversations does.
 *
 * @package WordPressdotorg\API\FreeScout
 */

declare( strict_types = 1 );

namespace WordPressdotorg\API\FreeScout;

use PHPUnit\Framework\TestCase;

/**
 * Tests for get_replaced_ids(), needs_all_threads(), plan_email_write(), plan_copy_replacement(), and
 * get_copy_mailbox_slug().
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
	 * Writing takes over the replaced copies' plugins and themes, names its helpdesk and the HelpScout conversations it
	 * replaces, adds what it mentions, and deletes the replaced copies; nothing it has already is added again.
	 *
	 * @return void
	 */
	public function test_takes_over_the_copies_it_replaces(): void {
		$plan = plan_email_write(
			self::ID,
			get_replaced_ids( $this->request() ),
			array(
				$this->meta( self::ID, 'plugins', 'my-plugin' ),
				$this->meta( self::MERGED_ID, 'plugins', 'merged-plugin' ),
				$this->meta( self::MERGED_ID, HELPDESK_META_KEY, 'freescout' ),
				$this->meta( self::HELPSCOUT_ID, 'themes', 'old-theme' ),
				$this->meta( self::HELPSCOUT_ID, 'plugins', 'my-plugin' ),
			),
			array( 'plugins' => array( 'my-plugin', 'new-plugin' ) )
		);

		$this->assertEqualsCanonicalizing(
			array(
				array( 'plugins', 'merged-plugin' ),
				array( 'themes', 'old-theme' ),
				array( HELPDESK_META_KEY, 'freescout' ),
				array( REPLACED_HELPSCOUT_META_KEY, (string) self::HELPSCOUT_ID ),
				array( REPLACED_HELPSCOUT_META_KEY, (string) self::MERGED_HELPSCOUT_ID ),
				array( 'plugins', 'new-plugin' ),
			),
			$plan['meta']
		);
		$this->assertSame( array( self::MERGED_ID, self::HELPSCOUT_ID, self::MERGED_HELPSCOUT_ID ), $plan['delete'] );
	}

	/**
	 * Once the copy names a HelpScout conversation, later events, and the same event sent again, leave it alone: nothing
	 * is deleted again, and nothing more is added.
	 *
	 * @return void
	 */
	public function test_leaves_helpscout_conversations_it_replaced_alone(): void {
		$request = (object) array( 'helpscout_id' => self::HELPSCOUT_ID );

		$plan = plan_email_write(
			self::ID,
			get_replaced_ids( $request ),
			array(
				$this->meta( self::ID, HELPDESK_META_KEY, 'freescout' ),
				$this->meta( self::ID, REPLACED_HELPSCOUT_META_KEY, (string) self::HELPSCOUT_ID ),
			),
			array()
		);

		$this->assertSame(
			array(
				'meta'   => array(),
				'delete' => array(),
			),
			$plan
		);
	}

	/**
	 * A copy that names HelpScout conversations keeps naming them as its meta is written again.
	 *
	 * @return void
	 */
	public function test_keeps_naming_what_it_replaced(): void {
		$plan = plan_email_write(
			self::ID,
			get_replaced_ids( (object) array() ),
			array( $this->meta( self::ID, REPLACED_HELPSCOUT_META_KEY, (string) self::HELPSCOUT_ID ) ),
			array( 'plugins' => array( 'my-plugin' ) )
		);

		$this->assertSame( array( array( HELPDESK_META_KEY, 'freescout' ), array( 'plugins', 'my-plugin' ) ), $plan['meta'] );
		$this->assertSame( array(), $plan['delete'] );
	}

	/**
	 * A conversation merged into another hands it the HelpScout conversation it named.
	 *
	 * @return void
	 */
	public function test_merging_hands_over_what_was_replaced(): void {
		$plan = plan_email_write(
			self::ID,
			get_replaced_ids( (object) array( 'merged_id' => self::MERGED_ID ) ),
			array( $this->meta( self::MERGED_ID, REPLACED_HELPSCOUT_META_KEY, (string) self::MERGED_HELPSCOUT_ID ) ),
			array()
		);

		$this->assertContains( array( REPLACED_HELPSCOUT_META_KEY, (string) self::MERGED_HELPSCOUT_ID ), $plan['meta'] );
		$this->assertSame( array( self::MERGED_ID ), $plan['delete'] );
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

		$this->assertSame( 'rename', plan_copy_replacement( $copy, true, false, false ) );
		$this->assertSame( 'merge', plan_copy_replacement( $copy, true, true, false ) );
		$this->assertSame( 'name', plan_copy_replacement( $copy, false, true, false ) );
		$this->assertSame( 'skip', plan_copy_replacement( $copy, false, true, true ) );
		$this->assertSame( 'skip', plan_copy_replacement( $copy, false, false, false ) );

		$copy->delete = true;
		$this->assertSame( 'delete', plan_copy_replacement( $copy, true, false, false ) );
		$this->assertSame( 'delete', plan_copy_replacement( $copy, false, true, true ) );
		$this->assertSame( 'skip', plan_copy_replacement( $copy, false, false, false ) );

		$copy->id = self::HELPSCOUT_ID;
		$this->assertSame( 'skip', plan_copy_replacement( $copy, true, true, false ) );
	}

	/**
	 * A conversation merged into another before the switch has its HelpScout copy merged into that one's, whether that
	 * one's copy is there yet or not, so it keeps what the copy mentions; one the copy names already is left alone.
	 *
	 * @return void
	 */
	public function test_plans_merging_a_conversation_merged_away(): void {
		$copy = (object) array(
			'helpscout_id' => self::MERGED_HELPSCOUT_ID,
			'id'           => self::ID,
			'number'       => 1234,
			'delete'       => false,
			'merged'       => true,
		);

		$this->assertSame( 'merge', plan_copy_replacement( $copy, true, false, false ) );
		$this->assertSame( 'merge', plan_copy_replacement( $copy, true, true, false ) );
		$this->assertSame( 'skip', plan_copy_replacement( $copy, false, true, true ) );

		$copy->delete = true;
		$this->assertSame( 'delete', plan_copy_replacement( $copy, true, true, false ) );
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

	/**
	 * A row of the copy's meta.
	 *
	 * @param int    $id    Conversation ID.
	 * @param string $key   Meta key.
	 * @param string $value Meta value.
	 * @return array
	 */
	private function meta( int $id, string $key, string $value ): array {
		return array(
			'helpscout_id' => (string) $id,
			'meta_key'     => $key, // phpcs:ignore WordPress.DB.SlowDBQuery -- Not a query.
			'meta_value'   => $value, // phpcs:ignore WordPress.DB.SlowDBQuery -- Not a query.
		);
	}
}
