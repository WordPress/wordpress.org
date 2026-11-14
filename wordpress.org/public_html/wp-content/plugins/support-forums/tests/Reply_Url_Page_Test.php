<?php
/**
 * Tests the page number in reply URLs.
 *
 * @package support-forums
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Reply URLs point at the page the reply is shown on, not counting hidden replies.
 */
class Reply_Url_Page_Test extends WP_UnitTestCase {

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
	 * The 16th reply of a topic is on page 2 unless an earlier reply is hidden.
	 *
	 * @param string $first_reply_status Post status of the first reply.
	 * @param string $expected_page_path Pagination path expected in the last reply’s URL.
	 * @return void
	 */
	#[DataProvider( 'data_last_reply_page' )]
	public function test_last_reply_page( string $first_reply_status, string $expected_page_path ): void {
		$this->set_permalink_structure( '/%postname%/' );
		add_filter( 'bbp_show_lead_topic', '__return_true' );

		$forum_id = bbp_insert_forum( array( 'post_title' => 'Support' ) );
		$topic_id = bbp_insert_topic(
			array(
				'post_parent'  => $forum_id,
				'post_title'   => 'Topic',
				'post_content' => 'Topic content.',
				'post_date'    => '2026-01-01 00:00:00',
			),
			array( 'forum_id' => $forum_id )
		);

		for ( $position = 1; $position <= 16; $position++ ) {
			$reply_id = bbp_insert_reply(
				array(
					'post_parent'  => $topic_id,
					'post_status'  => 1 === $position ? $first_reply_status : bbp_get_public_status_id(),
					'post_content' => "Reply {$position}.",
					'post_date'    => sprintf( '2026-01-01 00:%02d:00', $position ),
					'menu_order'   => $position,
				),
				array(
					'forum_id' => $forum_id,
					'topic_id' => $topic_id,
				)
			);
		}

		$this->assertSame(
			trailingslashit( bbp_get_topic_permalink( $topic_id ) ) . $expected_page_path . '#post-' . $reply_id,
			bbp_get_reply_url( $reply_id )
		);
	}

	/**
	 * Statuses of the first reply, with the pagination path the last reply should link to.
	 *
	 * @return array[]
	 */
	public static function data_last_reply_page(): array {
		return array(
			'all replies visible'  => array( 'publish', 'page/2/' ),
			'first reply archived' => array( 'archived', '' ),
		);
	}
}
