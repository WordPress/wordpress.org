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

		/*
		 * Suffixed so an interrupted run that skips tearDown() doesn't leave a login behind
		 * that fails the insert — and with it every test in the class — on the next run.
		 */
		$login = 'reviewer-block-user-' . uniqid();

		$reviewer_id = wp_insert_user(
			array(
				'user_login' => $login,
				'user_pass'  => 'password',
				'user_email' => $login . '@example.org',
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

		delete_transient( 'settings_errors' );
		remove_filter( 'redirect_post_location', array( Controls::class, 'flag_settings_updated' ) );

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
		/*
		 * Run the updater first so the cooldown actually schedules the deferred serve. Without
		 * it there's no event to cancel, and the assertion below passes whether or not the
		 * block clears one.
		 */
		API_Update_Updater::update_single_plugin( self::SLUG );
		$this->assertNotFalse(
			wp_next_scheduled( 'release_to_update_api:' . self::SLUG ),
			'precondition: the cooldown scheduled a deferred serve'
		);

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
	 * The force-release over a reviewer block is recorded as an override of the block, rather
	 * than as the plain cooldown bypass.
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

	/**
	 * A second block is refused rather than merged into the first, so the recorded reason and
	 * reviewer stay those of the hold that's actually in effect.
	 */
	public function test_a_second_block_is_refused() {
		API_Update_Updater::block_release( self::SLUG, $this->reviewer_block() );

		$result = API_Update_Updater::block_release(
			self::SLUG,
			array(
				'reason'     => 'A different reviewer, a different reason.',
				'blocked_by' => 'someone-else',
			)
		);

		$this->assertFalse( $result );
		$this->assertSame( 'Suspicious obfuscated code.', $this->get_release_block()['reason'] );
		$this->assertSame( $this->reviewer->user_login, $this->get_release_block()['blocked_by'] );
	}

	/**
	 * Syncing availability rewrites only the closure fields: the rollout data describing the
	 * version still being served has to survive a close and a re-open untouched, or the
	 * phased rollout silently turns into a full one.
	 */
	public function test_holding_a_version_preserves_the_served_rollout_meta() {
		$this->unserve();
		$this->serve(
			self::SERVED_VERSION,
			array(
				'release_time' => 1700000000,
				'rollout'      => array( 'strategy' => 'manual-updates-24hr' ),
			)
		);

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

		$meta = $this->get_served_meta();

		$this->assertSame( 1700000000, $meta['release_time'] );
		$this->assertSame( array( 'strategy' => 'manual-updates-24hr' ), $meta['rollout'] );
		$this->assertArrayNotHasKey( 'closed_at', $meta, 're-opening drops the closure fields' );
		$this->assertSame( self::SERVED_VERSION, $this->get_served_version() );
	}

	/**
	 * Closing a plugin while a version is held records the closure in the served row's meta.
	 */
	public function test_closing_a_plugin_records_the_closure_while_a_version_is_held() {
		API_Update_Updater::block_release( self::SLUG, $this->reviewer_block() );

		wp_update_post(
			array(
				'ID'          => $this->plugin->ID,
				'post_status' => 'closed',
			)
		);
		clean_post_cache( $this->plugin->ID );

		API_Update_Updater::update_single_plugin( self::SLUG );

		$this->assertArrayHasKey( 'closed_at', $this->get_served_meta() );
	}

	/**
	 * With no `update_source` row there's nothing being served to correct, so holding a
	 * version is still recorded and nothing is written.
	 */
	public function test_a_block_without_a_served_row_still_holds() {
		$this->unserve();

		$result = API_Update_Updater::block_release( self::SLUG, $this->reviewer_block() );

		$this->assertTrue( $result );
		$this->assertNull( $this->get_served_row() );
	}

	/**
	 * Render the release section of the Controls metabox for the plugin under test.
	 *
	 * The method is protected and reads the post from the global, so both have to be worked
	 * around to exercise it. Asserting on which controls appear rather than on the markup
	 * keeps this from breaking on copy changes.
	 *
	 * @return string The rendered markup.
	 */
	protected function render_controls() {
		wp_set_current_user( $this->reviewer->ID );

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride -- display_release_cooldown() reads the post from the global; there's no way to inject it.
		$GLOBALS['post'] = $this->plugin;

		$display = new ReflectionMethod( Controls::class, 'display_release_cooldown' );
		$display->setAccessible( true );

		ob_start();
		$display->invoke( null );
		$output = ob_get_clean();

		unset( $GLOBALS['post'] );

		return $output;
	}

	/**
	 * A held version stays force-releasable after its cooldown window has passed — otherwise
	 * the block would have no way to be lifted from the edit screen.
	 */
	public function test_the_metabox_offers_force_release_on_a_held_version_past_its_cooldown() {
		API_Update_Updater::block_release( self::SLUG, $this->reviewer_block() );
		$this->elapse_cooldown();

		$output = $this->render_controls();

		$this->assertStringContainsString( 'name="force_release_version"', $output );
		$this->assertStringNotContainsString( 'name="block_release_version"', $output );
	}

	/**
	 * The Block button survives the gap between the cooldown expiring and the deferred serve
	 * actually running — the version isn't served yet, so it can still be held.
	 */
	public function test_the_metabox_offers_block_after_the_cooldown_but_before_the_serve() {
		$this->elapse_cooldown();

		$output = $this->render_controls();

		$this->assertStringContainsString( 'name="block_release_version"', $output );
	}

	/**
	 * Once the version is being served and isn't held, there's nothing left to act on.
	 */
	public function test_the_metabox_shows_nothing_once_the_version_is_served() {
		$this->elapse_cooldown();
		API_Update_Updater::update_single_plugin( self::SLUG );

		$this->assertSame( self::NEW_VERSION, $this->get_served_version(), 'precondition: version is live' );
		$this->assertSame( '', trim( $this->render_controls() ) );
	}

	/**
	 * A refused block tells the reviewer why, rather than leaving them to infer it from the
	 * release section having disappeared.
	 */
	public function test_the_metabox_reports_a_block_that_was_refused() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- `update_source` lives outside WordPress; there is no API for it.
		$wpdb->update(
			$wpdb->prefix . 'update_source',
			array( 'version' => self::NEW_VERSION ),
			array( 'plugin_slug' => self::SLUG )
		);

		$this->submit_controls(
			array(
				'block_release_version' => self::NEW_VERSION,
				'release_action_reason' => 'Suspicious obfuscated code.',
			)
		);

		$notice = get_transient( 'settings_errors' );

		$this->assertNotEmpty( $notice );
		$this->assertSame( 'error', $notice[0]['type'] );
		$this->assertStringContainsString( 'already being served', $notice[0]['message'] );
	}

	/**
	 * A block with no reason says so, instead of silently doing nothing.
	 */
	public function test_the_metabox_reports_a_missing_reason() {
		$this->submit_controls(
			array(
				'block_release_version' => self::NEW_VERSION,
				'release_action_reason' => '   ',
			)
		);

		$notice = get_transient( 'settings_errors' );

		$this->assertNotEmpty( $notice );
		$this->assertStringContainsString( 'a reason is required', $notice[0]['message'] );
	}

	/**
	 * A successful block is confirmed, so the reviewer knows the hold took effect.
	 */
	public function test_the_metabox_confirms_a_successful_block() {
		$this->submit_controls(
			array(
				'block_release_version' => self::NEW_VERSION,
				'release_action_reason' => 'Suspicious obfuscated code.',
			)
		);

		$notice = get_transient( 'settings_errors' );

		$this->assertNotEmpty( $notice );
		$this->assertSame( 'updated', $notice[0]['type'] );
	}
}
