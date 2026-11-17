<?php
/**
 * Tests the confirmation of an uploaded update, in the review conversation in FreeScout.
 *
 * @package plugin-directory
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
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
	 * Another submitter's review conversation.
	 *
	 * @var int
	 */
	const OTHER_CONVERSATION_ID = 43;

	/**
	 * Filters added during a test, each a hook and its callback, removed again on teardown.
	 *
	 * @var array[]
	 */
	protected array $filters = array();

	/**
	 * Requests sent to FreeScout: URL, and arguments.
	 *
	 * @var array[]
	 */
	protected array $requests = array();

	/**
	 * IDs of the FreeScout conversations a sender started, whatever answer() says of the others.
	 *
	 * @var int[]
	 */
	protected array $sender_started = array();

	/**
	 * IDs of posts and users created during a test.
	 *
	 * @var int[][]
	 */
	protected array $created = array(
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
		foreach ( array( self::CONVERSATION_ID, self::HELPSCOUT_ID, self::OTHER_CONVERSATION_ID ) as $id ) {
			$wpdb->delete( "{$wpdb->base_prefix}helpscout", array( 'id' => $id ) );
			$wpdb->delete( "{$wpdb->base_prefix}helpscout_meta", array( 'helpscout_id' => $id ) );
		}
		delete_site_transient( 'WordPressdotorg\Plugin_Directory\Clients\HelpScoutget_auth_token' );
		wp_set_current_user( 0 );

		parent::tearDown();
	}

	/**
	 * The author's upload is confirmed by a reply from the configured agent, which makes the conversation active again,
	 * and keeps who it copies.
	 *
	 * @return void
	 */
	public function test_replies_in_the_review_conversation(): void {
		$this->answer( array( 'committer@example.org' ) );
		list( $plugin, $attachment ) = $this->upload( true );

		$this->assertTrue( Upload_Handler::update_review_email( $plugin, $attachment ) );

		$this->assertSame( array( 'GET', 'GET', 'POST' ), wp_list_pluck( wp_list_pluck( $this->requests, 'args' ), 'method' ) );
		$this->assertSame( 'https://freescout.wordpress.net/api/conversations/' . self::CONVERSATION_ID . '/threads', $this->requests[2]['url'] );

		$body = json_decode( (string) $this->requests[2]['args']['body'], true );
		$this->assertSame( array( 'message', 81, array( 'committer@example.org' ), 'active' ), array( $body['type'], $body['user'], $body['cc'], $body['status'] ) );
		$this->assertStringContainsString( 'This is an automated message to confirm that we have received your updated plugin file.', $body['text'] );
		$this->assertStringContainsString( 'version 1.2', $body['text'] );
	}

	/**
	 * Without the review conversation, there's no telling who started it, so there's no reply.
	 *
	 * @return void
	 */
	public function test_does_not_reply_without_the_review_conversation(): void {
		$this->answer( null );
		list( $plugin, $attachment ) = $this->upload( true );

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- The warning it logs is expected.
		$this->assertFalse( @Upload_Handler::update_review_email( $plugin, $attachment ) );
		$this->assertSame( array( 'GET' ), wp_list_pluck( wp_list_pluck( $this->requests, 'args' ), 'method' ) );
	}

	/**
	 * No confirmation goes into a conversation a sender started, which can name any plugin and CC anyone.
	 *
	 * @return void
	 */
	public function test_does_not_reply_in_a_conversation_a_sender_started(): void {
		$this->answer( array( 'outsider@example.net' ), 'customer' );
		list( $plugin, $attachment ) = $this->upload( true );

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- The warning it logs is expected.
		$this->assertFalse( @Upload_Handler::update_review_email( $plugin, $attachment ) );
		$this->assertSame( array( 'GET' ), wp_list_pluck( wp_list_pluck( $this->requests, 'args' ), 'method' ) );
	}

	/**
	 * An author who answered the review from another address, which FreeScout then makes the conversation's customer,
	 * still gets the confirmation.
	 *
	 * @return void
	 */
	public function test_replies_when_the_author_answered_from_another_address(): void {
		$this->answer( array(), 'user', 'another-address@example.net' );
		list( $plugin, $attachment ) = $this->upload( true );

		$this->assertTrue( Upload_Handler::update_review_email( $plugin, $attachment ) );
		$this->assertSame( 'https://freescout.wordpress.net/api/conversations/' . self::CONVERSATION_ID . '/threads', $this->requests[2]['url'] );
	}

	/**
	 * An author who answers the review in a new email starts a conversation of their own, under a similar subject. The
	 * confirmation goes into the newest one the plugins team started instead.
	 *
	 * @return void
	 */
	public function test_replies_in_the_newest_conversation_the_team_started(): void {
		global $wpdb;

		$this->answer();
		list( $plugin, $attachment ) = $this->upload( true );

		$this->sender_started = array( self::OTHER_CONVERSATION_ID );
		$wpdb->insert(
			"{$wpdb->base_prefix}helpscout",
			array(
				'id'      => self::OTHER_CONVERSATION_ID,
				'number'  => 1235,
				'user_id' => $plugin->post_author,
				'subject' => 'Re: [WordPress Plugin Directory] Review in Progress: Upload Confirmation Fixture',
				'created' => '2026-09-01 10:00:00',
			)
		);
		$meta = array(
			'plugins'  => 'upload-confirmation-fixture',
			'helpdesk' => 'freescout',
		);
		foreach ( $meta as $key => $value ) {
			$wpdb->insert(
				"{$wpdb->base_prefix}helpscout_meta",
				array(
					'helpscout_id' => self::OTHER_CONVERSATION_ID,
					'meta_key'     => $key, // phpcs:ignore WordPress.DB.SlowDBQuery -- Test fixture.
					'meta_value'   => $value, // phpcs:ignore WordPress.DB.SlowDBQuery -- Test fixture.
				)
			);
		}

		$this->assertTrue( Upload_Handler::update_review_email( $plugin, $attachment ) );

		$this->assertStringStartsWith( 'https://freescout.wordpress.net/api/conversations/' . self::OTHER_CONVERSATION_ID, $this->requests[0]['url'] );
		$this->assertSame( 'https://freescout.wordpress.net/api/conversations/' . self::CONVERSATION_ID . '/threads', end( $this->requests )['url'] );
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
				'user_id' => $plugin->post_author,
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
		$this->assertSame( 'https://freescout.wordpress.net/api/conversations/' . self::CONVERSATION_ID . '/threads', $this->requests[2]['url'] );
	}

	/**
	 * Another submitter's newer review conversation that mentions the plugin isn't the plugin's review conversation.
	 *
	 * @return void
	 */
	public function test_replies_only_in_the_authors_review_conversation(): void {
		global $wpdb;

		$this->answer();
		list( $plugin, $attachment ) = $this->upload( true );

		$other_id                 = wp_insert_user(
			array(
				'user_login' => 'other-submitter-' . wp_generate_password( 6, false ),
				'user_email' => 'other-' . wp_generate_password( 6, false ) . '@example.org',
				'user_pass'  => wp_generate_password(),
			)
		);
		$this->created['users'][] = $other_id;

		$wpdb->insert(
			"{$wpdb->base_prefix}helpscout",
			array(
				'id'      => self::OTHER_CONVERSATION_ID,
				'number'  => 1235,
				'user_id' => $other_id,
				'subject' => '[WordPress Plugin Directory] Review in Progress: Another Plugin',
				'created' => '2026-09-01 10:00:00',
			)
		);
		$meta = array(
			'plugins'  => 'upload-confirmation-fixture',
			'helpdesk' => 'freescout',
		);
		foreach ( $meta as $key => $value ) {
			$wpdb->insert(
				"{$wpdb->base_prefix}helpscout_meta",
				array(
					'helpscout_id' => self::OTHER_CONVERSATION_ID,
					'meta_key'     => $key, // phpcs:ignore WordPress.DB.SlowDBQuery -- Test fixture.
					'meta_value'   => $value, // phpcs:ignore WordPress.DB.SlowDBQuery -- Test fixture.
				)
			);
		}

		$this->assertSame( self::CONVERSATION_ID, (int) Upload_Handler::find_review_email( $plugin )->id );

		$this->assertTrue( Upload_Handler::update_review_email( $plugin, $attachment ) );
		$this->assertSame( 'https://freescout.wordpress.net/api/conversations/' . self::CONVERSATION_ID . '/threads', $this->requests[2]['url'] );
	}

	/**
	 * A review conversation still in HelpScout is replied to there, even once the plugins team is on FreeScout.
	 *
	 * @return void
	 */
	public function test_leaves_helpscouts_conversations_to_helpscout(): void {
		$this->answer();
		$this->answer_helpscout();
		list( $plugin, $attachment ) = $this->upload( false );

		$this->assertTrue( Upload_Handler::update_review_email( $plugin, $attachment ) );

		$this->assertSame( array( 'GET', 'POST' ), wp_list_pluck( wp_list_pluck( $this->requests, 'args' ), 'method' ) );
		$this->assertSame( 'https://api.helpscout.net/v2/conversations/' . self::CONVERSATION_ID . '/reply', $this->requests[1]['url'] );
	}

	/**
	 * No confirmation goes into a HelpScout conversation a sender started, though its subject names the author.
	 *
	 * @return void
	 */
	public function test_does_not_reply_in_a_helpscout_conversation_a_sender_started(): void {
		$this->answer_helpscout( 'customer' );
		list( $plugin, $attachment ) = $this->upload( false );

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- The warning it logs is expected.
		$this->assertFalse( @Upload_Handler::update_review_email( $plugin, $attachment ) );
		$this->assertSame( array( 'GET' ), wp_list_pluck( wp_list_pluck( $this->requests, 'args' ), 'method' ) );
	}

	/**
	 * No confirmation goes into a HelpScout conversation the team started with someone else, though WordPress.org's
	 * copy names the author from its subject.
	 *
	 * @return void
	 */
	public function test_does_not_reply_in_helpscout_when_the_customer_is_not_the_author(): void {
		$this->answer_helpscout( 'user', 'someone-else@example.net' );
		list( $plugin, $attachment ) = $this->upload( false );

		$this->assertFalse( Upload_Handler::update_review_email( $plugin, $attachment ) );
		$this->assertSame( array( 'GET' ), wp_list_pluck( wp_list_pluck( $this->requests, 'args' ), 'method' ) );
	}

	/**
	 * Answers HelpScout's API: the review conversation, and that the reply was created.
	 *
	 * @param string      $created_by Who started the conversation: `user`, an agent, or `customer`, a sender.
	 * @param string|null $customer   The conversation customer's email; null for the current user's.
	 * @return void
	 */
	protected function answer_helpscout( string $created_by = 'user', ?string $customer = null ): void {
		if ( ! defined( 'HELPSCOUT_APP_ID' ) ) {
			define( 'HELPSCOUT_APP_ID', 'test' );
			define( 'HELPSCOUT_APP_SECRET', 'test' );
		}
		set_site_transient(
			'WordPressdotorg\Plugin_Directory\Clients\HelpScoutget_auth_token',
			array(
				'exp'   => time() + HOUR_IN_SECONDS,
				'token' => 'test',
			)
		);

		$this->filter(
			'pre_http_request',
			function ( mixed $preempt, array $args, string $url ) use ( $created_by, $customer ): mixed {
				if ( ! str_starts_with( $url, 'https://api.helpscout.net/' ) ) {
					return $preempt;
				}
				$this->requests[] = compact( 'url', 'args' );

				if ( 'GET' === $args['method'] ) {
					$conversation = array(
						'id'              => self::CONVERSATION_ID,
						'createdBy'       => array( 'type' => $created_by ),
						'primaryCustomer' => array( 'email' => $customer ?? wp_get_current_user()->user_email ),
					);

					return array( 'response' => array( 'code' => 200 ), 'body' => wp_json_encode( $conversation ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- A short HTTP response.
				}

				return array( 'response' => array( 'code' => 201 ), 'body' => '' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- A short HTTP response.
			}
		);
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
	 * Answers FreeScout's API, in place of the API & Webhooks module: the review conversation, and that a thread was
	 * created.
	 *
	 * @param string[]|null $cc         Who the conversation copies; null if FreeScout can't be reached.
	 * @param string        $created_by Who started the conversation: `user`, an agent, or `customer`, a sender.
	 * @param string|null   $customer   The conversation customer's email; null for the current user's.
	 * @return void
	 */
	protected function answer( ?array $cc = array(), string $created_by = 'user', ?string $customer = null ): void {
		$this->filter(
			'pre_http_request',
			function ( mixed $preempt, array $args, string $url ) use ( $cc, $created_by, $customer ): mixed {
				if ( ! str_starts_with( $url, 'https://freescout.wordpress.net/api/' ) ) {
					return $preempt;
				}
				$this->requests[] = compact( 'url', 'args' );

				if ( 'GET' === $args['method'] ) {
					if ( null === $cc ) {
						return new WP_Error( 'http_request_failed', 'Connection timed out' );
					}

					$id           = (int) preg_replace( '!^.*/conversations/(\d+).*$!', '$1', $url );
					$conversation = array(
						'id'        => $id,
						'number'    => 1234,
						'status'    => 'closed',
						'createdBy' => array(
							'id'   => 7,
							'type' => in_array( $id, $this->sender_started, true ) ? 'customer' : $created_by,
						),
						'customer'  => array( 'email' => $customer ?? wp_get_current_user()->user_email ),
						'cc'        => $cc,
						'bcc'       => array(),
					);

					return array( 'response' => array( 'code' => 200 ), 'body' => wp_json_encode( $conversation ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- A short HTTP response.
				}

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
				'user_id' => $author_id,
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
