<?php
/**
 * Tests for Plugin_Share_Image.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\Plugin_Share_Image;

/**
 * Share image data and render tests.
 *
 * @group share-image
 */
class Plugin_Share_Image_Test extends TestCase {

	/**
	 * Format an install count via the protected helper.
	 *
	 * @param int $active_installs Active install count.
	 * @return string
	 */
	protected function format_installs( $active_installs ) {
		$reflection = new \ReflectionMethod( Plugin_Share_Image::class, 'format_install_count' );

		return $reflection->invoke( null, $active_installs );
	}

	/**
	 * Create a published plugin fixture without icon meta.
	 *
	 * @param array $args Optional post args / meta overrides.
	 * @return \WP_Post
	 */
	protected function create_plugin( $args = array() ) {
		$active_installs = $args['active_installs'] ?? 0;
		unset( $args['active_installs'] );

		$now     = gmdate( 'Y-m-d H:i:s' );
		$post_id = wp_insert_post(
			array_merge(
				array(
					'post_type'         => 'plugin',
					'post_status'       => 'publish',
					'post_title'        => 'Share Image Test Plugin',
					'post_name'         => 'share-image-test-plugin-' . wp_generate_password( 6, false ),
					'post_excerpt'      => 'A short description for the share image test.',
					'post_date'         => $now,
					'post_date_gmt'     => $now,
					'post_modified'     => $now,
					'post_modified_gmt' => $now,
				),
				$args
			),
			true
		);

		$this->assertFalse( is_wp_error( $post_id ) );
		$this->assertIsInt( $post_id );

		update_post_meta( $post_id, 'active_installs', $active_installs );

		return get_post( $post_id );
	}

	/**
	 * Build a WP_Post that is not persisted, for negative get_data/render cases.
	 *
	 * @param string $post_type   Post type.
	 * @param string $post_status Post status.
	 * @return \WP_Post
	 */
	protected function fake_post( $post_type, $post_status ) {
		return new \WP_Post(
			(object) array(
				'ID'          => 0,
				'post_type'   => $post_type,
				'post_status' => $post_status,
				'post_name'   => 'fake-share-image-post',
				'post_title'  => 'Fake',
				'filter'      => 'raw',
			)
		);
	}

	/**
	 * Non-plugin posts must not produce share-image data.
	 */
	public function test_get_data_returns_null_for_non_plugin_post() {
		$this->assertNull( Plugin_Share_Image::get_data( $this->fake_post( 'post', 'publish' ) ) );
	}

	/**
	 * Unpublished plugins must not produce share-image data.
	 */
	public function test_get_data_returns_null_for_unpublished_plugin() {
		$this->assertNull( Plugin_Share_Image::get_data( $this->fake_post( 'plugin', 'draft' ) ) );
	}

	/**
	 * Install counts use the directory's display formatting.
	 */
	public function test_get_data_formats_install_counts() {
		$this->assertSame( '<10', $this->format_installs( 0 ) );
		$this->assertSame( '500+', $this->format_installs( 500 ) );
		$this->assertSame( '50,000+', $this->format_installs( 50000 ) );
		$this->assertSame( '150K+', $this->format_installs( 150000 ) );
		$this->assertSame( '2M+', $this->format_installs( 2500000 ) );
	}

	/**
	 * Rendering a non-plugin post must fail.
	 */
	public function test_render_returns_false_for_non_plugin_post() {
		$this->assertFalse( Plugin_Share_Image::render( $this->fake_post( 'post', 'publish' ) ) );
	}

	/**
	 * Rendering an unpublished plugin must fail.
	 */
	public function test_render_returns_false_for_unpublished_plugin() {
		$this->assertFalse( Plugin_Share_Image::render( $this->fake_post( 'plugin', 'draft' ) ) );
	}

	/**
	 * A published plugin renders a 1200x630 JPEG.
	 */
	public function test_render_returns_jpeg_for_published_plugin() {
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
	 * Overlong multibyte titles must truncate without fatalling in GD.
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
