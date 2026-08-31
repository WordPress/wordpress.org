<?php
/**
 * Tests that a theme version is one stable identity from review to download.
 *
 * @package theme-directory
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;

/**
 * Covers WPORG_Themes_Upload::is_canonical_version() and the download URL identity.
 *
 * @group upload
 */
class Version_Identity_Test extends TestCase {

	/**
	 * IDs of posts created during a test, deleted again on teardown.
	 *
	 * @var int[]
	 */
	protected array $post_ids = array();

	/**
	 * Removes the fixture posts created during a test.
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
	 * Canonical numeric versions are accepted.
	 *
	 * @dataProvider data_canonical_versions
	 *
	 * @param string $version A version that should be accepted.
	 */
	public function test_accepts_canonical_versions( string $version ): void {
		$this->assertTrue( WPORG_Themes_Upload::is_canonical_version( $version ) );
	}

	/**
	 * Data provider of canonical versions.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function data_canonical_versions(): array {
		return array(
			'integer'      => array( '1' ),
			'major minor'  => array( '1.4' ),
			'three parts'  => array( '1.2.3' ),
			'four parts'   => array( '1.2.3.4' ),
			'wide numbers' => array( '10.20.30' ),
		);
	}

	/**
	 * Noncanonical version strings that create an identity split are rejected.
	 *
	 * @dataProvider data_noncanonical_versions
	 *
	 * @param string $version A version that should be rejected.
	 */
	public function test_rejects_noncanonical_versions( string $version ): void {
		$this->assertFalse( WPORG_Themes_Upload::is_canonical_version( $version ) );
	}

	/**
	 * Data provider of noncanonical versions, including the exploited aliases.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function data_noncanonical_versions(): array {
		return array(
			'empty'        => array( '' ),
			'trailing dot' => array( '1.4.' ),
			'repeated dot' => array( '1..4' ),
			'leading dot'  => array( '.1.4' ),
			'lone dot'     => array( '.' ),
			'non numeric'  => array( '1.4a' ),
			'whitespace'   => array( '1.4 ' ),
			'letters'      => array( 'v1.4' ),
		);
	}

	/**
	 * The download URL must address the exact version, never a period-collapsed alias.
	 */
	public function test_download_url_does_not_collapse_periods(): void {
		$post_id = wp_insert_post(
			array(
				'post_type'   => 'repopackage',
				'post_status' => 'publish',
				'post_title'  => 'Identity Probe',
				'post_name'   => 'identity-probe',
				'post_author' => 1,
			)
		);

		$this->post_ids[] = $post_id;

		$package = new WPORG_Themes_Repo_Package( get_post( $post_id ) );

		$this->assertStringEndsWith(
			'/identity-probe.1.2.3.zip',
			$package->download_url( '1.2.3' )
		);
		$this->assertStringEndsWith(
			'/identity-probe.1.4..zip',
			$package->download_url( '1.4.' )
		);
	}
}
