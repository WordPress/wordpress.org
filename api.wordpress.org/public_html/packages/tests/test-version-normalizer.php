<?php
/**
 * Unit tests for the Version_Normalizer class.
 *
 * @package WordPressdotorg\API\Composer
 */

declare( strict_types = 1 );

namespace WordPressdotorg\API\Composer\Tests;

use PHPUnit\Framework\TestCase;
use WordPressdotorg\API\Composer\Version_Normalizer;

require_once dirname( __DIR__ ) . '/includes/class-version-normalizer.php';

/**
 * Tests for Version_Normalizer.
 *
 * @group packages
 */
class Test_Version_Normalizer extends TestCase {

	/**
	 * Test that valid version strings are normalized correctly.
	 *
	 * @dataProvider data_valid_versions
	 *
	 * @param string $input    The raw version string.
	 * @param string $expected The expected normalized output.
	 */
	public function test_normalize_valid_versions( string $input, string $expected ): void {
		$this->assertSame( $expected, Version_Normalizer::normalize( $input ) );
	}

	/**
	 * Data provider for valid version strings.
	 *
	 * @return array Test cases.
	 */
	public function data_valid_versions(): array {
		return array(
			'simple major.minor'        => array( '1.0', '1.0' ),
			'three segments'            => array( '1.2.3', '1.2.3' ),
			'four segments'             => array( '1.2.3.4', '1.2.3.4' ),
			'major only'                => array( '5', '5.0' ),
			'large version'             => array( '12345.6', '12345.6' ),
			'leading v stripped'        => array( 'v1.2.3', '1.2.3' ),
			'beta suffix'               => array( '7.0-beta1', '7.0-beta1' ),
			'RC suffix'                 => array( '7.0-RC1', '7.0-rc1' ),
			'alpha suffix'              => array( '2.0-alpha3', '2.0-alpha3' ),
			'beta with dot separator'   => array( '3.0.beta2', '3.0-beta2' ),
			'short suffix a'            => array( '1.0a1', '1.0-alpha1' ),
			'short suffix b'            => array( '1.0b2', '1.0-beta2' ),
			'short suffix p'            => array( '1.0p1', '1.0-patch1' ),
			'short suffix pl'           => array( '1.0pl1', '1.0-patch1' ),
			'dev suffix'                => array( '2.0-dev1', '2.0-dev1' ),
			'rc lowercase'              => array( '5.0-rc2', '5.0-rc2' ),
			'beta no number'            => array( '3.0-beta', '3.0-beta' ),
			'trunk'                     => array( 'trunk', 'dev-trunk' ),
			'trunk uppercase'           => array( 'TRUNK', 'dev-trunk' ),
			'integer version'           => array( '10', '10.0' ),
			'two segment with beta'     => array( '6.9-beta3', '6.9-beta3' ),
			'three segment with RC'     => array( '6.9.1-RC1', '6.9.1-rc1' ),
			'version with v and suffix' => array( 'v2.0-beta1', '2.0-beta1' ),
			'zero patch kept'           => array( '1.2.0', '1.2.0' ),
			'zero patch and minor kept' => array( '1.0.0', '1.0.0' ),
			'zero third segment kept'   => array( '8.7.0.1', '8.7.0.1' ),
			'zero fourth segment kept'  => array( '2.1.3.0', '2.1.3.0' ),
			'all zeros kept'            => array( '0.0.0', '0.0.0' ),
			'zero minor kept'           => array( '4.0', '4.0' ),
			'zero patch with suffix'    => array( '3.1.0-rc1', '3.1.0-rc1' ),
		);
	}

	/**
	 * Test that the dedupe key matches for versions Composer compares as equal.
	 *
	 * @dataProvider data_equivalent_versions
	 *
	 * @param string $a One normalized version.
	 * @param string $b A version Composer treats as equal to $a.
	 */
	public function test_dedupe_key_matches_equivalent_versions( string $a, string $b ): void {
		$this->assertSame(
			Version_Normalizer::dedupe_key( Version_Normalizer::normalize( $a ) ),
			Version_Normalizer::dedupe_key( Version_Normalizer::normalize( $b ) )
		);
	}

	/**
	 * Data provider for versions Composer compares as equal.
	 *
	 * @return array Test cases.
	 */
	public function data_equivalent_versions(): array {
		return array(
			'trailing zero'         => array( '1.54', '1.54.0' ),
			'two trailing zeros'    => array( '1.6', '1.6.0.0' ),
			'four segment zero'     => array( '9.1.4', '9.1.4.0' ),
			'leading v'             => array( '1.0', 'v1.0' ),
			'zero with suffix'      => array( '3.1-rc1', '3.1.0-rc1' ),
			'short and long suffix' => array( '1.0-a1', '1.0-alpha1' ),
			'leading zero patch'    => array( '1.7.5', '1.7.05' ),
			'leading zero major'    => array( '1', '01' ),
			'leading zero suffix'   => array( '1.0-rc1', '1.0-rc01' ),
		);
	}

	/**
	 * Test that the dedupe key differs for versions Composer compares as distinct.
	 */
	public function test_dedupe_key_separates_hotfix_versions(): void {
		$this->assertNotSame(
			Version_Normalizer::dedupe_key( Version_Normalizer::normalize( '8.7.1' ) ),
			Version_Normalizer::dedupe_key( Version_Normalizer::normalize( '8.7.0.1' ) )
		);
	}

	/**
	 * Test that hotfix releases don't collapse onto the release they patch.
	 *
	 * WordPress hotfixes use an x.y.0.z tag, which has to stay distinct from x.y.z — otherwise both
	 * are served under one Composer version and a pinned requirement resolves to the wrong zip.
	 */
	public function test_normalize_keeps_hotfix_versions_distinct(): void {
		$this->assertNotSame(
			Version_Normalizer::normalize( '8.7.1' ),
			Version_Normalizer::normalize( '8.7.0.1' )
		);
	}

	/**
	 * Test that invalid version strings return false.
	 *
	 * @dataProvider data_invalid_versions
	 *
	 * @param string $input The raw version string.
	 */
	public function test_normalize_invalid_versions( string $input ): void {
		$this->assertFalse( Version_Normalizer::normalize( $input ) );
	}

	/**
	 * Data provider for invalid version strings.
	 *
	 * @return array Test cases.
	 */
	public function data_invalid_versions(): array {
		return array(
			'empty string'       => array( '' ),
			'whitespace only'    => array( '   ' ),
			'non-numeric'        => array( 'abc' ),
			'special characters' => array( '1.0@beta' ),
			'too many digits'    => array( '123456.0' ),
			'path traversal'     => array( '../1.0' ),
			'with spaces'        => array( '1. 0' ),
		);
	}

	/**
	 * Test that leading/trailing whitespace is trimmed.
	 */
	public function test_normalize_trims_whitespace(): void {
		$this->assertSame( '1.0', Version_Normalizer::normalize( '  1.0  ' ) );
	}

	/**
	 * Test that integer input (from PHP array keys) is handled.
	 */
	public function test_normalize_integer_cast(): void {
		$this->assertSame( '10.0', Version_Normalizer::normalize( '10' ) );
	}
}
