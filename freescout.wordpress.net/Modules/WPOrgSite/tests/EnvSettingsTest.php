<?php
/**
 * Tests for the settings FreeScout keeps in `.env`.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSite
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSite\Tests;

use Modules\WPOrgSite\Providers\WPOrgSiteServiceProvider;
use WordPressdotorg\FreeScout\Tests\TestCase;

/**
 * Covers saving Manage » Settings with a read-only `.env`, as on the server, and showing its settings read-only.
 */
final class EnvSettingsTest extends TestCase {

	/**
	 * Directory of the read-only `.env`.
	 *
	 * @var string
	 */
	private $env_dir;

	/**
	 * Registers the module, logs in an administrator, and makes `.env` read-only.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->app->register( WPOrgSiteServiceProvider::class );
		$this->actingAs( $this->create_user() );

		$this->env_dir = sys_get_temp_dir() . '/wporgsite-env-' . uniqid();
		mkdir( $this->env_dir );
		file_put_contents( $this->env_dir . '/.env', "APP_TIMEZONE=UTC\n" );
		chmod( $this->env_dir . '/.env', 0444 );
		$this->app->useEnvironmentPath( $this->env_dir );
	}

	/**
	 * Removes the read-only `.env`.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		chmod( $this->env_dir . '/.env', 0644 );
		unlink( $this->env_dir . '/.env' );
		rmdir( $this->env_dir );

		parent::tearDown();
	}

	/**
	 * A section saves its other settings, and leaves `.env` alone, rather than failing on it.
	 *
	 * @return void
	 */
	public function test_saves_section_without_writing_env(): void {
		$this->post(
			route( 'settings.save', array( 'section' => 'general' ) ),
			array(
				'_token'   => csrf_token(),
				'settings' => array(
					'company_name' => 'WordPress.org',
					'timezone'     => 'Europe/Berlin',
				),
			)
		)->assertRedirect( route( 'settings', array( 'section' => 'general' ) ) );

		$this->assertSame( 'WordPress.org', \Option::get( 'company_name', false, true, false ) );
		$this->assertSame( "APP_TIMEZONE=UTC\n", file_get_contents( $this->env_dir . '/.env' ) );
	}

	/**
	 * The section's form is marked with its `.env` settings, for settings.js to show them read-only.
	 *
	 * @return void
	 */
	public function test_marks_env_settings(): void {
		$page = (string) $this->get( route( 'settings', array( 'section' => 'general' ) ) )->assertStatus( 200 )->getContent();

		$this->assertSame( 1, preg_match( '/<div class="wporgsite-env-settings" data-settings="([^"]*)".*name="settings\[company_name\]"/s', $page, $match ) );

		// Core adds settings to `.env` as it goes, so only some of its own are checked.
		$keys = json_decode( html_entity_decode( $match[1] ), true );
		$this->assertSame( array( 'custom_number', 'timezone' ), array_values( array_intersect( $keys, array( 'company_name', 'custom_number', 'timezone' ) ) ) );
	}
}
