<?php
/**
 * Tests for Plugin_Share_Image.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\Plugin_Share_Image;
use WordPressdotorg\Plugin_Directory\Template;

/**
 * Share image URL, data, and render tests.
 *
 * @group share-image
 */
class Plugin_Share_Image_Test extends TestCase {

	/**
	 * Create a published plugin fixture.
	 *
	 * @param array $args Optional post args / meta overrides.
	 * @return \WP_Post
	 */
	protected function create_plugin( $args = array() ) {
		$active_installs = $args['active_installs'] ?? 0;
		$assets_icons    = $args['assets_icons'] ?? null;
		unset( $args['active_installs'], $args['assets_icons'] );

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
		update_post_meta( $post_id, 'last_updated', $now );

		if ( null !== $assets_icons ) {
			update_post_meta( $post_id, 'assets_icons', $assets_icons );
		}

		return get_post( $post_id );
	}

	/**
	 * Build a WP_Post that is not persisted, for negative get_data/get_url/render cases.
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
	 * Require Inter so render tests fail loudly instead of skipping.
	 */
	protected function require_inter_font() {
		$this->assertFileExists(
			WP_CONTENT_DIR . '/mu-plugins/wporg-mu-plugins/fonts/Inter.ttf',
			'Inter.ttf is required to render share images; map wporg-mu-plugins fonts into this environment.'
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
	 * Closed and disabled plugins must not emit a share-image URL.
	 */
	public function test_get_url_returns_false_for_closed_plugin() {
		$this->assertFalse( Plugin_Share_Image::get_url( $this->fake_post( 'plugin', 'closed' ) ) );
		$this->assertFalse( Plugin_Share_Image::get_url( $this->fake_post( 'plugin', 'disabled' ) ) );
	}

	/**
	 * Non-plugin posts must not emit a share-image URL.
	 */
	public function test_get_url_returns_false_for_non_plugin_post() {
		$this->assertFalse( Plugin_Share_Image::get_url( $this->fake_post( 'post', 'publish' ) ) );
	}

	/**
	 * Published plugins get a versioned share-image URL.
	 */
	public function test_get_url_includes_cache_token_for_published_plugin() {
		$this->require_inter_font();

		$plugin = $this->create_plugin( array( 'active_installs' => 150000 ) );
		$url    = Plugin_Share_Image::get_url( $plugin );

		$this->assertNotFalse( $url );
		$this->assertMatchesRegularExpression(
			'#/share-image/' . preg_quote( $plugin->post_name, '#' ) . '_[a-f0-9]{8}\.jpg$#',
			$url
		);
	}

	/**
	 * Changing installs must bust the share-image URL token.
	 */
	public function test_get_url_changes_when_install_count_changes() {
		$this->require_inter_font();

		$plugin = $this->create_plugin( array( 'active_installs' => 1000 ) );
		$first  = Plugin_Share_Image::get_url( $plugin );

		update_post_meta( $plugin->ID, 'active_installs', 2000000 );
		$second = Plugin_Share_Image::get_url( $plugin );

		$this->assertNotFalse( $first );
		$this->assertNotFalse( $second );
		$this->assertNotSame( $first, $second );
	}

	/**
	 * A 1x raster icon is used when icon_2x is false.
	 */
	public function test_get_data_falls_back_to_1x_icon_when_2x_is_false() {
		$plugin = $this->create_plugin(
			array(
				'assets_icons' => array(
					array(
						'filename'   => 'icon-128x128.png',
						'revision'   => 1,
						'resolution' => '128x128',
						'locale'     => '',
					),
				),
			)
		);

		$icons = Template::get_plugin_icon( $plugin );
		$this->assertFalse( $icons['icon_2x'] );
		$this->assertNotEmpty( $icons['icon'] );

		$data = Plugin_Share_Image::get_data( $plugin );
		$this->assertSame( $icons['icon'], $data['icon_url'] );
	}

	/**
	 * Install counts use the directory display formatting.
	 */
	public function test_get_data_uses_directory_install_formatting() {
		$plugin = $this->create_plugin( array( 'active_installs' => 150000 ) );
		$data   = Plugin_Share_Image::get_data( $plugin );
		$last   = end( $data['stats'] );

		$this->assertSame( Template::format_active_installs_for_display( 150000 ), $last['value'] );
		$this->assertSame( 'Installs', $last['label'] );
	}

	/**
	 * Contributor nicenames that do not resolve to a user are dropped.
	 */
	public function test_contributors_helper_drops_nicenames_that_do_not_resolve() {
		$plugin = $this->create_plugin();
		wp_set_object_terms( $plugin->ID, array( 'not-a-real-wporg-user-xyz' ), 'plugin_contributors' );

		$contributors = Template::get_plugin_contributors( $plugin );

		foreach ( $contributors as $user ) {
			$this->assertInstanceOf( \WP_User::class, $user );
			$this->assertNotSame( 'not-a-real-wporg-user-xyz', $user->user_nicename );
		}
	}

	/**
	 * Plugins with no translations report zero locales.
	 */
	public function test_count_plugin_locales_returns_zero_without_translations() {
		$plugin = $this->create_plugin();
		$this->assertSame( 0, Template::count_plugin_locales( $plugin ) );
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
		$this->require_inter_font();

		$plugin = $this->create_plugin( array( 'active_installs' => 150000 ) );
		$bytes  = Plugin_Share_Image::render( $plugin );

		$this->assertNotFalse( $bytes );
		$this->assertNotEmpty( $bytes );

		$info = getimagesizefromstring( $bytes );
		$this->assertIsArray( $info );
		$this->assertSame( 1200, $info[0] );
		$this->assertSame( 630, $info[1] );
		$this->assertSame( IMAGETYPE_JPEG, $info[2] );
	}

	/**
	 * An empty excerpt must still render a JPEG.
	 */
	public function test_render_handles_empty_excerpt() {
		$this->require_inter_font();

		$plugin = $this->create_plugin(
			array(
				'post_excerpt' => '',
				'post_content' => '',
			)
		);

		$bytes = Plugin_Share_Image::render( $plugin );

		$this->assertNotFalse( $bytes );
		$this->assertSame( IMAGETYPE_JPEG, getimagesizefromstring( $bytes )[2] );
	}

	/**
	 * Overlong multibyte titles must truncate without fatalling in GD.
	 */
	public function test_render_handles_multibyte_title_that_requires_truncation() {
		$this->require_inter_font();

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
