<?php
/**
 * Tests that a theme version is one stable identity from review to download.
 *
 * @package theme-directory
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;

/**
 * Covers WPORG_Themes_Upload::is_canonical_version(), version_identity_errors(),
 * and the download URL identity.
 *
 * @group upload
 */
class Version_Identity_Test extends TestCase {

	/**
	 * Builds an in-memory repopackage package, no database rows needed.
	 *
	 * With post ID 0 there is no meta, so latest_version() resolves to ''.
	 *
	 * @param string       $post_name The theme slug for the package.
	 * @param string|false $version   Optional. The version to construct with, or false for latest.
	 * @return WPORG_Themes_Repo_Package The package under test.
	 */
	private function create_package( string $post_name, $version = false ): WPORG_Themes_Repo_Package {
		return new WPORG_Themes_Repo_Package(
			new WP_Post(
				(object) array(
					'ID'        => 0,
					'post_name' => $post_name,
				)
			),
			$version
		);
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
			'zero'         => array( '0' ),
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
			'newline'      => array( "1.4\n" ),
		);
	}

	/**
	 * A canonical header matching the SVN directory version is error free.
	 */
	public function test_matching_canonical_version_yields_no_errors(): void {
		$errors = WPORG_Themes_Upload::version_identity_errors( '1.4', '1.4' );

		$this->assertFalse( $errors->has_errors() );
	}

	/**
	 * The falsy canonical header '0' is a real version, not a missing one.
	 */
	public function test_version_zero_header_is_not_reported_as_missing(): void {
		$errors = WPORG_Themes_Upload::version_identity_errors( '0', '0' );

		$this->assertFalse( $errors->has_errors() );
	}

	/**
	 * A canonical header that differs from the SVN directory version is a mismatch.
	 */
	public function test_version_mismatch_detected(): void {
		$errors = WPORG_Themes_Upload::version_identity_errors( '1.2', '1.4' );

		$this->assertSame( array( 'version_mismatch' ), $errors->get_error_codes() );
	}

	/**
	 * The directory identity check must also run for the falsy canonical version '0'.
	 */
	public function test_version_mismatch_detected_for_version_zero_directory(): void {
		$errors = WPORG_Themes_Upload::version_identity_errors( '1.2', '0' );

		$this->assertSame( array( 'version_mismatch' ), $errors->get_error_codes() );
	}

	/**
	 * A missing header is one defect: no self-contradictory mismatch error on top.
	 */
	public function test_missing_header_reports_only_no_version(): void {
		$errors = WPORG_Themes_Upload::version_identity_errors( '', '1.4' );

		$this->assertSame( array( 'no_version' ), $errors->get_error_codes() );
	}

	/**
	 * A noncanonical header is one defect: a mismatch error would just restate it.
	 */
	public function test_noncanonical_header_reports_only_invalid_version(): void {
		$errors = WPORG_Themes_Upload::version_identity_errors( '1.4.', '1.4' );

		$this->assertSame( array( 'invalid_version' ), $errors->get_error_codes() );
	}

	/**
	 * The download URL must address the exact version, never a period-collapsed alias.
	 */
	public function test_download_url_does_not_collapse_periods(): void {
		$package = $this->create_package( 'identity-probe' );

		$this->assertStringEndsWith(
			'/identity-probe.1.2.3.zip',
			$package->download_url( '1.2.3' )
		);
		$this->assertStringEndsWith(
			'/identity-probe.1.4..zip',
			$package->download_url( '1.4.' )
		);
	}

	/**
	 * A package whose version resolves to an empty string must fall back to the
	 * unversioned latest-package URL, not a doubled-period filename.
	 */
	public function test_download_url_omits_empty_version(): void {
		$package = $this->create_package( 'identity-probe-unversioned' );

		$this->assertStringEndsWith(
			'/identity-probe-unversioned.zip',
			$package->download_url()
		);
	}

	/**
	 * The falsy canonical version '0' must address its own package, not the latest one.
	 */
	public function test_download_url_keeps_version_zero(): void {
		$package = $this->create_package( 'identity-probe-zero', '0' );

		$this->assertSame( '0', $package->version );
		$this->assertStringEndsWith( '/identity-probe-zero.0.zip', $package->download_url() );
		$this->assertStringEndsWith( '/identity-probe-zero.0.zip', $package->download_url( '0' ) );
	}
}
