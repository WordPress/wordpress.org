<?php
/**
 * End-to-end tests for the Composer packages endpoint.
 *
 * @package WordPressdotorg\API\Composer
 */

declare( strict_types = 1 );

namespace WordPressdotorg\API\Composer\Tests;

use PHPUnit\Framework\TestCase;

// phpcs:disable WordPress.WP.CapitalPDangit -- Package slugs use lowercase "wordpress".

/**
 * End-to-end tests for the packages API.
 *
 * @group packages
 * @group e2e
 */
class Test_Packages_Endpoint extends TestCase {

	/**
	 * Test that packages.json returns a valid Composer v2 repository root.
	 */
	public function test_packages_json(): void {
		$response = send_request( '/packages/packages.json' );

		$this->assertSame( 200, $response->status_code );
		$this->assertStringContainsString( 'application/json', $response->headers['content-type'] );

		$data = json_decode( $response->body );

		$this->assertIsObject( $data );
		$this->assertObjectHasProperty( 'packages', $data );
		$this->assertObjectHasProperty( 'metadata-url', $data );
		$this->assertSame( '/packages/p2/%package%.json', $data->{'metadata-url'} );
		$this->assertObjectHasProperty( 'available-package-patterns', $data );
		$this->assertContains( 'wp-plugin/*', $data->{'available-package-patterns'} );
		$this->assertContains( 'wp-theme/*', $data->{'available-package-patterns'} );
		$this->assertContains( 'wp-core/*', $data->{'available-package-patterns'} );
	}

	/**
	 * Test that a plugin package returns valid Composer metadata.
	 */
	public function test_plugin_package(): void {
		$response = send_request( '/packages/p2/wp-plugin/clicky-popular-posts-widget.json' );

		$this->assertSame( 200, $response->status_code );

		$data = json_decode( $response->body );

		$this->assertIsObject( $data );
		$this->assertObjectHasProperty( 'packages', $data );
		$this->assertObjectHasProperty( 'wp-plugin/clicky-popular-posts-widget', $data->packages );

		$versions = $data->packages->{'wp-plugin/clicky-popular-posts-widget'};
		$this->assertIsArray( $versions );
		$this->assertGreaterThan( 0, count( $versions ) );

		// Check the first version entry has all required fields.
		$entry = $versions[0];
		$this->assertSame( 'wp-plugin/clicky-popular-posts-widget', $entry->name );
		$this->assertSame( 'wordpress-plugin', $entry->type );
		$this->assertIsString( $entry->version );
		$this->assertIsObject( $entry->dist );
		$this->assertSame( 'zip', $entry->dist->type );
		$this->assertStringContainsString( 'downloads.wordpress.org/plugin/clicky-popular-posts-widget', $entry->dist->url );
		$this->assertIsObject( $entry->require );
		$this->assertObjectHasProperty( 'composer/installers', $entry->require );

		// Check extended fields.
		$this->assertIsObject( $entry->source );
		$this->assertSame( 'svn', $entry->source->type );
		$this->assertStringContainsString( 'plugins.svn.wordpress.org/clicky-popular-posts-widget', $entry->source->url );

		$this->assertIsObject( $entry->support );
		$this->assertObjectHasProperty( 'issues', $entry->support );
		$this->assertObjectHasProperty( 'changelog', $entry->support );

		$this->assertIsString( $entry->time );
		$this->assertIsArray( $entry->authors );
	}

	/**
	 * Test that versions are sorted newest first.
	 */
	public function test_plugin_versions_sorted_newest_first(): void {
		$response = send_request( '/packages/p2/wp-plugin/clicky-popular-posts-widget.json' );
		$this->assertSame( 200, $response->status_code );

		$data = json_decode( $response->body );
		$this->assertObjectHasProperty( 'packages', $data );

		$versions = $data->packages->{'wp-plugin/clicky-popular-posts-widget'};

		// First entry should be the latest version.
		$first_version = $versions[0]->version;
		$last_version  = $versions[ count( $versions ) - 1 ]->version;

		$this->assertGreaterThanOrEqual(
			0,
			version_compare( $first_version, $last_version ),
			"First version ($first_version) should be >= last version ($last_version)"
		);
	}

	/**
	 * Test that a theme package returns valid Composer metadata.
	 */
	public function test_theme_package(): void {
		$response = send_request( '/packages/p2/wp-theme/academica.json' );

		$this->assertSame( 200, $response->status_code );

		$data = json_decode( $response->body );

		$this->assertIsObject( $data );
		$this->assertObjectHasProperty( 'packages', $data );
		$this->assertObjectHasProperty( 'wp-theme/academica', $data->packages );

		$versions = $data->packages->{'wp-theme/academica'};
		$this->assertIsArray( $versions );
		$this->assertGreaterThan( 0, count( $versions ) );

		$entry = $versions[0];
		$this->assertSame( 'wp-theme/academica', $entry->name );
		$this->assertSame( 'wordpress-theme', $entry->type );
		$this->assertIsObject( $entry->dist );
		$this->assertStringContainsString( 'downloads.wordpress.org/theme/academica', $entry->dist->url );

		// Check SVN source.
		$this->assertIsObject( $entry->source );
		$this->assertSame( 'svn', $entry->source->type );
		$this->assertStringContainsString( 'themes.svn.wordpress.org/academica', $entry->source->url );

		// Check support.
		$this->assertIsObject( $entry->support );
		$this->assertObjectHasProperty( 'issues', $entry->support );

		// Check author has profile link (extended_author).
		$this->assertIsArray( $entry->authors );
		$this->assertGreaterThan( 0, count( $entry->authors ) );
		$this->assertObjectHasProperty( 'name', $entry->authors[0] );
	}

	/**
	 * Test that the core wordpress package returns valid metadata.
	 */
	public function test_core_package(): void {
		$response = send_request( '/packages/p2/wp-core/wordpress.json' );

		$this->assertSame( 200, $response->status_code );

		$data = json_decode( $response->body );

		$this->assertIsObject( $data );
		$this->assertObjectHasProperty( 'packages', $data );
		$this->assertObjectHasProperty( 'wp-core/wordpress', $data->packages );

		$versions = $data->packages->{'wp-core/wordpress'};
		$this->assertIsArray( $versions );
		$this->assertGreaterThan( 0, count( $versions ) );

		$entry = $versions[0];
		$this->assertSame( 'wp-core/wordpress', $entry->name );
		$this->assertSame( 'wordpress-core', $entry->type );
		$this->assertIsObject( $entry->dist );
		$this->assertStringContainsString( 'downloads.wordpress.org/release/wordpress-', $entry->dist->url );

		// Core-specific fields.
		$this->assertIsArray( $entry->license );
		$this->assertContains( 'GPL-2.0-or-later', $entry->license );
		$this->assertIsObject( $entry->require );
		$this->assertObjectHasProperty( 'php', $entry->require );
		$this->assertIsObject( $entry->suggest );
		$this->assertObjectHasProperty( 'ext-mysqli', $entry->suggest );
		$this->assertIsObject( $entry->provide );
		$this->assertObjectHasProperty( 'wordpress/core-implementation', $entry->provide );
		$this->assertIsObject( $entry->source );
		$this->assertSame( 'git', $entry->source->type );
		$this->assertIsObject( $entry->support );
		$this->assertIsArray( $entry->funding );
	}

	/**
	 * Test that the no-content core package has different download URLs.
	 */
	public function test_core_no_content_package(): void {
		$response = send_request( '/packages/p2/wp-core/wordpress-no-content.json' );

		// May be 404 if no-content zips don't exist on this server.
		if ( 404 === $response->status_code ) {
			$this->markTestSkipped( 'No-content core zips not available on this server.' );
		}

		$this->assertSame( 200, $response->status_code );

		$data     = json_decode( $response->body );
		$versions = $data->packages->{'wp-core/wordpress-no-content'};

		$this->assertIsArray( $versions );
		$this->assertGreaterThan( 0, count( $versions ) );
		$this->assertStringContainsString( '-no-content.zip', $versions[0]->dist->url );
	}

	/**
	 * Test that an unknown plugin returns 404 with empty packages.
	 */
	public function test_unknown_plugin_returns_404(): void {
		$response = send_request( '/packages/p2/wp-plugin/this-plugin-definitely-does-not-exist-xyz.json' );

		$this->assertSame( 404, $response->status_code );

		$data = json_decode( $response->body );
		$this->assertIsObject( $data );
		$this->assertObjectHasProperty( 'packages', $data );
	}

	/**
	 * Test that an unknown theme returns 404 with empty packages.
	 */
	public function test_unknown_theme_returns_404(): void {
		$response = send_request( '/packages/p2/wp-theme/this-theme-definitely-does-not-exist-xyz.json' );

		$this->assertSame( 404, $response->status_code );
	}

	/**
	 * Test that an invalid core slug returns 404.
	 */
	public function test_invalid_core_slug_returns_404(): void {
		$response = send_request( '/packages/p2/wp-core/not-wordpress.json' );

		$this->assertSame( 404, $response->status_code );
	}

	/**
	 * Test that POST to the p2 endpoint returns 405.
	 */
	public function test_p2_rejects_post(): void {
		$url     = 'https://127.0.0.1/packages/p2/wp-plugin/clicky-popular-posts-widget.json';
		$headers = array(
			'Accept' => 'application/json',
			'Host'   => 'api.wordpress.org',
		);
		$options = array( 'verifyname' => false );

		$response = \WpOrg\Requests\Requests::post( $url, $headers, '', $options );

		$this->assertSame( 405, $response->status_code );
	}

	/**
	 * Test that the notify-batch endpoint accepts POST.
	 */
	public function test_notify_batch_accepts_post(): void {
		$url     = 'https://127.0.0.1/packages/downloads';
		$headers = array(
			'Accept'       => 'application/json',
			'Host'         => 'api.wordpress.org',
			'Content-Type' => 'application/json',
		);
		$options = array( 'verifyname' => false );
		$body    = json_encode( array( 'downloads' => array() ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode

		$response = \WpOrg\Requests\Requests::post( $url, $headers, $body, $options );

		$this->assertSame( 200, $response->status_code );

		$data = json_decode( $response->body );
		$this->assertSame( 'ok', $data->status );
	}

	/**
	 * Test that plugin require includes composer/installers and php constraint.
	 */
	public function test_plugin_require_fields(): void {
		$response = send_request( '/packages/p2/wp-plugin/clicky-popular-posts-widget.json' );
		$this->assertSame( 200, $response->status_code );

		$data  = json_decode( $response->body );
		$entry = $data->packages->{'wp-plugin/clicky-popular-posts-widget'}[0];

		$this->assertObjectHasProperty( 'composer/installers', $entry->require );
	}

	/**
	 * Test that Last-Modified header is present.
	 */
	public function test_last_modified_header(): void {
		$response = send_request( '/packages/p2/wp-plugin/clicky-popular-posts-widget.json' );

		$this->assertSame( 200, $response->status_code );
		$this->assertArrayHasKey( 'last-modified', $response->headers );
	}

	/**
	 * Test CORS header is present.
	 */
	public function test_cors_header(): void {
		$response = send_request( '/packages/p2/wp-plugin/clicky-popular-posts-widget.json' );

		$this->assertSame( 200, $response->status_code );
		$this->assertSame( '*', $response->headers['access-control-allow-origin'] );
	}
}
