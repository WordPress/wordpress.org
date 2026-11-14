<?php
/**
 * Tests the unsubscription link in topic subscription emails.
 *
 * @package support-forums
 */

declare( strict_types = 1 );

use WordPressdotorg\Forums\Emails;
use WordPressdotorg\Forums\Plugin;

/**
 * Topic subscribers can unsubscribe from the link in their email, without logging in.
 */
class Topic_Unsubscribe_Link_Test extends WP_UnitTestCase {

	/**
	 * Restore the PHPUnit 9 API that Core's expectDeprecated() relies on.
	 *
	 * None of these tests use annotation-based expectations, so an empty set is equivalent.
	 *
	 * @return array[]
	 */
	public function getAnnotations(): array {
		return array(
			'class'  => array(),
			'method' => array(),
		);
	}

	/**
	 * Create the shared @moderator account that production has.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		// Subscribing looks this account up, and warns when it's missing.
		self::factory()->user->create( array( 'user_login' => 'moderator' ) );
	}

	/**
	 * Restore the request method the test bootstrap sets.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		$_SERVER['REQUEST_METHOD'] = 'GET';

		parent::tear_down();
	}

	/**
	 * The emailed link asks for confirmation on a visit and unsubscribes on a POST.
	 *
	 * @return void
	 */
	public function test_emailed_link_unsubscribes_after_confirmation(): void {
		// PHPMailer rejects the default noreply@localhost as a sender.
		$no_reply = static function (): string {
			return 'noreply@example.org';
		};
		add_filter( 'bbp_get_do_not_reply_address', $no_reply );

		$subscriber_id = self::factory()->user->create( array( 'user_email' => 'topic-subscriber@example.org' ) );
		$topic_id      = $this->create_topic();
		$forum_id      = bbp_get_topic_forum_id( $topic_id );
		$reply_id      = bbp_insert_reply(
			array(
				'post_parent'  => $topic_id,
				'post_author'  => self::factory()->user->create(),
				'post_content' => 'Clearing the cookies fixed it for me.',
			),
			array(
				'forum_id' => $forum_id,
				'topic_id' => $topic_id,
			)
		);

		// bbPress only emails subscribers who can read the topic.
		bbp_set_user_role( $subscriber_id, bbp_get_participant_role() );
		bbp_add_user_subscription( $subscriber_id, $topic_id );

		reset_phpmailer_instance();
		bbp_notify_topic_subscribers( $reply_id, $topic_id, $forum_id );
		remove_filter( 'bbp_get_do_not_reply_address', $no_reply );

		$email = tests_retrieve_phpmailer_instance()->get_sent();
		$this->assertSame( 'topic-subscriber@example.org', $email->to[0][0] );
		$this->assertMatchesRegularExpression( '/click here:\s*<([^>]+)>/', $email->body );

		preg_match( '/click here:\s*<([^>]+)>/', $email->body, $matches );
		$unsubscribe_url = $matches[1];

		// PHPMailer MIME-encodes long header values.
		$headers = iconv_mime_decode_headers( $email->header, ICONV_MIME_DECODE_CONTINUE_ON_ERROR );
		$this->assertSame( '<' . $unsubscribe_url . '>', $headers['List-Unsubscribe'] );
		$this->assertSame( 'List-Unsubscribe=One-Click', $headers['List-Unsubscribe-Post'] );

		$this->request( $unsubscribe_url, 'GET' );
		$this->assertTrue( bbp_is_user_subscribed( $subscriber_id, $topic_id ), 'Visiting the link unsubscribed without confirmation.' );

		$this->request( $unsubscribe_url, 'POST' );
		$this->assertFalse( bbp_is_user_subscribed( $subscriber_id, $topic_id ) );
	}

	/**
	 * A link sent for one topic doesn't unsubscribe from another.
	 *
	 * @return void
	 */
	public function test_link_for_another_topic_is_rejected(): void {
		$subscriber_id = self::factory()->user->create();
		$topic_id      = $this->create_topic();
		$other_id      = $this->create_topic();
		bbp_add_user_subscription( $subscriber_id, $topic_id );
		bbp_add_user_subscription( $subscriber_id, $other_id );

		wp_parse_str( wp_parse_url( Emails::get_topic_unsubscribe_url( get_userdata( $subscriber_id ), $topic_id ), PHP_URL_QUERY ), $query );
		$other_url = add_query_arg(
			array(
				'action' => $query['action'],
				'token'  => $query['token'],
			),
			bbp_get_topic_permalink( $other_id )
		);
		$this->request( $other_url, 'POST', false );

		$this->assertTrue( bbp_is_user_subscribed( $subscriber_id, $other_id ) );
	}

	/**
	 * Creates a topic in a new forum.
	 *
	 * @return int Topic ID.
	 */
	private function create_topic(): int {
		$forum_id = bbp_insert_forum( array( 'post_title' => 'Fixing WordPress' ) );

		return bbp_insert_topic(
			array(
				'post_parent'  => $forum_id,
				'post_author'  => self::factory()->user->create(),
				'post_title'   => 'Cannot log in',
				'post_content' => 'The login form reloads without an error.',
			),
			array( 'forum_id' => $forum_id )
		);
	}

	/**
	 * Requests a URL and runs the unsubscription handler on it.
	 *
	 * @param string $url         The URL to request.
	 * @param string $method      The request method.
	 * @param bool   $expect_page Whether the handler is expected to render its own page.
	 * @return void
	 */
	private function request( string $url, string $method, bool $expect_page = true ): void {
		$this->go_to( $url );
		$_SERVER['REQUEST_METHOD'] = $method;

		try {
			Plugin::get_instance()->emails->topic_unsubscribe_handler();
		} catch ( WPDieException $e ) {
			$this->assertTrue( $expect_page, 'The handler rendered a page: ' . $e->getMessage() );
			return;
		}

		$this->assertFalse( $expect_page, 'The handler did not render a page.' );
	}
}
