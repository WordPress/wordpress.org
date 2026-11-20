<?php
/**
 * Tests the moderator Close/Open link on topics.
 *
 * @package support-forums
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The Close/Open link only offers an action that can succeed.
 */
class Topic_Close_Link_Test extends WP_UnitTestCase {

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
	 * Open topics get Close, closed topics get Open, and auto-closed topics get no link.
	 *
	 * @param string $state    Topic state: 'open', 'closed' or 'auto-closed'.
	 * @param string $expected Action the link carries, or an empty string for no link.
	 * @return void
	 */
	#[DataProvider( 'data_close_link' )]
	public function test_close_link( string $state, string $expected ): void {
		$moderator_id = self::factory()->user->create();
		get_userdata( $moderator_id )->set_role( bbp_get_moderator_role() );
		wp_set_current_user( $moderator_id );

		$forum_id = bbp_insert_forum( array( 'post_title' => 'Support' ) );
		$topic_id = bbp_insert_topic(
			array(
				'post_parent'  => $forum_id,
				'post_title'   => 'Topic',
				'post_content' => 'Topic content.',
				'post_date'    => 'auto-closed' === $state ? gmdate( 'Y-m-d H:i:s', strtotime( '-7 months' ) ) : current_time( 'mysql' ),
			),
			array( 'forum_id' => $forum_id )
		);

		if ( 'closed' === $state ) {
			bbp_close_topic( $topic_id );
		}

		$link = bbp_get_topic_close_link( array( 'id' => $topic_id ) );

		if ( '' === $expected ) {
			$this->assertSame( '', $link );
		} else {
			$this->assertStringContainsString( "action={$expected}", $link );
		}
	}

	/**
	 * Topic states, with the action their Close/Open link carries.
	 *
	 * @return array[]
	 */
	public static function data_close_link(): array {
		return array(
			'open topic'        => array( 'open', 'wporg_bbp_close_topic' ),
			'closed topic'      => array( 'closed', 'wporg_bbp_open_topic' ),
			'auto-closed topic' => array( 'auto-closed', '' ),
		);
	}
}
