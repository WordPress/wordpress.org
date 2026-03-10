<?php

use WordPressdotorg\Plugin_Directory\Template;

/**
 * @group plugin-directory
 * @group template
 */
class Tests_Template extends WP_UnitTestCase {

	/**
	 * Helper to create a plugin post with required fields to avoid
	 * "Undefined array key" errors in filter_wp_insert_post_data.
	 */
	private function create_plugin_post( $args = [] ) {
		$now = current_time( 'mysql' );
		$gmt = current_time( 'mysql', true );

		return self::factory()->post->create_and_get( array_merge( [
			'post_type'         => 'plugin',
			'post_status'       => 'publish',
			'post_modified'     => $now,
			'post_modified_gmt' => $gmt,
		], $args ) );
	}

	/**
	 * @dataProvider data_sanitize_active_installs
	 */
	function test_sanitize_active_installs( $input, $expected ): void {
		// floor() returns float, so use assertEquals for loose type comparison.
		$this->assertEquals( $expected, Template::sanitize_active_installs( $input ) );
	}

	function data_sanitize_active_installs(): array {
		return [
			'zero'                => [ 0, 0 ],
			'single digit'        => [ 5, 0 ],
			'ten'                 => [ 10, 10 ],
			'twelve'              => [ 12, 10 ],
			'ninety nine'         => [ 99, 90 ],
			'one hundred'         => [ 100, 100 ],
			'hundred fifty'       => [ 150, 100 ],
			'nine ninety nine'    => [ 999, 900 ],
			'one thousand'        => [ 1000, 1000 ],
			'one thousand five'   => [ 1500, 1000 ],
			'ten thousand'        => [ 10000, 10000 ],
			'fifteen thousand'    => [ 15000, 10000 ],
			'hundred thousand'    => [ 100000, 100000 ],
			'five hundred k'      => [ 500000, 500000 ],
			'one million'         => [ 1000000, 1000000 ],
			'five million'        => [ 5000000, 5000000 ],
			'ten million'         => [ 10000000, 10000000 ],
			'over ten million'    => [ 15000000, 10000000 ],
			'fifty million'       => [ 50000000, 10000000 ],
		];
	}

	/**
	 * @dataProvider data_format_active_installs_for_display
	 */
	function test_format_active_installs_for_display( $input, $expected ): void {
		$this->assertSame( $expected, Template::format_active_installs_for_display( $input ) );
	}

	function data_format_active_installs_for_display(): array {
		return [
			'zero'           => [ 0, 'Fewer than 10' ],
			'five'           => [ 5, 'Fewer than 10' ],
			'nine'           => [ 9, 'Fewer than 10' ],
			'ten'            => [ 10, '10+' ],
			'hundred'        => [ 100, '100+' ],
			'thousand'       => [ 1000, '1,000+' ],
			'ten thousand'   => [ 10000, '10,000+' ],
			'one million'    => [ 1000000, '1+ million' ],
			'two million'    => [ 2000000, '2+ million' ],
			'ten million'    => [ 10000000, '10+ million' ],
		];
	}

	function test_get_plugin_section_titles(): void {
		$titles = Template::get_plugin_section_titles();

		$this->assertIsArray( $titles );

		$expected_keys = [
			'description',
			'installation',
			'faq',
			'screenshots',
			'changelog',
			'stats',
			'support',
			'reviews',
			'developers',
			'other_notes',
			'blocks',
		];

		$this->assertSame( $expected_keys, array_keys( $titles ) );
	}

	function test_get_current_major_wp_version_returns_float(): void {
		$version = Template::get_current_major_wp_version();

		$this->assertIsFloat( $version );
		$this->assertGreaterThan( 0, $version );
	}

	/**
	 * @dataProvider data_encode
	 */
	function test_encode( $input, $expected ): void {
		$this->assertSame( $expected, Template::encode( $input ) );
	}

	function data_encode(): array {
		return [
			'plain ascii'    => [ 'Hello World', 'Hello World' ],
			'empty string'   => [ '', '' ],
			'numeric entity' => [ 'caf&#233;', 'caf&#233;' ],
			'utf8 e-acute'   => [ "caf\xC3\xA9", 'caf&#233;' ],
			'utf8 em-dash'   => [ "\xE2\x80\x94", '&#8212;' ],
			'utf8 copyright' => [ "\xC2\xA9", '&#169;' ],
			// encode() round-trips & < > via htmlentities + htmlspecialchars_decode (ENT_NOQUOTES).
			'ampersand'      => [ 'A & B', 'A & B' ],
			'angle brackets' => [ 'a < b > c', 'a < b > c' ],
		];
	}

	/**
	 * Test dashicons_stars output.
	 *
	 * @dataProvider data_dashicons_stars
	 */
	function test_dashicons_stars( $rating, $filled, $half, $empty_stars ): void {
		$output = Template::dashicons_stars( $rating );

		$this->assertSame( $filled, substr_count( $output, 'dashicons-star-filled' ) );
		$this->assertSame( $half, substr_count( $output, 'dashicons-star-half' ) );
		$this->assertSame( $empty_stars, substr_count( $output, 'dashicons-star-empty' ) );
	}

	function data_dashicons_stars(): array {
		// [ rating, filled, half, empty ]
		return [
			'zero stars'     => [ 0, 0, 0, 5 ],
			'one star'       => [ 1, 1, 0, 4 ],
			'two and a half' => [ 2.5, 2, 1, 2 ],
			'four stars'     => [ 4, 4, 0, 1 ],
			'five stars'     => [ 5, 5, 0, 0 ],
			'three point 3'  => [ 3.3, 3, 1, 1 ],
		];
	}

	function test_get_close_reasons_returns_expected_keys(): void {
		$reasons = Template::get_close_reasons();

		$this->assertIsArray( $reasons );
		$this->assertArrayHasKey( 'security-issue', $reasons );
		$this->assertArrayHasKey( 'author-request', $reasons );
		$this->assertArrayHasKey( 'guideline-violation', $reasons );
		$this->assertArrayHasKey( 'licensing-trademark-violation', $reasons );
		$this->assertArrayHasKey( 'merged-into-core', $reasons );
		$this->assertArrayHasKey( 'unused', $reasons );
	}

	function test_get_rejection_reasons_returns_expected_keys(): void {
		$reasons = Template::get_rejection_reasons();

		$this->assertIsArray( $reasons );
		$this->assertArrayHasKey( '3-month', $reasons );
		$this->assertArrayHasKey( 'security', $reasons );
		$this->assertArrayHasKey( 'duplicate', $reasons );
		$this->assertArrayHasKey( 'banned', $reasons );
	}

	function test_download_link_with_version(): void {
		$plugin = $this->create_plugin_post( [ 'post_name' => 'test-download' ] );
		update_post_meta( $plugin->ID, 'stable_tag', '2.1.0' );

		$link = Template::download_link( $plugin, '1.0.0' );
		$this->assertSame( 'https://downloads.wordpress.org/plugin/test-download.1.0.0.zip', $link );

		$link = Template::download_link( $plugin, 'latest' );
		$this->assertSame( 'https://downloads.wordpress.org/plugin/test-download.2.1.0.zip', $link );

		$link = Template::download_link( $plugin, 'trunk' );
		$this->assertSame( 'https://downloads.wordpress.org/plugin/test-download.zip', $link );
	}

	function test_get_support_url_standard(): void {
		$plugin = $this->create_plugin_post( [ 'post_name' => 'my-cool-tool' ] );

		$url = Template::get_support_url( $plugin );
		$this->assertSame( 'https://wordpress.org/support/plugin/my-cool-tool/', $url );
	}

	function test_get_support_url_buddypress(): void {
		$plugin = $this->create_plugin_post( [ 'post_name' => 'buddypress' ] );

		$url = Template::get_support_url( $plugin );
		$this->assertSame( 'https://buddypress.org/support/', $url );
	}

	function test_get_support_url_bbpress(): void {
		$plugin = $this->create_plugin_post( [ 'post_name' => 'bbpress' ] );

		$url = Template::get_support_url( $plugin );
		$this->assertSame( 'https://bbpress.org/forums/', $url );
	}

	function test_geopattern_icon_url_format(): void {
		$plugin = $this->create_plugin_post( [ 'post_name' => 'geo-test' ] );

		$url = Template::get_geopattern_icon_url( $plugin );
		$this->assertStringContainsString( 'geo-test', $url );
		$this->assertStringContainsString( 'geopattern-icon', $url );
		$this->assertStringEndsWith( '.svg', $url );
	}

	function test_geopattern_icon_url_with_color(): void {
		$plugin = $this->create_plugin_post( [ 'post_name' => 'geo-color' ] );

		$url = Template::get_geopattern_icon_url( $plugin, 'ff5500' );
		$this->assertStringContainsString( 'geo-color_ff5500', $url );
	}

	function test_geopattern_icon_url_with_invalid_color(): void {
		$plugin = $this->create_plugin_post( [ 'post_name' => 'geo-invalid' ] );

		$url = Template::get_geopattern_icon_url( $plugin, 'notacolor' );
		$this->assertStringNotContainsString( '_notacolor', $url );
	}

	function test_is_plugin_outdated(): void {
		$plugin = $this->create_plugin_post( [ 'post_name' => 'old-tool' ] );

		// Set tested to a very old version.
		update_post_meta( $plugin->ID, 'tested', '4.0' );
		$this->assertTrue( Template::is_plugin_outdated( $plugin ) );

		// Set tested to current version.
		$current = Template::get_current_major_wp_version();
		update_post_meta( $plugin->ID, 'tested', (string) $current );
		$this->assertFalse( Template::is_plugin_outdated( $plugin ) );
	}

	function test_get_rollout_strategies(): void {
		$strategies = Template::get_rollout_strategies();

		$this->assertIsArray( $strategies );
		$this->assertArrayHasKey( '', $strategies );
		$this->assertArrayHasKey( 'manual-updates-24hr', $strategies );
		$this->assertArrayHasKey( 'name', $strategies[''] );
		$this->assertArrayHasKey( 'description', $strategies[''] );
	}
}
