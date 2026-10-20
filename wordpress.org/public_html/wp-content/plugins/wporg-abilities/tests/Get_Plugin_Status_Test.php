<?php
/**
 * Tests the review feedback the plugin status ability returns.
 *
 * @package WordPressdotorg\Abilities
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Abilities\Tests;

use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WordPressdotorg\Abilities\Plugins\Plugin_Directory\Tools\Get_Plugin_Status;

/**
 * Tests for `Get_Plugin_Status::execute()`'s feedback, from either helpdesk.
 */
class Get_Plugin_Status_Test extends TestCase {

	/**
	 * The review conversation's ID.
	 *
	 * @var int
	 */
	const CONVERSATION_ID = 4242;

	/**
	 * The plugin's slug.
	 *
	 * @var string
	 */
	const SLUG = 'plugin-status-fixture';

	/**
	 * Filters added during a test, each a hook and its callback, removed again on teardown.
	 *
	 * @var array[]
	 */
	protected array $filters = array();

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
	 * Configures FreeScout.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->filter( 'wporg_plugins_freescout_api_key', static fn(): string => 'test-key' );
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
		$wpdb->delete( "{$wpdb->base_prefix}helpscout", array( 'id' => self::CONVERSATION_ID ) );
		$wpdb->delete( "{$wpdb->base_prefix}helpscout_meta", array( 'helpscout_id' => self::CONVERSATION_ID ) );
		delete_site_transient( 'freescout_threads_' . self::CONVERSATION_ID );
		delete_site_transient( 'helpscout_threads_' . self::CONVERSATION_ID );
		wp_set_current_user( 0 );

		parent::tearDown();
	}

	/**
	 * The helpdesks a review conversation can be in.
	 *
	 * @return array[]
	 */
	public static function data_helpdesks(): array {
		return array(
			'FreeScout' => array( true ),
			'HelpScout' => array( false ),
		);
	}

	/**
	 * The plugin directory confirms an author's upload with a reply in the review conversation, which is the author's
	 * doing: the reviewer's latest feedback is still their review, and the history credits the confirmation to the
	 * author.
	 *
	 * @param bool $freescout Whether the conversation is FreeScout's.
	 * @return void
	 */
	#[DataProvider( 'data_helpdesks' )]
	public function test_upload_confirmation_is_not_reviewer_feedback( bool $freescout ): void {
		$this->answer( $freescout );
		$this->review( $freescout );

		$this->assertSame(
			array(
				array(
					'from' => 'reviewer',
					'date' => '2026-08-01T10:00:00Z',
					'body' => "<feedback>\nPlease fix the issues.\n</feedback>",
				),
			),
			Get_Plugin_Status::execute( array( 'plugin_slug' => self::SLUG ) )['feedback']
		);

		$history = Get_Plugin_Status::execute(
			array(
				'plugin_slug'  => self::SLUG,
				'full_history' => true,
			)
		)['feedback'];
		$this->assertSame( array( 'reviewer', 'author', 'author' ), wp_list_pluck( $history, 'from' ) );
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
	 * Answers the helpdesk's API with the review conversation's threads, newest first: the upload's confirmation, the
	 * author's reply, and the review.
	 *
	 * Replaces the requests `Get_Plugin_Status` sends to FreeScout's API & Webhooks module or to HelpScout's API, which
	 * the confirmation is a reply in, from FreeScout's agent for the plugin directory or HelpScout's API user.
	 *
	 * @param bool $freescout Whether the conversation is FreeScout's.
	 * @return void
	 */
	protected function answer( bool $freescout ): void {
		$threads = array(
			array(
				'type'      => $freescout ? 'message' : 'reply',
				'body'      => 'This is an automated message to confirm that we have received your updated plugin file.<br />' .
					'<br />File updated by plugin-status-author, version 1.2.<br />',
				'createdBy' => array(
					'id'   => 81,
					'type' => 'user',
				),
				'createdAt' => '2026-08-03T10:00:00Z',
			),
			array(
				'type'      => 'customer',
				'body'      => '<p>Fixed, uploading it now.</p>',
				'createdBy' => array(
					'id'   => 91,
					'type' => 'customer',
				),
				'createdAt' => '2026-08-02T10:00:00Z',
			),
			array(
				'type'      => $freescout ? 'message' : 'reply',
				'body'      => '<p>Please fix the issues.</p>',
				'createdBy' => array(
					'id'   => 5,
					'type' => 'user',
				),
				'createdAt' => '2026-08-01T10:00:00Z',
			),
		);

		$this->filter(
			'pre_http_request',
			static function ( mixed $preempt, array $args, string $url ) use ( $freescout, $threads ): mixed {
				if ( $freescout && str_starts_with( $url, 'https://freescout.wordpress.net/api/conversations/' . self::CONVERSATION_ID ) ) {
					$threads = array_map( static fn( array $thread ): array => $thread + array( 'state' => 'published' ), $threads );
					$body    = array(
						'id'        => self::CONVERSATION_ID,
						'_embedded' => array( 'threads' => $threads ),
					);
				} elseif ( ! $freescout && 'https://api.helpscout.net/v2/oauth2/token' === $url ) {
					$body = array(
						'access_token' => 'test-token',
						'expires_in'   => 7200,
					);
				} elseif ( ! $freescout && 'https://api.helpscout.net/v2/conversations/' . self::CONVERSATION_ID . '/threads' === $url ) {
					$body = array( '_embedded' => array( 'threads' => $threads ) );
				} else {
					return $preempt;
				}

				return array( 'response' => array( 'code' => 200 ), 'body' => wp_json_encode( $body ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- A short HTTP response.
			}
		);
	}

	/**
	 * The author's plugin in review, whose review conversation is in WordPress.org's copy.
	 *
	 * @param bool $freescout Whether the conversation is FreeScout's.
	 * @return void
	 */
	protected function review( bool $freescout ): void {
		global $wpdb;

		$author_id                = wp_insert_user(
			array(
				'user_login' => 'plugin-status-author-' . wp_generate_password( 6, false ),
				'user_email' => 'author-' . wp_generate_password( 6, false ) . '@example.org',
				'user_pass'  => wp_generate_password(),
			)
		);
		$this->created['users'][] = $author_id;

		$this->created['posts'][] = wp_insert_post(
			array(
				'post_type'         => 'plugin',
				'post_name'         => self::SLUG,
				'post_title'        => 'Plugin Status Fixture',
				'post_status'       => 'pending',
				'post_author'       => $author_id,
				// Plugin_Directory::filter_wp_insert_post_data() reads it, and fails the insert without it.
				'post_modified'     => current_time( 'mysql' ),
				'post_modified_gmt' => current_time( 'mysql', 1 ),
			)
		);

		$wpdb->insert(
			"{$wpdb->base_prefix}helpscout",
			array(
				'id'      => self::CONVERSATION_ID,
				'number'  => 1234,
				'subject' => '[WordPress Plugin Directory] Review in Progress: Plugin Status Fixture',
				'created' => '2026-08-01 10:00:00',
			)
		);
		$wpdb->insert(
			"{$wpdb->base_prefix}helpscout_meta",
			array(
				'helpscout_id' => self::CONVERSATION_ID,
				'meta_key'     => 'plugins', // phpcs:ignore WordPress.DB.SlowDBQuery -- Test fixture.
				'meta_value'   => self::SLUG, // phpcs:ignore WordPress.DB.SlowDBQuery -- Test fixture.
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

		// The ability only answers for the author's own plugins.
		wp_set_current_user( $author_id );
	}
}
