<?php
/**
 * Tests that the `plugin` post type's `read_post` capability keeps non-public
 * submissions out of the single-item REST route.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;

/**
 * Covers single-item REST read permission for the `plugin` post type.
 *
 * @group capabilities
 */
class Plugin_Read_Permission_Test extends TestCase {

	/**
	 * Slug of the plugin under test.
	 *
	 * @var string
	 */
	const PLUGIN_SLUG = 'read-permission-fixture';

	/**
	 * REST controller for the `plugin` post type.
	 *
	 * @var WP_REST_Posts_Controller
	 */
	protected WP_REST_Posts_Controller $controller;

	/**
	 * Plugin post IDs created by the running test, removed on tear down.
	 *
	 * @var int[]
	 */
	protected array $plugin_ids = array();

	/**
	 * User IDs created by the running test, removed on tear down.
	 *
	 * @var int[]
	 */
	protected array $user_ids = array();

	/**
	 * Prepares the REST controller shared by each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->controller = new WP_REST_Posts_Controller( 'plugin' );
	}

	/**
	 * Removes every fixture the test created.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		global $wpdb;

		wp_set_current_user( 0 );

		foreach ( $this->user_ids as $user_id ) {
			wp_delete_user( $user_id );
		}
		$this->user_ids = array();

		foreach ( $this->plugin_ids as $plugin_id ) {
			wp_delete_post( $plugin_id, true );
		}
		$this->plugin_ids = array();

		$wpdb->delete(
			PLUGINS_TABLE_PREFIX . 'svn_access',
			array( 'path' => '/' . self::PLUGIN_SLUG )
		);
		wp_cache_delete( self::PLUGIN_SLUG, 'plugin-committers' );

		parent::tearDown();
	}

	/**
	 * Inserts a plugin post in the given status.
	 *
	 * `Plugin_Directory::filter_wp_insert_post_data()` dereferences `post_modified`
	 * unconditionally, so it is supplied explicitly or the insert is rejected.
	 *
	 * @param string $post_status Status to create the plugin in.
	 * @param int    $author_id   Optional. Post author. Default 0.
	 * @return WP_Post The created plugin post.
	 */
	protected function create_plugin( string $post_status, int $author_id = 0 ): WP_Post {
		$plugin_id = wp_insert_post(
			array(
				'post_type'         => 'plugin',
				'post_title'        => 'Read Permission Fixture',
				'post_name'         => self::PLUGIN_SLUG,
				'post_status'       => $post_status,
				'post_author'       => $author_id,
				'post_modified'     => current_time( 'mysql' ),
				'post_modified_gmt' => current_time( 'mysql', 1 ),
			),
			true
		);

		$this->assertNotInstanceOf( WP_Error::class, $plugin_id, "The '{$post_status}' plugin fixture could not be created." );

		$this->plugin_ids[] = (int) $plugin_id;

		return get_post( (int) $plugin_id );
	}

	/**
	 * Creates a subscriber and registers it for removal on tear down.
	 *
	 * @param string $user_login Login for the new account.
	 * @return int User ID.
	 */
	protected function create_user( string $user_login ): int {
		$user_id = wp_insert_user(
			array(
				'user_login' => $user_login,
				'user_pass'  => wp_generate_password( 24 ),
				'user_email' => $user_login . '@example.invalid',
				'role'       => 'subscriber',
			)
		);

		$this->assertNotInstanceOf( WP_Error::class, $user_id, "Could not create the user '{$user_login}'." );

		$this->user_ids[] = (int) $user_id;

		return (int) $user_id;
	}

	/**
	 * Grants a login commit access to the plugin under test.
	 *
	 * @param string $user_login Login to record as a committer.
	 * @return void
	 */
	protected function add_committer( string $user_login ): void {
		global $wpdb;

		$wpdb->insert(
			PLUGINS_TABLE_PREFIX . 'svn_access',
			array(
				'path'   => '/' . self::PLUGIN_SLUG,
				'user'   => $user_login,
				'access' => 'rw',
			)
		);
		wp_cache_delete( self::PLUGIN_SLUG, 'plugin-committers' );
	}

	/**
	 * Statuses the directory serves publicly, which stay readable over REST.
	 *
	 * @return array<string, string[]>
	 */
	public static function data_public_statuses(): array {
		return array(
			'publish'  => array( 'publish' ),
			'closed'   => array( 'closed' ),
			'disabled' => array( 'disabled' ),
		);
	}

	/**
	 * Non-public plugin statuses that must never be exposed over REST.
	 *
	 * @return array<string, string[]>
	 */
	public static function data_non_public_statuses(): array {
		return array(
			'new'      => array( 'new' ),
			'pending'  => array( 'pending' ),
			'approved' => array( 'approved' ),
			'rejected' => array( 'rejected' ),
			'draft'    => array( 'draft' ),
		);
	}

	/**
	 * Public statuses stay readable to both anonymous and logged-in visitors.
	 *
	 * @dataProvider data_public_statuses
	 *
	 * @param string $post_status Public plugin status under test.
	 * @return void
	 */
	public function test_public_statuses_are_readable( string $post_status ): void {
		$plugin = $this->create_plugin( $post_status );

		wp_set_current_user( 0 );
		$this->assertTrue(
			$this->controller->check_read_permission( $plugin ),
			"A logged-out visitor should read a '{$post_status}' plugin over REST."
		);

		$subscriber = $this->create_user( 'plainsubscriber' );
		wp_set_current_user( $subscriber );
		$this->assertTrue(
			$this->controller->check_read_permission( $plugin ),
			"A subscriber should read a '{$post_status}' plugin over REST."
		);
	}

	/**
	 * A plain subscriber cannot read a submission still in the review pipeline.
	 *
	 * This is the regression guard for the `read_post => plugin_admin_view` mapping;
	 * with `read_post => read` each of these returned true.
	 *
	 * @dataProvider data_non_public_statuses
	 *
	 * @param string $post_status Non-public plugin status under test.
	 * @return void
	 */
	public function test_non_public_statuses_are_hidden_from_subscribers( string $post_status ): void {
		$plugin     = $this->create_plugin( $post_status );
		$subscriber = $this->create_user( 'plainsubscriber' );

		wp_set_current_user( $subscriber );

		$this->assertFalse(
			$this->controller->check_read_permission( $plugin ),
			"A subscriber must not read a '{$post_status}' plugin over REST."
		);
	}

	/**
	 * A committer keeps REST read access to their own non-public submission.
	 *
	 * @return void
	 */
	public function test_committer_can_read_non_public_submission(): void {
		$committer = $this->create_user( 'pluginowner' );
		$plugin    = $this->create_plugin( 'pending', $committer );
		$this->add_committer( 'pluginowner' );

		wp_set_current_user( $committer );

		$this->assertTrue(
			$this->controller->check_read_permission( $plugin ),
			'A committer should retain read access to their pending submission.'
		);
	}

	/**
	 * A reviewer keeps REST read access to non-public submissions.
	 *
	 * @return void
	 */
	public function test_reviewer_can_read_non_public_submission(): void {
		$plugin   = $this->create_plugin( 'pending' );
		$reviewer = $this->create_user( 'pluginreviewer' );

		$user = get_user_by( 'id', $reviewer );
		$user->add_cap( 'plugin_review' );

		wp_set_current_user( $reviewer );

		$this->assertTrue(
			$this->controller->check_read_permission( $plugin ),
			'A plugin reviewer should retain read access to a pending submission.'
		);
	}
}
