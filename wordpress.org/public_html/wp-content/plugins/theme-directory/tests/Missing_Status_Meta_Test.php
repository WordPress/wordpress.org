<?php
/**
 * Tests for handling repopackage posts that are missing the `_status` post meta.
 *
 * A hard failure during WPORG_Themes_Upload::import() can leave a repopackage
 * post without `_status` (and `_ticket_id`) meta. WP_Post::__get() returns an
 * empty string for missing meta, which used to fatal on PHP 8 wherever an
 * array was assumed.
 *
 * @package theme-directory
 */

use PHPUnit\Framework\TestCase;

/**
 * @group upload
 * @group themes-api
 */
class Missing_Status_Meta_Test extends TestCase {

	/**
	 * IDs of posts created during a test, deleted again on teardown.
	 *
	 * @var array
	 */
	protected $post_ids = array();

	/**
	 * Deletes the posts created during the test.
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

		$this->post_ids = array();

		parent::tearDown();
	}

	/**
	 * Creates a repopackage post.
	 *
	 * @param array $meta Optional. Post meta to add to the post.
	 * @return WP_Post The created post.
	 */
	protected function create_repopackage( $meta = array() ) {
		$post_id = wp_insert_post( array(
			'post_type'   => 'repopackage',
			'post_status' => 'publish',
			'post_title'  => 'Test Theme',
			'post_name'   => 'test-theme',
			'post_author' => 1,
		) );

		$this->post_ids[] = $post_id;

		foreach ( $meta as $key => $value ) {
			add_post_meta( $post_id, $key, $value );
		}

		return get_post( $post_id );
	}

	/**
	 * A post with no meta at all should not fatal and yield no max_version.
	 */
	public function test_populate_post_with_meta_without_any_meta() {
		$post   = $this->create_repopackage();
		$upload = new WPORG_Themes_Upload();

		$theme = $upload->populate_post_with_meta( $post );

		$this->assertFalse( $theme->max_version );
	}

	/**
	 * A post with versioned meta but no `_status` (an orphan from a failed
	 * import) should not fatal and yield no max_version.
	 */
	public function test_populate_post_with_meta_without_status_meta() {
		$post   = $this->create_repopackage( array(
			'_requires' => array( '1.0.0' => '5.0' ),
			'_author'   => array( '1.0.0' => 'Test Author' ),
		) );
		$upload = new WPORG_Themes_Upload();

		$theme = $upload->populate_post_with_meta( $post );

		$this->assertFalse( $theme->max_version );
		$this->assertSame( array( '1.0.0' => '5.0' ), $theme->_requires );
	}

	/**
	 * A post with valid `_status` meta should yield the highest version.
	 */
	public function test_populate_post_with_meta_with_status_meta() {
		$post   = $this->create_repopackage( array(
			'_status' => array(
				'1.1'  => 'old',
				'1.0'  => 'old',
				'1.10' => 'live',
			),
		) );
		$upload = new WPORG_Themes_Upload();

		$theme = $upload->populate_post_with_meta( $post );

		$this->assertSame( '1.10', $theme->max_version );
	}

	/**
	 * The themes API should return an empty `versions` array for a published
	 * theme missing the `_status` meta, rather than fataling.
	 */
	public function test_theme_information_versions_without_status_meta() {
		$this->create_repopackage();

		$api = new Themes_API( 'theme_information', array(
			'slug'   => 'test-theme',
			'fields' => array(
				'versions'       => true,
				'downloaded'     => false,
				'screenshot_url' => false,
			),
		) );

		$this->assertObjectNotHasProperty( 'error', $api->response );
		$this->assertSame( array(), $api->response->versions );
	}

	/**
	 * The themes API should return all versions for a theme with valid
	 * `_status` meta.
	 */
	public function test_theme_information_versions_with_status_meta() {
		$this->create_repopackage( array(
			'_status' => array(
				'1.0' => 'old',
				'1.1' => 'live',
			),
		) );

		$api = new Themes_API( 'theme_information', array(
			'slug'   => 'test-theme',
			'fields' => array(
				'versions'       => true,
				'downloaded'     => false,
				'screenshot_url' => false,
			),
		) );

		$this->assertObjectNotHasProperty( 'error', $api->response );
		$this->assertSame( array( '1.0', '1.1' ), array_keys( $api->response->versions ) );
	}
}
