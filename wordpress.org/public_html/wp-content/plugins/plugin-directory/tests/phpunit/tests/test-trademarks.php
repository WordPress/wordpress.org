<?php

use WordPressdotorg\Plugin_Directory\Trademarks;

/**
 * @group plugin-directory
 * @group trademarks
 */
class Tests_Trademarks extends WP_UnitTestCase {

	/**
	 * Test that clean slugs pass trademark checks.
	 *
	 * @dataProvider data_clean_slugs
	 */
	function test_clean_slugs_pass( $slug ): void {
		$this->assertFalse( Trademarks::check_slug( $slug ) );
	}

	function data_clean_slugs(): array {
		// Note: 'plugin' is a trademarked slug, so avoid it in clean slugs.
		return [
			'generic slug'      => [ 'my-cool-tool' ],
			'simple slug'       => [ 'hello-world' ],
			'numeric slug'      => [ 'acme-2024' ],
			'short slug'        => [ 'foo' ],
		];
	}

	/**
	 * Test that trademarked prefix slugs are detected.
	 *
	 * @dataProvider data_trademarked_prefix_slugs
	 */
	function test_trademarked_prefix_slugs( $slug, $expected_trademark ): void {
		$result = Trademarks::check_slug( $slug );
		$this->assertIsArray( $result );
		$this->assertContains( $expected_trademark, $result );
	}

	function data_trademarked_prefix_slugs(): array {
		return [
			'google prefix'    => [ 'google-analytics-tool', 'google-' ],
			'facebook in slug' => [ 'my-facebook-share', 'facebook' ],
			'jetpack prefix'   => [ 'jetpack-addon', 'jetpack-' ],
			'stripe prefix'    => [ 'stripe-payments', 'stripe-' ],
			'paypal prefix'    => [ 'paypal-checkout', 'paypal-' ],
			'woocommerce slug' => [ 'woocommerce-extras', 'woocommerce' ],
			'instagram slug'   => [ 'instagram-feed-widget', 'instagram' ],
			'twitter slug'     => [ 'twitter-cards', 'twitter-' ],
			'chatgpt prefix'   => [ 'chatgpt-assistant', 'chatgpt-' ],
		];
	}

	/**
	 * Test the for-woocommerce exception.
	 */
	function test_for_woocommerce_exception(): void {
		// "something-for-woocommerce" is allowed.
		$this->assertFalse( Trademarks::check_slug( 'my-payments-for-woocommerce' ) );

		// But "woocommerce-something" is not.
		$result = Trademarks::check_slug( 'woocommerce-payments' );
		$this->assertIsArray( $result );
	}

	/**
	 * Test that check() converts a plugin name to slug first.
	 */
	function test_check_converts_name_to_slug(): void {
		// "My Cool Tool" should be clean (note: 'plugin' is trademarked).
		$this->assertFalse( Trademarks::check( 'My Cool Tool' ) );

		// "Google Maps Helper" should be trademarked (contains 'google-').
		$result = Trademarks::check( 'Google Maps Helper' );
		$this->assertIsArray( $result );
	}

	/**
	 * Test that trademark exceptions work.
	 */
	function test_trademark_exceptions(): void {
		// Without exception, jetpack- is trademarked.
		$result = Trademarks::check_slug( 'jetpack-boost-extra' );
		$this->assertIsArray( $result );

		// With automattic.com exception, jetpack- is allowed.
		$result = Trademarks::check_slug( 'jetpack-boost-extra', [ 'automattic.com' ] );
		$this->assertFalse( $result );
	}

	/**
	 * Test published plugin exception for wp- prefix.
	 */
	function test_published_plugin_wp_prefix_exception(): void {
		// wp- is flagged for new plugins.
		$result = Trademarks::check_slug( 'wp-super-cache' );
		$this->assertIsArray( $result );

		// published-plugin exception allows wp-.
		$result = Trademarks::check_slug( 'wp-super-cache', [ 'published-plugin' ] );
		$this->assertFalse( $result );
	}

	/**
	 * Test portmanteau detection (e.g. woopress).
	 */
	function test_portmanteau_detection(): void {
		$result = Trademarks::check_slug( 'woopress' );
		$this->assertIsArray( $result );
	}

	/**
	 * Test that the trademarked slugs list is not empty.
	 */
	function test_trademarked_slugs_list_is_populated(): void {
		$this->assertNotEmpty( Trademarks::$trademarked_slugs );
		$this->assertGreaterThan( 50, count( Trademarks::$trademarked_slugs ) );
	}
}
