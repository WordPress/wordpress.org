<?php
/**
 * Tests reply form access on closed topics.
 *
 * @package support-forums
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The reply form only shows to users whose reply bbPress would accept.
 */
class Closed_Topic_Reply_Form_Test extends WP_UnitTestCase {

	/**
	 * Keep Core's annotation lookup compatible with PHPUnit 11.
	 *
	 * This class uses attributes and has no annotation-based expectations.
	 *
	 * @return array Empty annotation groups expected by Core's test framework.
	 */
	public function getAnnotations(): array {
		return array(
			'class'  => array(),
			'method' => array(),
		);
	}

	/**
	 * The topic author can edit a closed topic but only moderators can reply to it.
	 *
	 * @param bool $is_closed    Whether the topic is closed.
	 * @param bool $as_moderator Whether a moderator views the topic, rather than its author.
	 * @param bool $expected     Whether the reply form is accessible.
	 * @return void
	 */
	#[DataProvider( 'data_reply_form_access' )]
	public function test_reply_form_access( bool $is_closed, bool $as_moderator, bool $expected ): void {
		$author_id    = self::factory()->user->create();
		$moderator_id = self::factory()->user->create();
		get_userdata( $author_id )->set_role( bbp_get_participant_role() );
		get_userdata( $moderator_id )->set_role( bbp_get_moderator_role() );

		$forum_id = bbp_insert_forum( array( 'post_title' => 'Support' ) );
		$topic_id = bbp_insert_topic(
			array(
				'post_parent'  => $forum_id,
				'post_author'  => $author_id,
				'post_title'   => 'Topic',
				'post_content' => 'Topic content.',
			),
			array( 'forum_id' => $forum_id )
		);

		if ( $is_closed ) {
			bbp_close_topic( $topic_id );
		}

		wp_set_current_user( $as_moderator ? $moderator_id : $author_id );
		$this->go_to( get_permalink( $topic_id ) );

		$this->assertTrue( bbp_is_single_topic() );
		$this->assertSame( $expected, bbp_current_user_can_access_create_reply_form() );
	}

	/**
	 * Topic states and viewers, with whether they get the reply form.
	 *
	 * @return array[]
	 */
	public static function data_reply_form_access(): array {
		return array(
			'author on open topic'      => array( false, false, true ),
			'author on closed topic'    => array( true, false, false ),
			'moderator on closed topic' => array( true, true, true ),
		);
	}
}
