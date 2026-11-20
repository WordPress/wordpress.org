<?php
/**
 * Tests archiving and unarchiving topics.
 *
 * @package support-forums
 */

declare( strict_types = 1 );

use WordPressdotorg\Forums\Plugin;

/**
 * Archiving a topic takes its public replies with it, and unarchiving it
 * brings back only those.
 */
class Topic_Archive_Test extends WP_UnitTestCase {

	/**
	 * Replies archived on their own, or not public, keep their status through
	 * archiving and unarchiving the topic.
	 *
	 * @return void
	 */
	public function test_topic_archive_cascades_to_public_replies_only(): void {
		$moderators = Plugin::get_instance()->moderators;

		$moderator_id = self::factory()->user->create();
		get_userdata( $moderator_id )->set_role( bbp_get_moderator_role() );
		wp_set_current_user( $moderator_id );

		$forum_id = bbp_insert_forum( array( 'post_title' => 'Support' ) );
		$topic_id = bbp_insert_topic(
			array(
				'post_parent'  => $forum_id,
				'post_title'   => 'Topic',
				'post_content' => 'Topic content.',
			),
			array( 'forum_id' => $forum_id )
		);

		$reply_ids = array();
		foreach ( array( 'public', 'archived', 'pending' ) as $name ) {
			$reply_ids[ $name ] = bbp_insert_reply(
				array(
					'post_parent'  => $topic_id,
					'post_content' => "The {$name} reply.",
				),
				array(
					'forum_id' => $forum_id,
					'topic_id' => $topic_id,
				)
			);
		}
		$moderators->archive_post( $reply_ids['archived'] );
		bbp_unapprove_reply( $reply_ids['pending'] );

		$this->assertTrue( $moderators->archive_post( $topic_id ) );
		$this->assertSame(
			array(
				'public'   => 'archived',
				'archived' => 'archived',
				'pending'  => 'pending',
			),
			array_map( 'get_post_status', $reply_ids )
		);

		$this->assertTrue( $moderators->unarchive_post( $topic_id ) );
		$this->assertSame(
			array(
				'public'   => 'publish',
				'archived' => 'archived',
				'pending'  => 'pending',
			),
			array_map( 'get_post_status', $reply_ids )
		);
	}
}
