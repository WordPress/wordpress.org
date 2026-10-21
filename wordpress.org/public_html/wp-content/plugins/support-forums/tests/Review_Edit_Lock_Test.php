<?php
/**
 * Tests the edit window exception for review topics.
 *
 * @package support-forums
 */

use WordPressdotorg\Forums\Plugin;

/**
 * Review authors may update old review topics without opening other content.
 */
class Review_Edit_Lock_Test extends WP_UnitTestCase {

	/**
	 * Previous POST data.
	 *
	 * @var array
	 */
	private $old_post;

	/**
	 * Previous server data.
	 *
	 * @var array
	 */
	private $old_server;

	/**
	 * Previous bbPress errors.
	 *
	 * @var WP_Error
	 */
	private $old_errors;

	/** Set up request and error state. */
	public function setUp(): void {
		parent::setUp();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Test fixture preserves the incoming request.
		$this->old_post   = $_POST;
		$this->old_server = $_SERVER;
		$this->old_errors = bbpress()->errors;

		bbpress()->errors = new WP_Error();
	}

	/** Restore request and error state. */
	public function tearDown(): void {
		$_POST            = $this->old_post;
		$_SERVER          = $this->old_server;
		bbpress()->errors = $this->old_errors;

		parent::tearDown();
	}

	/** Review authors can edit after the window, without changing other permissions. */
	public function test_only_author_can_edit_old_review_topic_from_directory_view() {
		$author_id = self::factory()->user->create();
		$other_id  = self::factory()->user->create();
		bbp_set_user_role( $author_id, bbp_get_participant_role() );
		bbp_set_user_role( $other_id, bbp_get_participant_role() );

		$reviews_forum_id = wp_insert_post(
			array(
				'import_id'   => Plugin::REVIEWS_FORUM_ID,
				'post_type'   => bbp_get_forum_post_type(),
				'post_status' => bbp_get_public_status_id(),
				'post_title'  => 'Reviews',
			)
		);
		$this->assertSame( Plugin::REVIEWS_FORUM_ID, $reviews_forum_id );

		$normal_forum_id = wp_insert_post(
			array(
				'post_type'   => bbp_get_forum_post_type(),
				'post_status' => bbp_get_public_status_id(),
				'post_title'  => 'Support',
			)
		);
		$old_date        = '2020-01-01 00:00:00';
		$review_id       = wp_insert_post(
			array(
				'post_type'     => bbp_get_topic_post_type(),
				'post_status'   => bbp_get_public_status_id(),
				'post_parent'   => $reviews_forum_id,
				'post_author'   => $author_id,
				'post_title'    => 'Review',
				'post_content'  => 'Old review.',
				'post_date'     => $old_date,
				'post_date_gmt' => $old_date,
			)
		);
		$normal_topic_id = wp_insert_post(
			array(
				'post_type'     => bbp_get_topic_post_type(),
				'post_status'   => bbp_get_public_status_id(),
				'post_parent'   => $normal_forum_id,
				'post_author'   => $author_id,
				'post_title'    => 'Support topic',
				'post_content'  => 'Old topic.',
				'post_date'     => $old_date,
				'post_date_gmt' => $old_date,
			)
		);

		wp_set_current_user( $author_id );
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST['action']           = 'bbp-edit-topic';
		$_POST['bbp_topic_id']     = $review_id;

		$this->assertFalse( bbp_is_topic_edit() );
		// phpcs:ignore WordPress.WP.Capabilities.Unknown -- bbPress registers the edit_topic meta capability.
		$this->assertTrue( current_user_can( 'edit_topic', $review_id ) );
		$this->assertFalse( bbp_past_edit_lock( $old_date ) );

		// A missing nonce stops at the next gate, after the edit permission check.
		bbp_edit_topic_handler( 'bbp-edit-topic' );
		$this->assertNotContains( 'bbp_edit_topic_permission', bbpress()->errors->get_error_codes() );
		$this->assertContains( 'bbp_edit_topic_nonce', bbpress()->errors->get_error_codes() );

		// The bbPress handler rejects the same old review without this exception.
		$hook = Plugin::get_instance()->hooks;
		remove_filter( 'bbp_past_edit_lock', array( $hook, 'allow_review_topic_edits' ), 10 );
		try {
			bbpress()->errors = new WP_Error();
			bbp_edit_topic_handler( 'bbp-edit-topic' );
			$this->assertContains( 'bbp_edit_topic_permission', bbpress()->errors->get_error_codes() );
		} finally {
			add_filter( 'bbp_past_edit_lock', array( $hook, 'allow_review_topic_edits' ), 10, 2 );
		}

		$_POST['bbp_topic_id'] = $normal_topic_id;
		$this->assertTrue( bbp_past_edit_lock( $old_date ) );
		bbpress()->errors = new WP_Error();
		bbp_edit_topic_handler( 'bbp-edit-topic' );
		$this->assertContains( 'bbp_edit_topic_permission', bbpress()->errors->get_error_codes() );

		$_POST['bbp_topic_id'] = $review_id;
		wp_set_current_user( $other_id );
		$this->assertTrue( bbp_past_edit_lock( $old_date ) );
		// phpcs:ignore WordPress.WP.Capabilities.Unknown -- bbPress registers the edit_topic meta capability.
		$this->assertFalse( current_user_can( 'edit_topic', $review_id ) );

		wp_set_current_user( $author_id );
		$_POST['action'] = 'bbp-edit-reply';
		$this->assertTrue( bbp_past_edit_lock( $old_date ) );

		// The ordinary topic edit route also permits the review author.
		$_SERVER['REQUEST_METHOD']  = 'GET';
		$old_topic_id               = bbpress()->current_topic_id;
		bbpress()->current_topic_id = $review_id;
		add_filter( 'bbp_is_topic_edit', '__return_true' );
		try {
			$this->assertFalse( bbp_past_edit_lock( $old_date ) );
			// phpcs:ignore WordPress.WP.Capabilities.Unknown -- bbPress registers the edit_topic meta capability.
			$this->assertTrue( current_user_can( 'edit_topic', $review_id ) );
		} finally {
			remove_filter( 'bbp_is_topic_edit', '__return_true' );
			bbpress()->current_topic_id = $old_topic_id;
		}

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST['action']           = 'bbp-edit-topic';
		add_filter( 'bbp_allow_content_edit', '__return_false' );
		try {
			$this->assertTrue( bbp_past_edit_lock( $old_date ) );
		} finally {
			remove_filter( 'bbp_allow_content_edit', '__return_false' );
		}

		// Anonymous authors cannot gain this exception by matching user ID zero.
		wp_update_post(
			array(
				'ID'          => $review_id,
				'post_author' => 0,
			)
		);
		wp_set_current_user( 0 );
		$this->assertTrue( bbp_past_edit_lock( $old_date ) );
	}
}
