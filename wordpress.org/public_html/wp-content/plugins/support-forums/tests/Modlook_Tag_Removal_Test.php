<?php
/**
 * Tests who can remove the modlook tag from a topic.
 *
 * @package support-forums
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Only moderators can clear a report by removing the modlook tag.
 */
class Modlook_Tag_Removal_Test extends WP_UnitTestCase {

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
	 * Topic forms keep modlook for participants, but not other tags they remove.
	 *
	 * @param bool   $as_moderator Whether a moderator submits the tags, rather than the topic author.
	 * @param string $submitted    The submitted topic tags.
	 * @param string $expected     The topic tags bbPress saves.
	 * @return void
	 */
	#[DataProvider( 'data_tag_names_for_update' )]
	public function test_tag_names_for_update( bool $as_moderator, string $submitted, string $expected ): void {
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
		wp_set_object_terms( $topic_id, array( 'modlook', 'styling' ), bbp_get_topic_tag_tax_id() );

		$user_id = $as_moderator ? $moderator_id : $author_id;

		$this->assertSame( $expected, bbp_get_topic_tag_names_for_update( $topic_id, $submitted, $user_id ) );
	}

	/**
	 * Submitters and tags, with the tags that are saved.
	 *
	 * @return array[]
	 */
	public static function data_tag_names_for_update(): array {
		return array(
			'author removes modlook'     => array( false, 'styling', 'styling, modlook' ),
			'author removes another tag' => array( false, 'modlook', 'modlook' ),
			'moderator removes modlook'  => array( true, 'styling', 'styling' ),
		);
	}
}
