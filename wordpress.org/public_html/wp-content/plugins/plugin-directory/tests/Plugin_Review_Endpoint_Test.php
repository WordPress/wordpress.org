<?php
/**
 * Tests for the plugin review REST endpoints which change a plugin.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\Admin\Status_Transitions;
use WordPressdotorg\Plugin_Directory\Tools\Helpscout;

/**
 * Tests the full REST chain for the review endpoints — the shared secret, the
 * per-plugin token, the capabilities of the user performing the action, the
 * statuses each route accepts, and the changes they make.
 *
 * @group api
 */
#[Group( 'api' )]
class Plugin_Review_Endpoint_Test extends TestCase {

	/**
	 * The shared secret the endpoints are configured with.
	 */
	private const SECRET = 'test-review-endpoint-secret';

	/**
	 * Counter to give every fixture a unique slug and login.
	 *
	 * @var int
	 */
	private static int $fixture_count = 0;

	/**
	 * The plugin post under test, created in the 'new' status.
	 *
	 * @var \WP_Post
	 */
	private \WP_Post $plugin;

	/**
	 * A user with no review capabilities.
	 *
	 * @var int
	 */
	private int $subscriber_id;

	/**
	 * A user who can review plugins but not approve them.
	 *
	 * @var int
	 */
	private int $reviewer_id;

	/**
	 * A user who can both review and approve plugins.
	 *
	 * @var int
	 */
	private int $admin_id;

	/**
	 * Post IDs created by the running test, removed on tear down.
	 *
	 * @var int[]
	 */
	private array $post_ids = array();

	/**
	 * User IDs created by the running test, removed on tear down.
	 *
	 * @var int[]
	 */
	private array $user_ids = array();

	/**
	 * Create the users and the plugin fixture, and a fresh REST server.
	 */
	protected function setUp(): void {
		parent::setUp();

		wp_cache_flush();

		// Tools::audit_log() reads it unguarded.
		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

		if ( ! defined( 'PLUGIN_REVIEW_ENDPOINT_SECRET' ) ) {
			define( 'PLUGIN_REVIEW_ENDPOINT_SECRET', self::SECRET );
		}

		// A fresh server per test; creating it fires rest_api_init, which registers the routes.
		global $wp_rest_server;
		$wp_rest_server = null;
		rest_get_server();

		/*
		 * Renaming a plugin mirrors the slug into the HelpScout tables, which live
		 * outside WordPress and are not stubbed for the suite.
		 */
		remove_action( 'post_updated', array( Helpscout::class, 'post_updated' ), 10 );

		$this->subscriber_id = $this->create_user( 'subscriber' );
		$this->reviewer_id   = $this->create_user( 'reviewer', array( 'plugin_review' ) );
		$this->admin_id      = $this->create_user( 'admin', array( 'plugin_review', 'plugin_approve' ) );

		$this->plugin = $this->create_plugin( 'new' );
	}

	/**
	 * Remove every fixture, and the hooks the endpoints registered.
	 */
	protected function tearDown(): void {
		global $wpdb;

		/*
		 * The endpoints hook the transition actions for the request they run in. Left
		 * registered they would fire for every later test in the suite.
		 */
		$transitions = Status_Transitions::instance();
		remove_action( 'transition_post_status', array( $transitions, 'transition_post_status' ), 11 );
		remove_action( 'post_updated', array( $transitions, 'record_owner_change' ), 11 );

		add_action( 'post_updated', array( Helpscout::class, 'post_updated' ), 10, 3 );

		wp_set_current_user( 0 );

		foreach ( $this->post_ids as $post_id ) {
			$slug = get_post_field( 'post_name', $post_id );

			$wpdb->delete( PLUGINS_TABLE_PREFIX . 'svn_access', array( 'path' => '/' . $slug ) );
			$wpdb->delete( $wpdb->prefix . 'update_source', array( 'plugin_id' => $post_id ) );
			wp_cache_delete( $slug, 'plugin-committers' );
			wp_cache_delete( $slug, 'plugin-slugs' );

			wp_delete_post( $post_id, true );
		}
		$this->post_ids = array();

		foreach ( $this->user_ids as $user_id ) {
			wp_delete_user( $user_id );
		}
		$this->user_ids = array();

		parent::tearDown();
	}

	/**
	 * Insert a plugin post in the given status.
	 *
	 * `Plugin_Directory::filter_wp_insert_post_data()` dereferences `post_modified`
	 * unconditionally, so it is supplied explicitly or the insert is rejected.
	 *
	 * @param string $post_status Status to create the plugin in.
	 * @param string $post_type   Optional. Post type. Default 'plugin'.
	 * @return \WP_Post The created post.
	 */
	private function create_plugin( string $post_status, string $post_type = 'plugin' ): \WP_Post {
		$post_id = wp_insert_post(
			array(
				'post_type'         => $post_type,
				'post_title'        => 'Review Endpoint Fixture',
				'post_name'         => 'review-endpoint-' . ( ++self::$fixture_count ),
				'post_status'       => $post_status,
				'post_author'       => $this->admin_id,
				'post_modified'     => current_time( 'mysql' ),
				'post_modified_gmt' => current_time( 'mysql', 1 ),
			),
			true
		);

		$this->assertNotInstanceOf( \WP_Error::class, $post_id, "The '{$post_status}' fixture could not be created." );

		$this->post_ids[] = (int) $post_id;

		return get_post( (int) $post_id );
	}

	/**
	 * Create a user holding the given capabilities.
	 *
	 * The plugin roles are added on activation, which does not run for the test
	 * suite, so the capabilities are granted to the account directly.
	 *
	 * @param string   $prefix Prefix for the generated login.
	 * @param string[] $caps   Optional. Capabilities to grant.
	 * @return int User ID.
	 */
	private function create_user( string $prefix, array $caps = array() ): int {
		$login = $prefix . '-' . ( ++self::$fixture_count );

		$user_id = wp_insert_user(
			array(
				'user_login' => $login,
				'user_pass'  => wp_generate_password( 24 ),
				'user_email' => $login . '@example.invalid',
				'role'       => 'subscriber',
			)
		);

		$this->assertNotInstanceOf( \WP_Error::class, $user_id, "Could not create the user '{$login}'." );

		$user = new \WP_User( (int) $user_id );
		foreach ( $caps as $cap ) {
			$user->add_cap( $cap );
		}

		$this->user_ids[] = (int) $user_id;

		return (int) $user_id;
	}

	/**
	 * Dispatch a request to one of the review endpoints.
	 *
	 * @param string      $action    The route action: 'assign', 'approve' or 'slug'.
	 * @param array       $body      Optional. The request parameters.
	 * @param string|null $bearer    Optional. The bearer token; null for the shared secret, '' to omit the header.
	 * @param string|null $token     Optional. The per-plugin token; null for the fixture plugin's.
	 * @param int|null    $plugin_id Optional. The routed plugin; null for the fixture plugin.
	 * @return \WP_REST_Response The response.
	 */
	private function dispatch( string $action, array $body = array(), ?string $bearer = null, ?string $token = null, ?int $plugin_id = null ): \WP_REST_Response {
		$plugin_id = $plugin_id ?? $this->plugin->ID;
		$token     = $token ?? wp_hash( $plugin_id, 'plugin-review' );
		$bearer    = $bearer ?? PLUGIN_REVIEW_ENDPOINT_SECRET;

		$request = new \WP_REST_Request( 'POST', "/plugins/v1/plugin-review/{$plugin_id}-{$token}/{$action}" );

		if ( '' !== $bearer ) {
			$request->set_header( 'Authorization', 'Bearer ' . $bearer );
		}

		foreach ( $body as $key => $value ) {
			$request->set_param( $key, $value );
		}

		return rest_do_request( $request );
	}

	/**
	 * Fetch the audit log entries recorded against a plugin, oldest first.
	 *
	 * @param int $post_id The plugin.
	 * @return \WP_Comment[] The internal notes.
	 */
	private function audit_log( int $post_id ): array {
		return get_comments(
			array(
				'post_id' => $post_id,
				'type'    => 'internal-note',
				'order'   => 'ASC',
			)
		);
	}

	/**
	 * Assert that a rejected request left the plugin exactly as it was.
	 *
	 * @param \WP_Post $plugin The plugin as it was before the request.
	 * @return void
	 */
	private function assertPluginUnchanged( \WP_Post $plugin ): void {
		$current = get_post( $plugin->ID );

		$this->assertSame( $plugin->post_status, $current->post_status, 'The plugin status changed.' );
		$this->assertSame( $plugin->post_name, $current->post_name, 'The plugin slug changed.' );
		$this->assertEmpty( get_post_meta( $plugin->ID, 'assigned_reviewer', true ), 'A reviewer was assigned.' );
		$this->assertCount( 0, $this->audit_log( $plugin->ID ), 'The audit log was written to.' );
	}

	/*
	 * The shared secret and the per-plugin token.
	 */

	/**
	 * A request without the bearer header is rejected before anything happens.
	 */
	public function test_missing_bearer_is_rejected(): void {
		$response = $this->dispatch( 'assign', array( 'user_id' => $this->admin_id ), '' );

		$this->assertSame( 401, $response->get_status() );
		$this->assertSame( 'not_authorized', $response->get_data()['code'] );
		$this->assertPluginUnchanged( $this->plugin );
	}

	/**
	 * A request with the wrong shared secret is rejected before anything happens.
	 */
	public function test_wrong_bearer_is_rejected(): void {
		$response = $this->dispatch( 'assign', array( 'user_id' => $this->admin_id ), 'not-the-shared-secret' );

		$this->assertSame( 401, $response->get_status() );
		$this->assertSame( 'not_authorized', $response->get_data()['code'] );
		$this->assertPluginUnchanged( $this->plugin );
	}

	/**
	 * A valid secret does not stand in for the per-plugin token.
	 */
	public function test_wrong_token_is_rejected(): void {
		$response = $this->dispatch( 'assign', array( 'user_id' => $this->admin_id ), null, str_repeat( '0', 32 ) );

		$this->assertSame( 401, $response->get_status() );
		$this->assertPluginUnchanged( $this->plugin );
	}

	/**
	 * A token issued for one plugin does not authorise a change to another.
	 */
	public function test_token_for_another_plugin_is_rejected(): void {
		$other = $this->create_plugin( 'new' );

		$response = $this->dispatch(
			'assign',
			array( 'user_id' => $this->admin_id ),
			null,
			wp_hash( $other->ID, 'plugin-review' )
		);

		$this->assertSame( 401, $response->get_status() );
		$this->assertPluginUnchanged( $this->plugin );
	}

	/**
	 * Every changing route is behind the shared secret.
	 */
	public function test_every_route_requires_the_secret(): void {
		foreach ( array( 'assign', 'approve', 'slug' ) as $action ) {
			$response = $this->dispatch(
				$action,
				array(
					'user_id' => $this->admin_id,
					'slug'    => 'a-brand-new-slug',
				),
				''
			);

			$this->assertSame( 401, $response->get_status(), "The '{$action}' route was reachable without the secret." );
		}

		$this->assertPluginUnchanged( $this->plugin );
	}

	/*
	 * The user performing the action.
	 */

	/**
	 * The user performing the action has to be named.
	 */
	public function test_missing_user_id_is_rejected(): void {
		$response = $this->dispatch( 'assign' );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rest_missing_callback_param', $response->get_data()['code'] );
		$this->assertPluginUnchanged( $this->plugin );
	}

	/**
	 * A user ID below the schema minimum is rejected by the route.
	 */
	public function test_invalid_user_id_is_rejected(): void {
		$response = $this->dispatch( 'assign', array( 'user_id' => 0 ) );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rest_invalid_param', $response->get_data()['code'] );
		$this->assertPluginUnchanged( $this->plugin );
	}

	/**
	 * A user ID that belongs to nobody is rejected.
	 */
	public function test_unknown_user_is_rejected(): void {
		$response = $this->dispatch( 'assign', array( 'user_id' => PHP_INT_MAX ) );

		$this->assertSame( 404, $response->get_status() );
		$this->assertSame( 'user_not_found', $response->get_data()['code'] );
		$this->assertPluginUnchanged( $this->plugin );
	}

	/**
	 * Assigning a review needs the review capability.
	 */
	public function test_assign_requires_the_review_capability(): void {
		$response = $this->dispatch( 'assign', array( 'user_id' => $this->subscriber_id ) );

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'user_cannot_do_that', $response->get_data()['code'] );
		$this->assertPluginUnchanged( $this->plugin );
	}

	/**
	 * Approving needs more than the review capability.
	 */
	public function test_approve_requires_the_approve_capability(): void {
		$plugin = $this->create_plugin( 'pending' );

		$response = $this->dispatch( 'approve', array( 'user_id' => $this->reviewer_id ), null, null, $plugin->ID );

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'user_cannot_do_that', $response->get_data()['code'] );
		$this->assertPluginUnchanged( $plugin );
	}

	/**
	 * Renaming needs more than the review capability.
	 *
	 * `wp_insert_post()` drops the slug of a pending post for a user who cannot
	 * publish it, so a reviewer's rename would otherwise be silently discarded.
	 */
	public function test_slug_change_requires_the_approve_capability(): void {
		$plugin = $this->create_plugin( 'pending' );

		$response = $this->dispatch(
			'slug',
			array(
				'user_id' => $this->reviewer_id,
				'slug'    => 'a-brand-new-slug',
			),
			null,
			null,
			$plugin->ID
		);

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'user_cannot_do_that', $response->get_data()['code'] );
		$this->assertPluginUnchanged( $plugin );
	}

	/*
	 * Assigning a reviewer.
	 */

	/**
	 * Assigning sets the reviewer, moves the plugin to pending, and runs the
	 * status transition actions the admin screens run.
	 */
	public function test_assign_sets_the_reviewer_and_pends_the_plugin(): void {
		$response = $this->dispatch( 'assign', array( 'user_id' => $this->reviewer_id ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data() );

		$this->assertSame( 'pending', get_post( $this->plugin->ID )->post_status );
		$this->assertSame( (string) $this->reviewer_id, get_post_meta( $this->plugin->ID, 'assigned_reviewer', true ) );
		$this->assertNotEmpty( get_post_meta( $this->plugin->ID, 'assigned_reviewer_time', true ) );

		// Recorded by Status_Transitions, so it also proves the transition actions ran.
		$this->assertNotEmpty( get_post_meta( $this->plugin->ID, '_pending', true ) );
	}

	/**
	 * The assignment is attributed to the user named in the request.
	 */
	public function test_assign_is_recorded_against_the_reviewer(): void {
		$this->dispatch( 'assign', array( 'user_id' => $this->reviewer_id ) );

		$notes = $this->audit_log( $this->plugin->ID );

		$this->assertCount( 1, $notes );
		$this->assertSame( $this->reviewer_id, (int) $notes[0]->user_id );
		$this->assertStringContainsString( 'Assigned to', $notes[0]->comment_content );
	}

	/**
	 * Only a new submission can be picked up for review.
	 */
	public function test_assign_only_accepts_a_new_plugin(): void {
		foreach ( array( 'pending', 'approved', 'publish', 'rejected' ) as $status ) {
			$plugin = $this->create_plugin( $status );

			$response = $this->dispatch( 'assign', array( 'user_id' => $this->reviewer_id ), null, null, $plugin->ID );

			$this->assertSame( 400, $response->get_status(), "A '{$status}' plugin was assigned." );
			$this->assertSame( 'invalid_status', $response->get_data()['code'] );
			$this->assertSame( $status, get_post( $plugin->ID )->post_status );
		}
	}

	/*
	 * Approving a plugin.
	 */

	/**
	 * Approving moves the plugin to approved and runs the approval actions.
	 */
	public function test_approve_approves_the_plugin(): void {
		$plugin = $this->create_plugin( 'pending' );
		update_post_meta( $plugin->ID, 'assigned_reviewer', $this->admin_id );

		$response = $this->dispatch( 'approve', array( 'user_id' => $this->admin_id ), null, null, $plugin->ID );

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data() );

		$this->assertSame( 'approved', get_post( $plugin->ID )->post_status );
		$this->assertNotEmpty( get_post_meta( $plugin->ID, '_approved', true ) );

		// Status_Transitions unassigns the reviewer once a plugin leaves review.
		$this->assertEmpty( get_post_meta( $plugin->ID, 'assigned_reviewer', true ) );
	}

	/**
	 * Approving grants the author commit access and records the approval.
	 */
	public function test_approve_grants_commit_access_and_is_logged(): void {
		global $wpdb;

		$plugin = $this->create_plugin( 'pending' );

		$this->dispatch( 'approve', array( 'user_id' => $this->admin_id ), null, null, $plugin->ID );

		$committer = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT user FROM %i WHERE path = %s',
				PLUGINS_TABLE_PREFIX . 'svn_access',
				'/' . $plugin->post_name
			)
		);
		$this->assertSame( get_userdata( $this->admin_id )->user_login, $committer );

		$notes = array_map(
			static fn ( $note ) => $note->comment_content,
			$this->audit_log( $plugin->ID )
		);
		$this->assertContains( 'Plugin approved.', $notes );
	}

	/**
	 * A plugin can only be approved from a status the directory allows it from.
	 */
	public function test_approve_only_accepts_a_reviewable_plugin(): void {
		foreach ( array( 'approved', 'publish', 'closed', 'disabled' ) as $status ) {
			$plugin = $this->create_plugin( $status );

			$response = $this->dispatch( 'approve', array( 'user_id' => $this->admin_id ), null, null, $plugin->ID );

			$this->assertSame( 400, $response->get_status(), "A '{$status}' plugin was approved." );
			$this->assertSame( 'invalid_status', $response->get_data()['code'] );
			$this->assertSame( $status, get_post( $plugin->ID )->post_status );
		}
	}

	/**
	 * A new submission can be approved without being picked up first.
	 */
	public function test_approve_accepts_a_new_plugin(): void {
		$response = $this->dispatch( 'approve', array( 'user_id' => $this->admin_id ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'approved', get_post( $this->plugin->ID )->post_status );
	}

	/*
	 * Changing the slug.
	 */

	/**
	 * A rename changes the slug and is recorded against the user who made it.
	 */
	public function test_slug_change_renames_the_plugin(): void {
		$plugin = $this->create_plugin( 'pending' );

		$response = $this->dispatch(
			'slug',
			array(
				'user_id' => $this->admin_id,
				'slug'    => 'a-brand-new-slug',
			),
			null,
			null,
			$plugin->ID
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame(
			array(
				'slug'           => 'a-brand-new-slug',
				'requested_slug' => 'a-brand-new-slug',
			),
			$response->get_data()
		);

		$this->assertSame( 'a-brand-new-slug', get_post( $plugin->ID )->post_name );
		$this->assertSame( 'pending', get_post( $plugin->ID )->post_status );

		$notes = $this->audit_log( $plugin->ID );
		$this->assertCount( 1, $notes );
		$this->assertSame( $this->admin_id, (int) $notes[0]->user_id );
		$this->assertStringContainsString( "Slug changed from '{$plugin->post_name}' to 'a-brand-new-slug'.", $notes[0]->comment_content );
	}

	/**
	 * A rename which lands on a different slug than the one requested is a success
	 * reporting the slug the plugin got, not a failure.
	 *
	 * `wp_insert_post()` suffixes a slug which was taken between the availability check
	 * and the update. That race can't be staged in a single threaded test, so the slug is
	 * marked as unavailable on the filter core offers for it, and core suffixes it.
	 */
	public function test_slug_change_reports_the_slug_the_plugin_got(): void {
		// The uniqueness check is skipped for pending posts, so the plugin is a new one.
		$plugin = $this->create_plugin( 'new' );

		$slug_is_taken = static function ( bool $is_bad, string $slug ): bool {
			return 'a-contested-slug' === $slug ? true : $is_bad;
		};

		add_filter( 'wp_unique_post_slug_is_bad_flat_slug', $slug_is_taken, 10, 2 );

		try {
			$response = $this->dispatch(
				'slug',
				array(
					'user_id' => $this->admin_id,
					'slug'    => 'a-contested-slug',
				),
				null,
				null,
				$plugin->ID
			);
		} finally {
			remove_filter( 'wp_unique_post_slug_is_bad_flat_slug', $slug_is_taken, 10 );
		}

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame(
			array(
				'slug'           => 'a-contested-slug-2',
				'requested_slug' => 'a-contested-slug',
			),
			$response->get_data()
		);

		$this->assertSame( 'a-contested-slug-2', get_post( $plugin->ID )->post_name );

		$notes = $this->audit_log( $plugin->ID );
		$this->assertCount( 1, $notes );
		$this->assertStringContainsString( "Slug changed from '{$plugin->post_name}' to 'a-contested-slug-2'.", $notes[0]->comment_content );
		$this->assertStringContainsString( "The requested slug, 'a-contested-slug', was not available.", $notes[0]->comment_content );
	}

	/**
	 * A reviewer's rename does not spend the author's own one-time slug change.
	 */
	public function test_slug_change_leaves_the_authors_rename_available(): void {
		$plugin = $this->create_plugin( 'new' );

		$this->dispatch(
			'slug',
			array(
				'user_id' => $this->admin_id,
				'slug'    => 'another-brand-new-slug',
			),
			null,
			null,
			$plugin->ID
		);

		$this->assertEmpty( get_post_meta( $plugin->ID, '_wporg_plugin_original_slug', true ) );
	}

	/**
	 * Slugs which are unusable, taken, or reserved are refused, each with its
	 * own error, and none of them touch the plugin.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function data_unavailable_slugs(): array {
		return array(
			'not a slug'      => array( 'Not A Slug!', 'invalid_slug' ),
			'uppercase'       => array( 'Uppercase-Slug', 'invalid_slug' ),
			'too short'       => array( 'abcd', 'too_short' ),
			'reserved name'   => array( 'wordpress', 'reserved_slug' ),
			'reserved plugin' => array( 'yoast-seo', 'reserved_slug' ),
		);
	}

	/**
	 * An unavailable slug is refused with its own error, and changes nothing.
	 *
	 * @param string $slug          The requested slug.
	 * @param string $expected_code The error the endpoint should return.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'data_unavailable_slugs' )]
	public function test_slug_change_refuses_unavailable_slugs( string $slug, string $expected_code ): void {
		$plugin = $this->create_plugin( 'pending' );

		$response = $this->dispatch(
			'slug',
			array(
				'user_id' => $this->admin_id,
				'slug'    => $slug,
			),
			null,
			null,
			$plugin->ID
		);

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( $expected_code, $response->get_data()['code'] );
		$this->assertSame( $plugin->post_name, get_post( $plugin->ID )->post_name );
		$this->assertCount( 0, $this->audit_log( $plugin->ID ) );
	}

	/**
	 * A slug another plugin already holds is refused.
	 */
	public function test_slug_change_refuses_a_slug_in_use(): void {
		$taken  = $this->create_plugin( 'publish' );
		$plugin = $this->create_plugin( 'pending' );

		$response = $this->dispatch(
			'slug',
			array(
				'user_id' => $this->admin_id,
				'slug'    => $taken->post_name,
			),
			null,
			null,
			$plugin->ID
		);

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'slug_in_use', $response->get_data()['code'] );
		$this->assertSame( $plugin->post_name, get_post( $plugin->ID )->post_name );
	}

	/**
	 * Renaming a plugin to the slug it already has is refused.
	 */
	public function test_slug_change_refuses_the_current_slug(): void {
		$plugin = $this->create_plugin( 'pending' );

		$response = $this->dispatch(
			'slug',
			array(
				'user_id' => $this->admin_id,
				'slug'    => $plugin->post_name,
			),
			null,
			null,
			$plugin->ID
		);

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'slug_unchanged', $response->get_data()['code'] );
	}

	/**
	 * The slug has to be supplied.
	 */
	public function test_slug_change_requires_a_slug(): void {
		$response = $this->dispatch( 'slug', array( 'user_id' => $this->admin_id ) );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rest_missing_callback_param', $response->get_data()['code'] );
		$this->assertPluginUnchanged( $this->plugin );
	}

	/**
	 * Once a plugin is approved it owns a SVN repository, so the endpoint stops
	 * renaming it.
	 */
	public function test_slug_change_only_accepts_a_plugin_awaiting_review(): void {
		foreach ( array( 'approved', 'publish', 'closed', 'disabled', 'rejected' ) as $status ) {
			$plugin = $this->create_plugin( $status );

			$response = $this->dispatch(
				'slug',
				array(
					'user_id' => $this->admin_id,
					'slug'    => 'a-brand-new-slug',
				),
				null,
				null,
				$plugin->ID
			);

			$this->assertSame( 400, $response->get_status(), "A '{$status}' plugin was renamed." );
			$this->assertSame( 'invalid_status', $response->get_data()['code'] );
			$this->assertSame( $plugin->post_name, get_post( $plugin->ID )->post_name );
		}
	}

	/*
	 * Routing.
	 */

	/**
	 * A token that resolves to something which is not a plugin is refused.
	 */
	public function test_a_post_that_is_not_a_plugin_is_refused(): void {
		$post = $this->create_plugin( 'publish', 'post' );

		$response = $this->dispatch( 'assign', array( 'user_id' => $this->admin_id ), null, null, $post->ID );

		$this->assertSame( 404, $response->get_status() );
		$this->assertSame( 'plugin_not_found', $response->get_data()['code'] );
	}

	/**
	 * The changing routes are POST only.
	 */
	public function test_the_routes_do_not_answer_get(): void {
		$token = wp_hash( $this->plugin->ID, 'plugin-review' );

		foreach ( array( 'assign', 'approve', 'slug' ) as $action ) {
			$request = new \WP_REST_Request( 'GET', "/plugins/v1/plugin-review/{$this->plugin->ID}-{$token}/{$action}" );
			$request->set_header( 'Authorization', 'Bearer ' . PLUGIN_REVIEW_ENDPOINT_SECRET );

			$response = rest_do_request( $request );

			$this->assertSame( 404, $response->get_status(), "The '{$action}' route answered GET." );
		}

		$this->assertPluginUnchanged( $this->plugin );
	}
}
