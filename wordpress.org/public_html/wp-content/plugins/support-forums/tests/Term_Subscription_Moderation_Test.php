<?php
/**
 * Tests term subscription notifications for topics held for moderation.
 *
 * @package support-forums
 */

declare( strict_types = 1 );

use WordPressdotorg\Forums\Term_Subscription\Plugin as Term_Subscription;

/**
 * Term subscribers hear about a held topic once it's approved.
 */
class Term_Subscription_Moderation_Test extends WP_UnitTestCase {

	/**
	 * Restore the PHPUnit 9 API that Core's expectDeprecated() relies on.
	 *
	 * Core's test framework predates PHPUnit 10 and falls back to other
	 * PHPUnit 9 APIs when this method is missing. None of these tests use
	 * annotation-based expectations, so an empty set is equivalent.
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
	 * A held topic reaches its tag's subscribers on approval, and not before.
	 */
	public function test_held_topic_notifies_term_subscribers_once_approved(): void {
		// PHPMailer rejects the default noreply@localhost as a sender.
		$no_reply = static function (): string {
			return 'noreply@example.org';
		};
		add_filter( 'bbp_get_do_not_reply_address', $no_reply );

		$author_id     = self::factory()->user->create();
		$subscriber_id = self::factory()->user->create( array( 'user_email' => 'tag-subscriber@example.org' ) );
		$forum_id      = bbp_insert_forum( array( 'post_title' => 'Fixing WordPress' ) );
		$tag           = wp_insert_term( 'login', bbp_get_topic_tag_tax_id() );

		// bbPress only emails subscribers who can read the topic.
		bbp_set_user_role( $subscriber_id, bbp_get_participant_role() );
		Term_Subscription::add_user_subscription( $subscriber_id, $tag['term_id'] );

		$topic_id = bbp_insert_topic(
			array(
				'post_parent'  => $forum_id,
				'post_status'  => bbp_get_pending_status_id(),
				'post_author'  => $author_id,
				'post_title'   => 'Cannot log in',
				'post_content' => 'The login form reloads without an error.',
			),
			array( 'forum_id' => $forum_id )
		);
		wp_set_object_terms( $topic_id, $tag['term_id'], bbp_get_topic_tag_tax_id() );

		reset_phpmailer_instance();
		do_action( 'bbp_new_topic', $topic_id, $forum_id, array(), $author_id );

		$this->assertNotContains( 'tag-subscriber@example.org', $this->get_recipients(), 'A held topic was announced before approval.' );

		bbp_approve_topic( $topic_id );
		remove_filter( 'bbp_get_do_not_reply_address', $no_reply );

		$this->assertContains( 'tag-subscriber@example.org', $this->get_recipients() );
	}

	/**
	 * Collects every address the mock mailer has sent to.
	 *
	 * @return string[] Email addresses.
	 */
	private function get_recipients(): array {
		$recipients = array();

		foreach ( tests_retrieve_phpmailer_instance()->mock_sent as $email ) {
			foreach ( array( 'to', 'cc', 'bcc' ) as $field ) {
				foreach ( $email[ $field ] as $address ) {
					$recipients[] = $address[0];
				}
			}
		}

		return $recipients;
	}
}
