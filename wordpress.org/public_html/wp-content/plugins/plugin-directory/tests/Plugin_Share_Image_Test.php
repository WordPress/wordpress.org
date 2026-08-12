<?php
/**
 * Tests for Plugin_Share_Image.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

use WordPressdotorg\Plugin_Directory\Plugin_Share_Image;

/**
 * @group share-image
 */
class Plugin_Share_Image_Test extends WP_UnitTestCase {

	/**
	 * Create a published plugin fixture without icon meta.
	 *
	 * @param array $args Optional post args / meta overrides.
	 * @return \WP_Post
	 */
	protected function create_plugin( $args = array() ) {
		$active_installs = $args['active_installs'] ?? 0;
		unset( $args['active_installs'] );

		$post_id = self::factory()->post->create(
			array_merge(
				array(
					'post_type'    => 'plugin',
					'post_status'  => 'publish',
					'post_title'   => 'Share Image Test Plugin',
					'post_name'    => 'share-image-test-plugin-' . wp_generate_password( 6, false ),
					'post_excerpt' => 'A short description for the share image test.',
				),
				$args
			)
		);

		update_post_meta( $post_id, 'active_installs', $active_installs );

		return get_post( $post_id );
	}

	/**
	 * Get the installs stat value from get_data().
	 *
	 * @param \WP_Post $plugin Plugin post.
	 * @return string|null
	 */
	protected function get_installs_value( $plugin ) {
		$data = Plugin_Share_Image::get_data( $plugin );
		if ( ! $data || empty( $data['stats'] ) ) {
			return null;
		}

		$installs = end( $data['stats'] );

		return $installs['value'] ?? null;
	}

	public function test_get_data_returns_null_for_non_plugin_post() {
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => 'post',
				'post_status' => 'publish',
				'post_title'  => 'Not a plugin',
			)
		);

		$this->assertNull( Plugin_Share_Image::get_data( get_post( $post_id ) ) );
	}

	public function test_get_data_returns_null_for_unpublished_plugin() {
		$plugin = $this->create_plugin(
			array(
				'post_status' => 'draft',
			)
		);

		$this->assertNull( Plugin_Share_Image::get_data( $plugin ) );
	}

	public function test_get_data_formats_install_counts() {
		$this->assertSame( '<10', $this->get_installs_value( $this->create_plugin( array( 'active_installs' => 0 ) ) ) );
		$this->assertSame( '500+', $this->get_installs_value( $this->create_plugin( array( 'active_installs' => 500 ) ) ) );
		$this->assertSame( '50,000+', $this->get_installs_value( $this->create_plugin( array( 'active_installs' => 50000 ) ) ) );
		$this->assertSame( '150K+', $this->get_installs_value( $this->create_plugin( array( 'active_installs' => 150000 ) ) ) );
		$this->assertSame( '2M+', $this->get_installs_value( $this->create_plugin( array( 'active_installs' => 2500000 ) ) ) );
	}

	public function test_render_returns_false_for_non_plugin_post() {
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => 'post',
				'post_status' => 'publish',
				'post_title'  => 'Not a plugin',
			)
		);

		$this->assertFalse( Plugin_Share_Image::render( get_post( $post_id ) ) );
	}

	public function test_render_returns_false_for_unpublished_plugin() {
		$plugin = $this->create_plugin(
			array(
				'post_status' => 'draft',
			)
		);

		$this->assertFalse( Plugin_Share_Image::render( $plugin ) );
	}

	public function test_render_returns_jpeg_for_published_plugin() {
		// Fail loudly rather than skip: without the font, render() would 500 in production too.
		$this->assertFileExists(
			WP_CONTENT_DIR . '/mu-plugins/wporg-mu-plugins/fonts/Inter.ttf',
			'Inter.ttf is required to render share images; map wporg-mu-plugins fonts into this environment.'
		);

		$plugin = $this->create_plugin(
			array(
				'active_installs' => 150000,
			)
		);

		$bytes = Plugin_Share_Image::render( $plugin );

		$this->assertNotFalse( $bytes );
		$this->assertNotEmpty( $bytes );

		$info = getimagesizefromstring( $bytes );
		$this->assertIsArray( $info );
		$this->assertSame( 1200, $info[0] );
		$this->assertSame( 630, $info[1] );
		$this->assertSame( IMAGETYPE_JPEG, $info[2] );
	}

	/**
	 * A single overlong multibyte word forces character-based truncation;
	 * byte-based truncation used to split UTF-8 characters and fatal in GD.
	 */
	public function test_render_handles_multibyte_title_that_requires_truncation() {
		$this->assertFileExists(
			WP_CONTENT_DIR . '/mu-plugins/wporg-mu-plugins/fonts/Inter.ttf',
			'Inter.ttf is required to render share images; map wporg-mu-plugins fonts into this environment.'
		);

		$plugin = $this->create_plugin(
			array(
				'post_title'   => str_repeat( 'Übérpluginäö', 20 ),
				'post_excerpt' => str_repeat( 'Ünïcödé ', 40 ),
			)
		);

		$bytes = Plugin_Share_Image::render( $plugin );

		$this->assertNotFalse( $bytes );
		$this->assertSame( IMAGETYPE_JPEG, getimagesizefromstring( $bytes )[2] );
	}
}
