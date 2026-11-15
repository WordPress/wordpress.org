<?php
/**
 * Tests the user notes added when a topic, reply, or review is held for moderation.
 *
 * @package support-forums
 */

declare( strict_types = 1 );

use WordPressdotorg\Forums\Plugin;
use WordPressdotorg\Forums\User_Notes;

/**
 * A held post leaves a note on its author's account naming why it was held.
 */
class Pending_Post_Audit_Test extends WP_UnitTestCase {

	/**
	 * The posting user.
	 *
	 * @var int
	 */
	private $author_id;

	/**
	 * The forum posts go to.
	 *
	 * @var int
	 */
	private $forum_id;

	/**
	 * Keep Core's annotation lookup compatible with PHPUnit 11.
	 *
	 * @return array[]
	 */
	public function getAnnotations(): array {
		return array(
			'class'  => array(),
			'method' => array(),
		);
	}

	/** Set up a participant, a forum, and the Comment Moderation list. */
	public function setUp(): void {
		parent::setUp();

		$this->author_id = self::factory()->user->create();
		bbp_set_user_role( $this->author_id, bbp_get_participant_role() );
		wp_set_current_user( $this->author_id );

		$this->forum_id = bbp_insert_forum( array( 'post_title' => 'Fixing WordPress' ) );

		update_option( 'moderation_keys', "peculiar\nodd#word\nunused" );
	}

	/**
	 * Insert a topic as the author.
	 *
	 * @param string $status  Post status.
	 * @param string $content Post content.
	 * @param int    $forum   Forum ID. Default the test forum.
	 * @return int Topic ID.
	 */
	private function insert_topic( string $status, string $content, int $forum = 0 ): int {
		$forum = $forum ? $forum : $this->forum_id;

		return bbp_insert_topic(
			array(
				'post_parent'  => $forum,
				'post_status'  => $status,
				'post_author'  => $this->author_id,
				'post_title'   => 'Login form',
				'post_content' => $content,
			),
			array( 'forum_id' => $forum )
		);
	}

	/**
	 * Get the notes on the author's account.
	 *
	 * @return array
	 */
	private function get_notes(): array {
		return get_user_meta( $this->author_id, User_Notes::META, true ) ?: array();
	}

	/** A held topic names every matched word and links to the topic. */
	public function test_held_topic_names_matched_words(): void {
		$topic_id = $this->insert_topic( bbp_get_pending_status_id(), 'A Peculiar error, and an odd#word too.' );
		do_action( 'bbp_new_topic', $topic_id, $this->forum_id, array(), $this->author_id );

		$notes = $this->get_notes();
		$this->assertCount( 1, $notes );
		$this->assertSame( 'Topic held for moderation: matched "peculiar", "odd#word".', $notes[1]->text );
		$this->assertSame( $topic_id, $notes[1]->post_id );
	}

	/** Words hidden in HTML tags still count, as they do for bbPress. */
	public function test_held_reply_matches_like_bbpress(): void {
		$topic_id = $this->insert_topic( 'publish', 'The login form reloads.' );
		$reply_id = bbp_insert_reply(
			array(
				'post_parent'  => $topic_id,
				'post_status'  => bbp_get_pending_status_id(),
				'post_author'  => $this->author_id,
				'post_content' => 'Still <em>pecu</em>liar here.',
			),
			array(
				'forum_id' => $this->forum_id,
				'topic_id' => $topic_id,
			)
		);
		do_action( 'bbp_new_reply', $reply_id, $topic_id, $this->forum_id, array(), $this->author_id, false, 0 );

		$this->assertSame( 'Reply held for moderation: matched "peculiar".', $this->get_notes()[1]->text );
	}

	/** An edit that is held says so, and a review is called a review. */
	public function test_edited_review_is_labeled(): void {
		$topic_id = $this->insert_topic( bbp_get_pending_status_id(), 'Works, but peculiar.', Plugin::REVIEWS_FORUM_ID );
		do_action( 'bbp_edit_topic', $topic_id, Plugin::REVIEWS_FORUM_ID, array(), $this->author_id, true );

		$this->assertSame( 'Edited review held for moderation: matched "peculiar".', $this->get_notes()[1]->text );
	}

	/** Akismet holds are logged too. */
	public function test_akismet_hold_is_logged(): void {
		$topic_id = $this->insert_topic( bbp_get_pending_status_id(), 'Nothing unusual.' );
		update_post_meta( $topic_id, '_bbp_akismet_result', 'true' );
		do_action( 'bbp_new_topic', $topic_id, $this->forum_id, array(), $this->author_id );

		$this->assertSame( 'Topic held for moderation: flagged by Akismet.', $this->get_notes()[1]->text );
	}

	/** Published posts, spam, holds with no known cause, and moderator edits add no note. */
	public function test_no_note_without_a_moderation_hold(): void {
		$published = $this->insert_topic( 'publish', 'A peculiar error.' );
		do_action( 'bbp_new_topic', $published, $this->forum_id, array(), $this->author_id );

		$spam = $this->insert_topic( bbp_get_spam_status_id(), 'A peculiar error.' );
		update_post_meta( $spam, '_bbp_akismet_result', 'true' );
		do_action( 'bbp_new_topic', $spam, $this->forum_id, array(), $this->author_id );

		$unknown = $this->insert_topic( bbp_get_pending_status_id(), 'Nothing unusual.' );
		do_action( 'bbp_new_topic', $unknown, $this->forum_id, array(), $this->author_id );

		$held = $this->insert_topic( bbp_get_pending_status_id(), 'A peculiar error.' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		do_action( 'bbp_edit_topic', $held, $this->forum_id, array(), $this->author_id, true );

		$this->assertEmpty( $this->get_notes() );
	}
}
