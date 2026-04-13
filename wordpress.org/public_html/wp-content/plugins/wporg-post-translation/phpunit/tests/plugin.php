<?php
/**
 * Tests for the main plugin functions and integration.
 */

use function WordPressdotorg\Post_Translation\{
	is_translation_enabled,
	get_translation_project,
	get_site_project,
};

class Test_Plugin extends WP_UnitTestCase {

	/**
	 * Test that translation is disabled by default.
	 */
	public function test_translation_disabled_by_default() {
		$post_id = self::factory()->post->create();

		$this->assertFalse( is_translation_enabled( $post_id ) );
	}

	/**
	 * Test that translation can be enabled via postmeta.
	 */
	public function test_enable_translation_via_meta() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, '_post_translation_enabled', true );

		$this->assertTrue( is_translation_enabled( $post_id ) );
	}

	/**
	 * Test that get_translation_project returns false when disabled.
	 */
	public function test_project_false_when_disabled() {
		$post_id = self::factory()->post->create();

		$this->assertFalse( get_translation_project( $post_id ) );
	}

	/**
	 * Test that get_translation_project returns a path when enabled.
	 */
	public function test_project_path_when_enabled() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, '_post_translation_enabled', true );

		$project = get_translation_project( $post_id );

		$this->assertIsString( $project );
		$this->assertStringStartsWith( 'post-content/', $project );
	}

	/**
	 * Test that get_site_project generates a slug from the home URL.
	 */
	public function test_site_project_slug() {
		$project = get_site_project();

		$this->assertStringStartsWith( 'post-content/', $project );
		$this->assertStringNotContainsString( 'https://', $project );
		$this->assertStringNotContainsString( 'http://', $project );
	}

	/**
	 * Test the post_translation_enabled filter.
	 */
	public function test_enabled_filter() {
		$post_id = self::factory()->post->create();

		// Force-enable via filter.
		add_filter( 'post_translation_enabled', '__return_true' );
		$this->assertTrue( is_translation_enabled( $post_id ) );
		remove_filter( 'post_translation_enabled', '__return_true' );

		// Force-disable via filter.
		update_post_meta( $post_id, '_post_translation_enabled', true );
		add_filter( 'post_translation_enabled', '__return_false' );
		$this->assertFalse( is_translation_enabled( $post_id ) );
		remove_filter( 'post_translation_enabled', '__return_false' );
	}

	/**
	 * Test the post_translation_project filter.
	 */
	public function test_project_filter() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, '_post_translation_enabled', true );

		add_filter(
			'post_translation_project',
			function () {
				return 'custom/project';
			}
		);

		$this->assertEquals( 'custom/project', get_translation_project( $post_id ) );

		remove_all_filters( 'post_translation_project' );
	}

	/**
	 * Test that is_translation_enabled handles invalid post gracefully.
	 */
	public function test_invalid_post() {
		$this->assertFalse( is_translation_enabled( 999999 ) );
		$this->assertFalse( get_translation_project( 999999 ) );
	}

	/**
	 * Test that the meta key is registered for the REST API.
	 */
	public function test_meta_registered() {
		// Trigger init which registers the meta.
		WordPressdotorg\Post_Translation\Editor::register_meta();

		$registered = get_registered_meta_keys( 'post', '' );

		$this->assertArrayHasKey( '_post_translation_enabled', $registered );
		$this->assertTrue( $registered['_post_translation_enabled']['show_in_rest'] );
		$this->assertEquals( 'boolean', $registered['_post_translation_enabled']['type'] );
	}
}
