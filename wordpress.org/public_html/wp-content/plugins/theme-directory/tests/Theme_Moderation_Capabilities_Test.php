<?php
/**
 * Tests that Theme Directory moderation stays with the review team.
 *
 * Guards against `suspend_theme` / `reinstate_theme` answering without looking at
 * the theme they were asked about, which made the object-level check the four
 * moderation handlers appear to perform an object-independent role test.
 *
 * @package theme-directory
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;

/**
 * Covers wporg_themes_map_meta_cap().
 *
 * @group capabilities
 */
class Theme_Moderation_Capabilities_Test extends TestCase {

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
	 * Grants the moderation primitives to the roles that are meant to have them.
	 */
	protected function setUp(): void {
		parent::setUp();

		wporg_themes_add_caps();
	}

	/**
	 * Removes the fixture posts and users.
	 *
	 * The moderation primitives are left in place: granting them to Editors and
	 * Administrators is this site's normal state, not something the test dirtied.
	 */
	protected function tearDown(): void {
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
	 * Creates a user holding a single role.
	 *
	 * @param string $role The role to grant.
	 * @return int The new user's ID.
	 */
	protected function create_user( string $role ): int {
		$user_id = wp_insert_user(
			array(
				'user_login' => $role . '-' . uniqid(),
				'user_pass'  => 'password',
				'user_email' => uniqid() . '@example.org',
				'role'       => $role,
			)
		);

		$this->user_ids[] = $user_id;

		return $user_id;
	}

	/**
	 * Creates a repopackage.
	 *
	 * @param int    $author_id   The owning user's ID.
	 * @param string $post_status Optional. The package's status. Default 'publish'.
	 * @return int The new package's post ID.
	 */
	protected function create_repopackage( int $author_id, string $post_status = 'publish' ): int {
		$post_id = wp_insert_post(
			array(
				'post_type'   => 'repopackage',
				'post_status' => $post_status,
				'post_title'  => 'Test Theme',
				'post_name'   => 'test-theme-' . uniqid(),
				'post_author' => $author_id,
			)
		);

		$this->post_ids[] = $post_id;

		return $post_id;
	}

	/**
	 * An Author cannot moderate a theme somebody else owns.
	 */
	public function test_author_cannot_moderate_someone_elses_theme(): void {
		$author_id = $this->create_user( 'author' );
		$theme_id  = $this->create_repopackage( $this->create_user( 'author' ) );

		$this->assertFalse( user_can( $author_id, 'suspend_theme', $theme_id ) );
		$this->assertFalse( user_can( $author_id, 'reinstate_theme', $theme_id ) );
	}

	/**
	 * Nor a theme the Author owns: moderation state is not self-service.
	 */
	public function test_author_cannot_moderate_their_own_theme(): void {
		$author_id = $this->create_user( 'author' );
		$theme_id  = $this->create_repopackage( $author_id );

		$this->assertFalse( user_can( $author_id, 'suspend_theme', $theme_id ) );
		$this->assertFalse( user_can( $author_id, 'reinstate_theme', $theme_id ) );
	}

	/**
	 * Reviewers keep the access the moderation handlers are written for.
	 */
	public function test_editor_can_moderate_a_theme(): void {
		$editor_id = $this->create_user( 'editor' );
		$theme_id  = $this->create_repopackage( $this->create_user( 'author' ) );

		$this->assertTrue( user_can( $editor_id, 'suspend_theme', $theme_id ) );
		$this->assertTrue( user_can( $editor_id, 'reinstate_theme', $theme_id ) );
	}

	/**
	 * A capability call carrying no theme is refused rather than answered.
	 */
	public function test_moderation_without_a_theme_context_is_denied(): void {
		$editor_id = $this->create_user( 'editor' );

		$this->assertFalse( user_can( $editor_id, 'suspend_theme' ) );
		$this->assertFalse( user_can( $editor_id, 'reinstate_theme' ) );
	}

	/**
	 * The context has to be a theme, not any post that happens to exist.
	 */
	public function test_moderation_of_a_non_theme_is_denied(): void {
		$editor_id = $this->create_user( 'editor' );

		$post_id          = wp_insert_post( array( 'post_title' => 'Not a theme' ) );
		$this->post_ids[] = $post_id;

		$this->assertFalse( user_can( $editor_id, 'suspend_theme', $post_id ) );
		$this->assertFalse( user_can( $editor_id, 'reinstate_theme', $post_id ) );
	}

	/**
	 * A theme owner keeps editing their own package while it is unpublished.
	 */
	public function test_owner_still_edits_their_unpublished_package(): void {
		$author_id = $this->create_user( 'author' );
		$theme_id  = $this->create_repopackage( $author_id, 'draft' );

		$this->assertTrue( user_can( $author_id, 'edit_post', $theme_id ) );
	}
}
