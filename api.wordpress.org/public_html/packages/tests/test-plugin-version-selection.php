<?php
/**
 * Unit tests for Composer_Repository::select_plugin_versions().
 *
 * @package WordPressdotorg\API\Composer
 */

declare( strict_types = 1 );

namespace WordPressdotorg\API\Composer\Tests;

use PHPUnit\Framework\TestCase;
use WordPressdotorg\API\Composer\Composer_Repository;

require_once dirname( __DIR__ ) . '/includes/class-version-normalizer.php';
require_once dirname( __DIR__ ) . '/includes/class-composer-repository.php';

/**
 * Tests for the plugin version-eligibility logic (stable ceiling, dedupe, and fallback).
 *
 * @group packages
 */
class Test_Plugin_Version_Selection extends TestCase {

	/**
	 * Run the selector and return just the normalized version strings, newest first.
	 *
	 * @param array  $tagged   Map of raw SVN tag => download URL.
	 * @param string $stable   The stable tag.
	 * @param string $version  The current version.
	 * @param string $fallback Fallback download URL for the current version.
	 * @return array List of normalized version strings.
	 */
	private function versions( array $tagged, string $stable, string $version, string $fallback = 'FALLBACK' ): array {
		return array_column(
			Composer_Repository::select_plugin_versions( $tagged, $stable, $version, $fallback ),
			'version'
		);
	}

	/**
	 * Test that the history up to the stable tag is served and higher tags are dropped.
	 */
	public function test_keeps_history_up_to_stable(): void {
		$this->assertSame(
			array( '2.0', '1.5', '1.0' ),
			$this->versions(
				array(
					'1.0' => 'a',
					'1.5' => 'b',
					'2.0' => 'c',
					'3.0' => 'd',
				),
				'2.0',
				'2.0'
			)
		);
	}

	/**
	 * Test that a v-prefixed tag above stable is dropped (raw version_compare would keep it).
	 */
	public function test_drops_v_prefixed_higher_tag(): void {
		$this->assertSame(
			array( '2.0' ),
			$this->versions(
				array(
					'2.0'  => 'a',
					'v2.1' => 'b',
				),
				'2.0',
				'2.0'
			)
		);
	}

	/**
	 * Test that 'trunk' is kept as the dev version.
	 */
	public function test_keeps_trunk_dev_version(): void {
		$this->assertContains(
			'dev-trunk',
			$this->versions(
				array(
					'1.0'   => 'a',
					'2.0'   => 'b',
					'trunk' => 'c',
				),
				'2.0',
				'2.0'
			)
		);
	}

	/**
	 * Test that a trunk-stable plugin still serves its tagged history (bounded by the version).
	 */
	public function test_trunk_stable_keeps_history(): void {
		$this->assertSame(
			array( '1.5', '1.0' ),
			$this->versions(
				array(
					'1.0' => 'a',
					'1.5' => 'b',
				),
				'trunk',
				'1.5'
			)
		);
	}

	/**
	 * Test that a capitalized 'Trunk' stable tag is treated as trunk, not as a numeric ceiling.
	 */
	public function test_capitalized_trunk_keeps_history(): void {
		$this->assertSame(
			array( '1.5', '1.0' ),
			$this->versions(
				array(
					'1.0' => 'a',
					'1.5' => 'b',
				),
				'Trunk',
				'1.5'
			)
		);
	}

	/**
	 * Test that an unnormalizable stable tag falls back to the current version as the ceiling.
	 */
	public function test_invalid_stable_tag_uses_current_version_ceiling(): void {
		$this->assertSame(
			array( '1.0', '0.9' ),
			$this->versions(
				array(
					'0.9' => 'a',
					'1.0' => 'b',
					'1.1' => 'c',
				),
				'1.0-final',
				'1.0'
			)
		);
	}

	/**
	 * Test that with neither a usable stable tag nor a usable version, nothing is served.
	 */
	public function test_no_usable_ceiling_returns_empty(): void {
		$this->assertSame(
			array(),
			$this->versions( array( '1.0' => 'a' ), '1.0-final', '1.0-final' )
		);
	}

	/**
	 * Test that a version ahead of the stable tag is not advertised above the ceiling.
	 */
	public function test_version_ahead_of_stable_is_not_advertised(): void {
		$this->assertSame(
			array( '2.0', '1.0' ),
			$this->versions(
				array(
					'1.0' => 'a',
					'2.0' => 'b',
				),
				'2.0',
				'2.1'
			)
		);
	}

	/**
	 * Test that tags Composer treats as one version collapse to a single entry.
	 */
	public function test_collapses_leading_zero_equivalents(): void {
		$this->assertSame(
			array( '2.0', '1.7.5' ),
			$this->versions(
				array(
					'1.7.5'  => 'a',
					'1.7.05' => 'b',
					'2.0'    => 'c',
				),
				'2.0',
				'2.0'
			)
		);
	}

	/**
	 * Test that the stable tag wins its dedupe key, so its own ZIP is served for that version.
	 */
	public function test_stable_tag_wins_dedupe(): void {
		$result = Composer_Repository::select_plugin_versions(
			array(
				'2.0'  => 'benign-zip',
				'2.00' => 'stable-zip',
			),
			'2.00',
			'2.00',
			'FALLBACK'
		);

		$this->assertCount( 1, $result );
		$this->assertSame( 'stable-zip', $result[0]['url'] );
	}

	/**
	 * Test that the current version is offered (via the fallback URL) when its tag is absent.
	 */
	public function test_current_version_added_when_its_tag_is_missing(): void {
		$result = Composer_Repository::select_plugin_versions(
			array( '1.0' => 'a' ),
			'2.0',
			'2.0',
			'FALLBACK'
		);

		$this->assertSame( array( '2.0', '1.0' ), array_column( $result, 'version' ) );
		$this->assertSame( 'FALLBACK', $result[0]['url'] );
	}

	/**
	 * Test that a trunk-stable plugin with a degenerate '0.0' version serves nothing, not a phantom.
	 */
	public function test_degenerate_version_trunk_stable_serves_nothing(): void {
		$this->assertSame(
			array(),
			$this->versions(
				array(
					'1.0' => 'a',
					'2.0' => 'b',
				),
				'trunk',
				'0.0'
			)
		);
	}

	/**
	 * Test that a trunk-stable plugin with a literal 'trunk' version serves nothing.
	 */
	public function test_literal_trunk_version_trunk_stable_serves_nothing(): void {
		$this->assertSame(
			array(),
			$this->versions( array( '1.0' => 'a' ), 'trunk', 'trunk' )
		);
	}

	/**
	 * Test that the fallback offers the stable release, not a phantom of a lagging header version.
	 */
	public function test_fallback_uses_stable_version_not_lagging_header(): void {
		$result = Composer_Repository::select_plugin_versions(
			array( '1.0' => 'a' ),
			'2.0',
			'1.5',
			'STABLE-ZIP'
		);

		$this->assertSame( array( '2.0', '1.0' ), array_column( $result, 'version' ) );
		$this->assertSame( 'STABLE-ZIP', $result[0]['url'] );
	}

	/**
	 * Test that a degenerate '0.0' stable tag (with no usable version) serves nothing.
	 */
	public function test_degenerate_stable_tag_serves_nothing(): void {
		$this->assertSame(
			array(),
			$this->versions(
				array(
					'1.0' => 'a',
					'2.0' => 'b',
				),
				'0.0',
				'0.0'
			)
		);
	}

	/**
	 * Test that a degenerate stable tag falls back to the header version as the ceiling.
	 */
	public function test_degenerate_stable_tag_falls_back_to_version(): void {
		$this->assertSame(
			array( '2.0', '1.0' ),
			$this->versions(
				array(
					'1.0' => 'a',
					'2.0' => 'b',
					'3.0' => 'c',
				),
				'0.0',
				'2.0'
			)
		);
	}
}
