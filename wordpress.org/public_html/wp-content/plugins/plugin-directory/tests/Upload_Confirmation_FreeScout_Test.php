<?php
/**
 * Tests the confirmation of an uploaded update, in the review conversation in FreeScout.
 *
 * When an author uploads an update to a plugin in review, the upload handler replies in its review conversation, which
 * tells the author it arrived, and the plugins team that there's something new to review.
 *
 * @package plugin-directory
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\Clients\FreeScout as FreeScout_Client;
use WordPressdotorg\Plugin_Directory\Shortcodes\Upload_Handler;
use WordPressdotorg\Plugin_Directory\Tools\Helpscout;

/**
 * Tests for `Upload_Handler::update_review_email()` with FreeScout.
 *
 * @group plugin-directory
 */
#[Group( 'plugin-directory' )]
class Upload_Confirmation_FreeScout_Test extends TestCase {

	/**
	 * The review conversation's ID in FreeScout.
	 *
	 * @var int
	 */
	const CONVERSATION_ID = 42;

	/**
	 * A made-up ID of the HelpScout conversation the review conversation was imported from.
	 *
	 * @var int
	 */
	const HELPSCOUT_ID = 9000000042;

	/**
	 * Filters added during a test, each a hook and its callback, removed again on teardown.
	 *
	 * @var array[]
	 */
	protected $filters = array();

	/**
	 * Requests sent to FreeScout: URL, and arguments.
	 *
	 * @var array[]
	 */
	protected $requests = array();

	/**
	 * IDs of posts and users created during a test.
	 *
	 * @var int[][]
	 */
	protected $created = array(
		'posts' => array(),
		'users' => array(),
	);

	/**
	 * Configures FreeScout, and answers its API.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->filter( 'wporg_plugins_freescout_mailbox_id', static fn(): int => 3 );
		$this->filter( 'wporg_plugins_freescout_api_key', static fn(): string => 'test-key' );
		$this->filter( 'wporg_plugins_freescout_user_id', static fn(): int => 12 );
	}

	/**
	 * Removes what the test added.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		global $wpdb;

		foreach ( $this->filters as list( $hook, $callback ) ) {
			remove_filter( $hook, $callback );
		}
		foreach ( $this->created['posts'] as $post_id ) {
			wp_delete_post( $post_id, true );
		}
		foreach ( $this->created['users'] as $user_id ) {
			wp_delete_user( $user_id );
		}
		foreach ( array( self::CONVERSATION_ID, self::HELPSCOUT_ID ) as $id ) {
			$wpdb->delete( "{$wpdb->base_prefix}helpscout", array( 'id' => $id ) );
			$wpdb->delete( "{$wpdb->base_prefix}helpscout_meta", array( 'helpscout_id' => $id ) );
		}
		wp_set_current_user( 0 );

		parent::tearDown();
	}

	/**
	 * The author's upload is confirmed by a reply from the configured agent, which makes the conversation active again.
	 *
	 * @return void
	 */
	public function test_replies_in_the_review_conversation(): void {
		$this->answer();
		list( $plugin, $attachment, $author ) = $this->upload( true );

		$this->assertTrue( Upload_Handler::update_review_email( $plugin, $attachment ) );

		$this->assertCount( 1, $this->requests );
		$this->assertSame( 'https://freescout.wordpress.net/api/conversations/' . self::CONVERSATION_ID . '/threads', $this->requests[0]['url'] );
		$this->assertSame( 'test-key', $this->requests[0]['args']['headers']['X-FreeScout-API-Key'] );

		$body = json_decode( (string) $this->requests[0]['args']['body'], true );
		$this->assertSame( array( 'message', 12, array( $author->user_email ), 'active' ), array( $body['type'], $body['user'], $body['to'], $body['status'] ) );
		$this->assertStringContainsString( 'This is an automated message to confirm that we have received your updated plugin file.', $body['text'] );
		$this->assertStringContainsString( 'version 1.2', $body['text'] );
	}

	/**
	 * Without an agent to reply as, nothing is sent.
	 *
	 * @return void
	 */
	public function test_needs_the_agent(): void {
		$this->answer();
		$this->filter( 'wporg_plugins_freescout_user_id', static fn(): int => 0 );
		list( $plugin, $attachment ) = $this->upload( true );

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- The warning it logs is expected.
		$this->assertFalse( @Upload_Handler::update_review_email( $plugin, $attachment ) );
		$this->assertSame( array(), $this->requests );
	}

	/**
	 * Without a key, there's nothing to ask FreeScout with.
	 *
	 * @return void
	 */
	public function test_needs_a_key(): void {
		$this->answer();
		$this->filter( 'wporg_plugins_freescout_api_key', static fn(): string => '' );
		list( $plugin, $attachment ) = $this->upload( true );

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- The warning it logs is expected.
		$this->assertFalse( @Upload_Handler::update_review_email( $plugin, $attachment ) );
		$this->assertSame( array(), $this->requests );
	}

	/**
	 * While HelpScout's copy of an imported conversation lingers next to FreeScout's, with the same date, FreeScout's is
	 * the review conversation, whatever their IDs.
	 *
	 * @return void
	 */
	public function test_prefers_freescouts_copy_of_the_same_conversation(): void {
		global $wpdb;

		$this->answer();
		list( $plugin, $attachment ) = $this->upload( true );

		$wpdb->insert(
			"{$wpdb->base_prefix}helpscout",
			array(
				'id'      => self::HELPSCOUT_ID,
				'number'  => 1234,
				'subject' => '[WordPress Plugin Directory] Review in Progress: Upload Confirmation Fixture',
				'created' => '2026-08-01 10:00:00',
			)
		);
		$wpdb->insert(
			"{$wpdb->base_prefix}helpscout_meta",
			array(
				'helpscout_id' => self::HELPSCOUT_ID,
				'meta_key'     => 'plugins', // phpcs:ignore WordPress.DB.SlowDBQuery -- Test fixture.
				'meta_value'   => 'upload-confirmation-fixture', // phpcs:ignore WordPress.DB.SlowDBQuery -- Test fixture.
			)
		);

		$emails = Helpscout::get_emails( $plugin );
		$this->assertSame( array( self::CONVERSATION_ID, self::HELPSCOUT_ID ), array_map( 'intval', wp_list_pluck( $emails, 'id' ) ) );

		$this->assertTrue( Upload_Handler::update_review_email( $plugin, $attachment ) );
		$this->assertSame( 'https://freescout.wordpress.net/api/conversations/' . self::CONVERSATION_ID . '/threads', $this->requests[0]['url'] );
	}

	/**
	 * A review conversation still in HelpScout is replied to there, even once the plugins team is on FreeScout.
	 *
	 * @return void
	 */
	public function test_leaves_helpscouts_conversations_to_helpscout(): void {
		$this->answer();
		list( $plugin, $attachment ) = $this->upload( false );

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- HelpScout isn't configured here, and the warning it logs is expected.
		@Upload_Handler::update_review_email( $plugin, $attachment );

		$this->assertSame( array(), $this->requests );
	}

	/**
	 * A request that gets no response returns why, so the failure that's logged can be told apart.
	 *
	 * @return void
	 */
	public function test_request_without_a_response_returns_the_error(): void {
		$this->filter( 'pre_http_request', static fn(): WP_Error => new WP_Error( 'http_request_failed', 'Connection timed out' ) );

		$result = FreeScout_Client::api( 'conversations/' . self::CONVERSATION_ID, array(), 'GET', $response_code );

		$this->assertSame( array( 'error' => 'Connection timed out' ), $result );
		$this->assertSame( 0, $response_code );
	}

	/**
	 * Adds a filter for the test.
	 *
	 * @param string  $hook     Hook.
	 * @param Closure $callback Callback.
	 * @return void
	 */
	protected function filter( string $hook, Closure $callback ): void {
		$this->filters[] = array( $hook, $callback );
		add_filter( $hook, $callback, 10, 3 );
	}

	/**
	 * Answers FreeScout's API: that a thread was created.
	 *
	 * @return void
	 */
	protected function answer(): void {
		$this->filter(
			'pre_http_request',
			function ( mixed $preempt, array $args, string $url ): mixed {
				if ( ! str_starts_with( $url, 'https://freescout.wordpress.net/api/' ) ) {
					return $preempt;
				}
				$this->requests[] = compact( 'url', 'args' );

				return array( 'response' => array( 'code' => 201 ), 'body' => '{"id":99}' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- A short HTTP response.
			}
		);
	}

	/**
	 * An author's upload to their plugin in review, whose review conversation is in WordPress.org's copy.
	 *
	 * @param bool $freescout Whether the conversation is FreeScout's.
	 * @return array The plugin, the uploaded ZIP, and the author, who is the current user.
	 */
	protected function upload( bool $freescout ): array {
		global $wpdb;

		$author_id                = wp_insert_user(
			array(
				'user_login' => 'upload-author-' . wp_generate_password( 6, false ),
				'user_email' => 'author-' . wp_generate_password( 6, false ) . '@example.org',
				'user_pass'  => wp_generate_password(),
			)
		);
		$this->created['users'][] = $author_id;

		$plugin_id                = wp_insert_post(
			array(
				'post_type'         => 'plugin',
				'post_name'         => 'upload-confirmation-fixture',
				'post_title'        => 'Upload Confirmation Fixture',
				'post_status'       => 'pending',
				'post_author'       => $author_id,
				// Plugin_Directory::filter_wp_insert_post_data() reads it, and fails the insert without it.
				'post_modified'     => current_time( 'mysql' ),
				'post_modified_gmt' => current_time( 'mysql', 1 ),
			)
		);
		$attachment_id            = wp_insert_post(
			array(
				'post_type'         => 'attachment',
				'post_parent'       => $plugin_id,
				'post_status'       => 'inherit',
				'post_title'        => 'upload-confirmation-fixture.zip',
				'post_content'      => '',
				'post_modified'     => current_time( 'mysql' ),
				'post_modified_gmt' => current_time( 'mysql', 1 ),
			)
		);
		$this->created['posts'][] = $attachment_id;
		$this->created['posts'][] = $plugin_id;
		update_post_meta( $attachment_id, 'version', '1.2' );

		$wpdb->insert(
			"{$wpdb->base_prefix}helpscout",
			array(
				'id'      => self::CONVERSATION_ID,
				'number'  => 1234,
				'subject' => '[WordPress Plugin Directory] Review in Progress: Upload Confirmation Fixture',
				'created' => '2026-08-01 10:00:00',
			)
		);
		$wpdb->insert(
			"{$wpdb->base_prefix}helpscout_meta",
			array(
				'helpscout_id' => self::CONVERSATION_ID,
				'meta_key'     => 'plugins', // phpcs:ignore WordPress.DB.SlowDBQuery -- Test fixture.
				'meta_value'   => 'upload-confirmation-fixture', // phpcs:ignore WordPress.DB.SlowDBQuery -- Test fixture.
			)
		);

		if ( $freescout ) {
			$wpdb->insert(
				"{$wpdb->base_prefix}helpscout_meta",
				array(
					'helpscout_id' => self::CONVERSATION_ID,
					'meta_key'     => 'helpdesk', // phpcs:ignore WordPress.DB.SlowDBQuery -- Test fixture.
					'meta_value'   => 'freescout', // phpcs:ignore WordPress.DB.SlowDBQuery -- Test fixture.
				)
			);
		}

		// Only the author's own upload is confirmed.
		wp_set_current_user( $author_id );

		return array( get_post( $plugin_id ), get_post( $attachment_id ), get_userdata( $author_id ) );
	}
}
