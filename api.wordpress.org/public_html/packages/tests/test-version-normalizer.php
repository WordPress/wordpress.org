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
