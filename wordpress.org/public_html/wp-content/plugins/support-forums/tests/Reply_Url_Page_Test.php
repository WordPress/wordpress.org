<?php
/**
 * Tests the page number in reply URLs.
 *
 * @package support-forums
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\DataProvider;
use WordPressdotorg\Forums\Plugin;

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
	 * The 16th reply of a topic is on page 2 unless an earlier reply is hidden from the reader.
	 *
	 * @param string $first_reply_status       Post status of the first reply.
	 * @param bool   $reader_wrote_first_reply Whether the logged-in reader wrote the first reply.
	 * @param string $expected_page_path       Pagination path expected in the last reply’s URL.
	 * @return void
	 */
	#[DataProvider( 'data_last_reply_page' )]
	public function test_last_reply_page( string $first_reply_status, bool $reader_wrote_first_reply, string $expected_page_path ): void {
		$first_reply_author = 0;
		if ( $reader_wrote_first_reply ) {
			$first_reply_author = self::factory()->user->create();
			wp_set_current_user( $first_reply_author );
		}

		list( $topic_id, $reply_id ) = $this->create_topic( $first_reply_status, $first_reply_author, bbp_get_public_status_id() );

		$this->assertSame(
			trailingslashit( bbp_get_topic_permalink( $topic_id ) ) . $expected_page_path . '#post-' . $reply_id,
			bbp_get_reply_url( $reply_id )
		);
	}

	/**
	 * Statuses and authors of the first reply, with the pagination path the last reply should link to.
	 *
	 * @return array[]
	 */
	public static function data_last_reply_page(): array {
		return array(
			'all replies visible'              => array( 'publish', false, 'page/2/' ),
			'first reply archived'             => array( 'archived', false, '' ),
			'reader’s own first reply pending' => array( 'pending', true, '' ),
		);
	}

	/**
	 * Approving a reply redirects the moderator to the page it now shows on.
	 *
	 * @return void
	 */
	public function test_approve_reply_redirects_to_visible_page(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		list( $topic_id, $reply_id ) = $this->create_topic( 'archived', 0, bbp_get_pending_status_id() );

		$_REQUEST['_wpnonce'] = wp_create_nonce( 'approve-' . bbp_get_reply_post_type() . '_' . $reply_id );

		$retval = Plugin::get_instance()->moderators->handle_reply_actions(
			array(
				'status'      => 0,
				'message'     => '',
				'redirect_to' => bbp_get_reply_url( $reply_id ),
				'view_all'    => false,
			),
			array(
				'id'     => $reply_id,
				'action' => 'wporg_bbp_approve_reply',
			),
			array()
		);

		unset( $_REQUEST['_wpnonce'] );

		$this->assertSame(
			trailingslashit( bbp_get_topic_permalink( $topic_id ) ) . '#post-' . $reply_id,
			$retval['redirect_to']
		);
	}

	/**
	 * Create a topic with 16 replies, one a minute.
	 *
	 * @param string $first_reply_status Post status of the first reply.
	 * @param int    $first_reply_author Author of the first reply.
	 * @param string $last_reply_status  Post status of the last reply.
	 * @return int[] Topic ID and last reply ID.
	 */
	private function create_topic( string $first_reply_status, int $first_reply_author, string $last_reply_status ): array {
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
			$status = bbp_get_public_status_id();
			if ( 1 === $position ) {
				$status = $first_reply_status;
			} elseif ( 16 === $position ) {
				$status = $last_reply_status;
			}

			$reply_id = bbp_insert_reply(
				array(
					'post_parent'  => $topic_id,
					'post_status'  => $status,
					'post_author'  => 1 === $position ? $first_reply_author : 0,
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

		return array( $topic_id, $reply_id );
	}
}
