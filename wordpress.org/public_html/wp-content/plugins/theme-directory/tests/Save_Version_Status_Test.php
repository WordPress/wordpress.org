<?php
/**
 * Tests for saving the Theme Versions meta box.
 *
 * The meta box renders a status control only for versions that already exist in
 * a package's `_status` post meta. Saving should therefore record statuses for
 * those known versions only, and ignore any other keys or statuses in the
 * request.
 *
 * @package theme-directory
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;

/**
 * Covers wporg_themes_save_meta_box_data().
 *
 * @group meta-box
 */
class Save_Version_Status_Test extends TestCase {

	/**
	 * IDs of posts created during a test, deleted again on teardown.
	 *
	 * @var int[]
	 */
	protected array $post_ids = array();

	/**
	 * IDs of users created during a test, deleted again on teardown.
	 *
	 * @var int[]
	 */
	protected array $user_ids = array();

	/**
	 * Starts each test from a clean request.
	 */
	protected function setUp(): void {
		parent::setUp();

		$_POST = array();
	}

	/**
	 * Removes the fixture posts, users, and request state.
	 */
	protected function tearDown(): void {
		$_POST = array();
		wp_set_current_user( 0 );

		/*
		 * The plugin prevents repopackages from being deleted; detach that
		 * specific guard while cleaning up the fixture posts.
		 */
		remove_filter( 'before_delete_post', 'wporg_theme_no_delete_repopackage' );
		foreach ( $this->post_ids as $post_id ) {
			wp_delete_post( $post_id, true );
		}
		add_filter( 'before_delete_post', 'wporg_theme_no_delete_repopackage' );

		if ( ! function_exists( 'wp_delete_user' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}
		foreach ( $this->user_ids as $user_id ) {
			wp_delete_user( $user_id );
		}

		$this->post_ids = array();
		$this->user_ids = array();

		parent::tearDown();
	}

	/**
	 * Creates a contributor and a draft repopackage they own.
	 *
	 * A contributor may edit their own package while it is unpublished, which is
	 * the state in which the Theme Versions meta box is saved.
	 *
	 * @param array $status Initial `_status` meta, keyed by version string.
	 * @return array{0: int, 1: int} The repopackage post ID and its author's user ID.
	 */
	protected function create_owned_repopackage( array $status ): array {
		$author = array(
			'user_login' => 'theme-contributor-' . uniqid(),
			'user_pass'  => 'password',
			'user_email' => uniqid() . '@example.org',
			'role'       => 'contributor',
		);

		$author_id        = wp_insert_user( $author );
		$this->user_ids[] = $author_id;

		$package = array(
			'post_type'   => 'repopackage',
			'post_status' => 'draft',
			'post_title'  => 'Test Theme',
			'post_name'   => 'test-theme-' . uniqid(),
			'post_author' => $author_id,
		);

		$post_id          = wp_insert_post( $package );
		$this->post_ids[] = $post_id;

		add_post_meta( $post_id, '_status', $status );

		return array( $post_id, $author_id );
	}

	/**
	 * Seeds the request superglobal for a meta box save by the current user.
	 *
	 * Version strings travel as base64-encoded array keys, matching how the meta
	 * box renders them.
	 *
	 * @param array $statuses New statuses keyed by plain version string.
	 * @return void
	 */
	protected function prime_request( array $statuses ): void {
		$_POST['wporg_themes_meta_box_nonce'] = wp_create_nonce( 'wporg_themes_meta_box' );
		$_POST['wporg_themes_status']         = array();

		foreach ( $statuses as $version => $status ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- versions travel as base64 keys, matching the meta box.
			$key = base64_encode( (string) $version );

			$_POST['wporg_themes_status'][ $key ] = $status;
		}
	}

	/**
	 * A status change for a version the package actually has is recorded.
	 */
	public function test_known_version_status_is_saved(): void {
		list( $post_id, $author_id ) = $this->create_owned_repopackage( array( '1.0' => 'new' ) );
		wp_set_current_user( $author_id );

		$this->prime_request( array( '1.0' => 'old' ) );
		wporg_themes_save_meta_box_data( $post_id );

		$this->assertSame( array( '1.0' => 'old' ), get_post_meta( $post_id, '_status', true ) );
	}

	/**
	 * A key that does not decode to an existing version is ignored, and the
	 * per-version `live` hook never runs for it.
	 */
	public function test_unknown_version_key_is_ignored(): void {
		list( $post_id, $author_id ) = $this->create_owned_repopackage( array( '1.0' => 'new' ) );
		wp_set_current_user( $author_id );

		/*
		 * Record any `live` transition rather than letting the real subscribers
		 * (which publish the theme and post to another host) run, so the unit
		 * stays isolated even if this guard should ever regress.
		 */
		$seen = array();
		$spy  = function ( int $post_id, string $version = '' ) use ( &$seen ): void {
			$seen[] = $version;
		};
		remove_action( 'wporg_themes_update_version_live', 'wporg_themes_approve_version', 10 );
		remove_action( 'wporg_themes_update_version_live', 'wporg_themes_update_check', 10 );
		remove_action( 'wporg_themes_update_version_live', 'wporg_themes_glotpress_import', 100 );
		add_action( 'wporg_themes_update_version_live', $spy, 10, 2 );

		$this->prime_request( array( '--not-a-version' => 'live' ) );
		wporg_themes_save_meta_box_data( $post_id );

		remove_action( 'wporg_themes_update_version_live', $spy, 10 );
		add_action( 'wporg_themes_update_version_live', 'wporg_themes_approve_version', 10, 3 );
		add_action( 'wporg_themes_update_version_live', 'wporg_themes_update_check', 10, 2 );
		add_action( 'wporg_themes_update_version_live', 'wporg_themes_glotpress_import', 100, 2 );

		$this->assertSame( array( '1.0' => 'new' ), get_post_meta( $post_id, '_status', true ) );
		$this->assertSame( array(), $seen );
	}

	/**
	 * A status the meta box does not offer is ignored.
	 */
	public function test_unknown_status_is_ignored(): void {
		list( $post_id, $author_id ) = $this->create_owned_repopackage( array( '1.0' => 'new' ) );
		wp_set_current_user( $author_id );

		$this->prime_request( array( '1.0' => 'not-a-status' ) );
		wporg_themes_save_meta_box_data( $post_id );

		$this->assertSame( array( '1.0' => 'new' ), get_post_meta( $post_id, '_status', true ) );
	}
}
