<?php
/**
 * Tests the content of forum subscription emails.
 *
 * @package support-forums
 */

declare( strict_types = 1 );

use WordPressdotorg\Forums\Term_Subscription\Plugin as Term_Subscription;

/**
 * Subscription emails are plain text, so code in a post must arrive as written.
 */
class Subscription_Mail_Content_Test extends WP_UnitTestCase {

	/**
	 * Code as bbPress stores it, with its special characters encoded.
	 *
	 * @var string
	 */
	const STORED_CODE = '<code>&lt;div class=&quot;note&quot;&gt;$item&#091;&#039;product&#039;] &amp;&amp; $x&lt;/div&gt;</code>';

	/**
	 * The same code as the poster wrote it.
	 *
	 * @var string
	 */
	const PLAIN_CODE = '<div class="note">$item[\'product\'] && $x</div>';

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
	 * Use a sender address PHPMailer accepts.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		// PHPMailer rejects the default noreply@localhost as a sender.
		add_filter( 'bbp_get_do_not_reply_address', array( $this, 'get_no_reply_address' ) );
		reset_phpmailer_instance();
	}

	/**
	 * Remove the sender address filter.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'bbp_get_do_not_reply_address', array( $this, 'get_no_reply_address' ) );

		parent::tear_down();
	}

	/**
	 * Provides a sender address PHPMailer accepts.
	 *
	 * @return string Email address.
	 */
	public function get_no_reply_address(): string {
		return 'noreply@example.org';
	}

	/**
	 * A topic subscriber gets the code in a reply as written.
	 */
	public function test_topic_subscription_mail_decodes_code(): void {
		$author_id     = self::factory()->user->create();
		$subscriber_id = self::factory()->user->create();
		$forum_id      = bbp_insert_forum( array( 'post_title' => 'Fixing WordPress' ) );
		$topic_id      = bbp_insert_topic(
			array(
				'post_parent'  => $forum_id,
				'post_author'  => $subscriber_id,
				'post_title'   => 'Packing slip',
				'post_content' => 'How do I show the excerpt?',
			),
			array( 'forum_id' => $forum_id )
		);
		bbp_add_user_subscription( $subscriber_id, $topic_id );

		$reply_id = bbp_insert_reply(
			array(
				'post_parent'  => $topic_id,
				'post_author'  => $author_id,
				'post_content' => 'Try this: ' . self::STORED_CODE,
			),
			array(
				'forum_id' => $forum_id,
				'topic_id' => $topic_id,
			)
		);

		bbp_notify_topic_subscribers( $reply_id, $topic_id, $forum_id, array(), $author_id );

		$this->assertStringContainsString( 'Try this: ' . self::PLAIN_CODE, $this->get_sent_body() );
	}

	/**
	 * A tag subscriber gets the code in a new topic as written.
	 */
	public function test_term_subscription_mail_decodes_code(): void {
		$author_id     = self::factory()->user->create();
		$subscriber_id = self::factory()->user->create();
		$forum_id      = bbp_insert_forum( array( 'post_title' => 'Fixing WordPress' ) );
		$tag           = wp_insert_term( 'packing-slip', bbp_get_topic_tag_tax_id() );

		// bbPress only emails subscribers who can read the topic.
		bbp_set_user_role( $subscriber_id, bbp_get_participant_role() );
		Term_Subscription::add_user_subscription( $subscriber_id, $tag['term_id'] );

		$topic_id = bbp_insert_topic(
			array(
				'post_parent'  => $forum_id,
				'post_author'  => $author_id,
				'post_title'   => 'Packing slip',
				'post_content' => 'This shows nothing: ' . self::STORED_CODE,
			),
			array( 'forum_id' => $forum_id )
		);
		wp_set_object_terms( $topic_id, $tag['term_id'], bbp_get_topic_tag_tax_id() );

		do_action( 'bbp_new_topic', $topic_id, $forum_id, array(), $author_id );

		$this->assertStringContainsString( 'This shows nothing: ' . self::PLAIN_CODE, $this->get_sent_body() );
	}

	/**
	 * Returns the body of the last email the mock mailer sent.
	 *
	 * @return string Email body, or an empty string when nothing was sent.
	 */
	private function get_sent_body(): string {
		$email = tests_retrieve_phpmailer_instance()->get_sent();

		return $email ? $email->body : '';
	}
}
