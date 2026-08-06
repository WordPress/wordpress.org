<?php
/**
 * Tests for the reviewer release block: a reviewer holding a version out of `update_source`
 * from the Controls metabox, and force-releasing it again.
 *
 * Covers both the API — API_Update_Updater::block_release() — and the metabox save handler
 * that reviewers actually reach it through, including its refusals.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

use WordPressdotorg\Plugin_Directory\Admin\Metabox\Controls;
use WordPressdotorg\Plugin_Directory\Jobs\API_Update_Updater;

/**
 * Covers API_Update_Updater::block_release() and its interaction with the release delay,
 * the plugin's status, and the reviewer force-release.
 *
 * @group jobs
 */
class Reviewer_Release_Block_Test extends Release_Block_Test_Case {

	/**
	 * The plugin slug under test.
	 */
	const SLUG = 'reviewer-block-test';

	/**
	 * The reviewer performing the block.
	 *
	 * @var \WP_User
	 */
	protected $reviewer;

	/**
	 * The release fixture, plus a reviewer who can act on it.
	 */
	protected function setUp(): void {
		parent::setUp();

		$reviewer_id = wp_insert_user(
			array(
				'user_login' => 'reviewer-block-user',
				'user_pass'  => 'password',
				'user_email' => 'reviewer-block-user@example.org',
			)
		);
		$this->assertNotInstanceOf( WP_Error::class, $reviewer_id );

		$this->reviewer = get_user_by( 'id', $reviewer_id );
		$this->reviewer->add_cap( 'plugin_review' );
	}

	/**
	 * Remove the reviewer, and the request state the metabox tests leave behind.
	 */
	protected function tearDown(): void {
		unset(
			$_POST['force_release_version'],
			$_POST['block_release_version'],
			$_POST['release_action_reason'],
			$_REQUEST['_wpnonce']
		);

		wp_set_current_user( 0 );
		wp_delete_user( $this->reviewer->ID );

		parent::tearDown();
	}

	/**
	 * The block payload the Controls metabox sends for a reviewer block.
	 *
	 * @return array
	 */
	protected function reviewer_block() {
		return array(
			'reason'     => 'Suspicious obfuscated code.',
			'blocked_by' => $this->reviewer->user_login,
		);
	}

	/**
	 * Submit the Controls metabox as the reviewer would, with a valid nonce.
	 *
	 * @param array $fields The release-action fields to post.
	 */
	protected function submit_controls( $fields ) {
		wp_set_current_user( $this->reviewer->ID );

		foreach ( $fields as $name => $value ) {
			$_POST[ $name ] = $value;
		}

		$_REQUEST['_wpnonce'] = wp_create_nonce( 'update-post_' . $this->plugin->ID );

		Controls::save_post( $this->plugin->ID );
	}

	/**
	 * A reviewer block holds the in-cooldown version: the previous one keeps being served, the
	 * block is recorded with the reason and reviewer, and any deferred serve is cancelled.
	 */
	public function test_a_block_holds_the_in_cooldown_version() {
		$result = API_Update_Updater::block_release( self::SLUG, $this->reviewer_block() );

		$this->assertTrue( $result );
		$this->assertSame( self::SERVED_VERSION, $this->get_served_version() );
		$this->assertSame( 'Suspicious obfuscated code.', $this->get_release_block()['reason'] );
		$this->assertSame( $this->reviewer->user_login, $this->get_release_block()['blocked_by'] );
		$this->assertFalse( wp_next_scheduled( 'release_to_update_api:' . self::SLUG ) );
	}

	/**
	 * The block is recorded in the audit log with the supplied reason.
	 */
	public function test_a_block_records_an_audit_note() {
		API_Update_Updater::block_release( self::SLUG, $this->reviewer_block() );

		$notes = get_comments(
			array(
				'post_id' => $this->plugin->ID,
				'type'    => 'internal-note',
			)
		);

		$block_notes = array_filter(
			$notes,
			function ( $note ) {
				return false !== strpos( $note->comment_content, 'Blocked version 2.0 from being served' );
			}
		);

		$this->assertCount( 1, $block_notes );
	}

	/**
	 * A force-release lifts a reviewer block: the held version is served and the block cleared.
	 */
	public function test_force_release_clears_a_reviewer_block_and_serves() {
		API_Update_Updater::block_release( self::SLUG, $this->reviewer_block() );

		$result = API_Update_Updater::force_release( self::SLUG, 'Reviewed with the author; resolved.' );

		$this->assertTrue( $result );
		$this->assertNull( $this->get_release_block() );
		$this->assertSame( self::NEW_VERSION, $this->get_served_version() );
	}

	/**
	 * The force-release over a reviewer block is recorded, without a risk score to name.
	 */
	public function test_force_release_over_a_reviewer_block_records_the_override() {
		API_Update_Updater::block_release( self::SLUG, $this->reviewer_block() );

		API_Update_Updater::force_release( self::SLUG, 'Reviewed with the author; resolved.' );

		$notes = get_comments(
			array(
				'post_id' => $this->plugin->ID,
				'type'    => 'internal-note',
			)
		);

		$override = array_filter(
			$notes,
			function ( $note ) {
				return false !== strpos( $note->comment_content, 'overriding the release block' );
			}
		);

		$this->assertCount( 1, $override );
	}

	/**
	 * A version that's already live can't be un-shipped by a block; `update_source` is left alone.
	 */
	public function test_a_block_leaves_an_already_served_version_untouched() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- `update_source` lives outside WordPress; there is no API for it.
		$wpdb->update(
			$wpdb->prefix . 'update_source',
			array( 'version' => self::NEW_VERSION ),
			array( 'plugin_slug' => self::SLUG )
		);

		$result = API_Update_Updater::block_release( self::SLUG, $this->reviewer_block() );

		$this->assertFalse( $result );
		$this->assertNull( $this->get_release_block() );
		$this->assertSame( self::NEW_VERSION, $this->get_served_version() );
	}

	/**
	 * With no release row for the current version there's nothing to hold, so the block is a no-op.
	 */
	public function test_a_block_without_a_release_does_nothing() {
		update_post_meta( $this->plugin->ID, 'version', '3.0' );

		$result = API_Update_Updater::block_release( self::SLUG, $this->reviewer_block() );

		$this->assertFalse( $result );
		$this->assertSame( self::SERVED_VERSION, $this->get_served_version() );
	}

	/**
	 * Closing a plugin has to reach sites even while a version is held: the held version stays
	 * held, but the one still being served stops being offered for update.
	 */
	public function test_closing_a_plugin_stops_serving_it_while_a_version_is_held() {
		API_Update_Updater::block_release( self::SLUG, $this->reviewer_block() );

		wp_update_post(
			array(
				'ID'          => $this->plugin->ID,
				'post_status' => 'closed',
			)
		);
		clean_post_cache( $this->plugin->ID );

		API_Update_Updater::update_single_plugin( self::SLUG );

		$row = $this->get_served_row();

		$this->assertEquals( 0, $row['available'] );
		$this->assertSame( self::SERVED_VERSION, $row['version'] );
	}

	/**
	 * The same holds for a version still inside its cooldown: a close isn't deferred with it.
	 */
	public function test_closing_a_plugin_stops_serving_it_during_the_cooldown() {
		wp_update_post(
			array(
				'ID'          => $this->plugin->ID,
				'post_status' => 'closed',
			)
		);
		clean_post_cache( $this->plugin->ID );

		API_Update_Updater::update_single_plugin( self::SLUG );

		$row = $this->get_served_row();

		$this->assertEquals( 0, $row['available'] );
		$this->assertSame( self::SERVED_VERSION, $row['version'] );
	}

	/**
	 * And re-opening it puts the served version back on offer, still without shipping the
	 * held one.
	 */
	public function test_reopening_a_plugin_serves_the_previous_version_again() {
		API_Update_Updater::block_release( self::SLUG, $this->reviewer_block() );

		foreach ( array( 'closed', 'publish' ) as $status ) {
			wp_update_post(
				array(
					'ID'          => $this->plugin->ID,
					'post_status' => $status,
				)
			);
			clean_post_cache( $this->plugin->ID );

			API_Update_Updater::update_single_plugin( self::SLUG );
		}

		$row = $this->get_served_row();

		$this->assertEquals( 1, $row['available'] );
		$this->assertSame( self::SERVED_VERSION, $row['version'] );
	}

	/**
	 * The metabox path end to end: a reviewer submits the Block button and the version is held.
	 */
	public function test_the_metabox_blocks_the_version() {
		$this->submit_controls(
			array(
				'block_release_version' => self::NEW_VERSION,
				'release_action_reason' => 'Suspicious obfuscated code.',
			)
		);

		$this->assertSame( 'Suspicious obfuscated code.', $this->get_release_block()['reason'] );
		$this->assertSame( $this->reviewer->user_login, $this->get_release_block()['blocked_by'] );
		$this->assertSame( self::SERVED_VERSION, $this->get_served_version() );
	}

	/**
	 * A submission from a user without the reviewer capability does nothing.
	 */
	public function test_the_metabox_ignores_a_user_without_the_review_capability() {
		$this->reviewer->remove_cap( 'plugin_review' );

		$this->submit_controls(
			array(
				'block_release_version' => self::NEW_VERSION,
				'release_action_reason' => 'Suspicious obfuscated code.',
			)
		);

		$this->assertNull( $this->get_release_block() );
	}

	/**
	 * A save that isn't a release action at all is left alone.
	 */
	public function test_the_metabox_ignores_an_unrelated_save() {
		$this->submit_controls( array( 'release_action_reason' => 'Suspicious obfuscated code.' ) );

		$this->assertNull( $this->get_release_block() );
	}

	/**
	 * A reason is required.
	 */
	public function test_the_metabox_refuses_a_block_without_a_reason() {
		$this->submit_controls(
			array(
				'block_release_version' => self::NEW_VERSION,
				'release_action_reason' => '   ',
			)
		);

		$this->assertNull( $this->get_release_block() );
	}

	/**
	 * A form rendered before a newer commit landed doesn't block the wrong version.
	 */
	public function test_the_metabox_refuses_a_stale_version() {
		update_post_meta( $this->plugin->ID, 'version', '3.0' );

		$this->submit_controls(
			array(
				'block_release_version' => self::NEW_VERSION,
				'release_action_reason' => 'Suspicious obfuscated code.',
			)
		);

		$this->assertNull( $this->get_release_block() );
	}

	/**
	 * The force-release button ships the held version.
	 */
	public function test_the_metabox_force_releases() {
		API_Update_Updater::block_release( self::SLUG, $this->reviewer_block() );

		$this->submit_controls(
			array(
				'force_release_version' => self::NEW_VERSION,
				'release_action_reason' => 'Reviewed with the author; resolved.',
			)
		);

		$this->assertNull( $this->get_release_block() );
		$this->assertSame( self::NEW_VERSION, $this->get_served_version() );
	}
}
