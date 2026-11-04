<?php
/**
 * Tests ability detection against the abilities-fixture plugin.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\CLI\Import;
use WordPressdotorg\Plugin_Directory\Tools\Ability_Scanner;

/**
 * Runs Import::find_abilities_in_directory() over tests/fixtures/abilities-fixture-plugin/,
 * a plugin that registers abilities the way popular plugins do, and checks the
 * result against its expected-abilities.json. That file records what WordPress
 * registers at runtime, and is kept as its author maintains it.
 *
 * Fields listed in an ability's "unreadable" exist only at runtime (e.g. a
 * description read from a file), so the scanner is expected to report null.
 *
 * @group import
 */
#[Group( 'import' )]
class Import_Abilities_Fixture_Plugin_Test extends TestCase {

	private const DIR = __DIR__ . '/fixtures/abilities-fixture-plugin';

	/**
	 * Abilities that aren't fully detected yet, and why. Remove an entry once its
	 * test passes; the test fails as a reminder if it's left here.
	 */
	private const KNOWN_GAPS = array();

	/**
	 * The fixture's answer file, once read.
	 *
	 * @var array|null
	 */
	private static ?array $expected = null;
	/**
	 * Abilities found in the fixture plugin, once scanned.
	 *
	 * @var array|null
	 */
	private static ?array $found = null;

	/**
	 * The fixture's answer file.
	 */
	private static function expected(): array {
		return self::$expected ??= json_decode( (string) file_get_contents( self::DIR . '/expected-abilities.json' ), true, 512, JSON_THROW_ON_ERROR ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local fixture file.
	}

	/**
	 * Abilities found in the fixture plugin, keyed by name.
	 */
	private static function found(): array {
		if ( null === self::$found ) {
			self::$found = array();
			foreach ( Import::find_abilities_in_directory( self::DIR ) as $ability ) {
				self::$found[ $ability->name ] = get_object_vars( $ability );
			}
		}
		return self::$found;
	}

	/**
	 * Each expected ability is found, with the expected label and description.
	 *
	 * @param string $name The ability name.
	 * @param array  $spec The ability's entry in expected-abilities.json.
	 *
	 * @dataProvider ability_provider
	 */
	#[DataProvider( 'ability_provider' )]
	public function test_ability_is_detected( string $name, array $spec ): void {
		$want = array(
			'name'    => $name,
			'bundled' => false,
		);
		foreach ( array( 'label', 'description', 'category' ) as $prop ) {
			$want[ $prop ] = in_array( $prop, $spec['unreadable'] ?? array(), true ) ? null : ( $spec[ $prop ] ?? null );
		}
		$found = self::found()[ $name ] ?? null;
		$got   = $found ? array_intersect_key( $found, $want ) : null;
		if ( $got ) {
			ksort( $got );
		}
		ksort( $want );

		if ( isset( self::KNOWN_GAPS[ $name ] ) ) {
			if ( $want === $got ) {
				$this->fail( "$name is now detected; remove it from KNOWN_GAPS." );
			}
			$this->markTestIncomplete( self::KNOWN_GAPS[ $name ] );
		}

		$this->assertSame( $want, $got, "Pattern: {$spec['pattern']}" );
	}

	/**
	 * One case per expected ability, named "pattern: ability-name".
	 */
	public static function ability_provider(): array {
		$cases = array();
		foreach ( self::expected()['abilities'] as $name => $spec ) {
			$cases[ "{$spec['pattern']}: $name" ] = array( $name, $spec );
		}
		ksort( $cases );
		return $cases;
	}

	/**
	 * Nothing listed in must_not_report is reported.
	 */
	public function test_look_alikes_are_not_reported(): void {
		$reported = array_intersect( self::expected()['must_not_report'], array_keys( self::found() ) );
		$this->assertSame( array(), array_values( $reported ) );
	}

	/**
	 * Bundled libraries' abilities are reported, flagged as bundled.
	 */
	public function test_bundled_abilities_are_flagged(): void {
		$vendored = array_keys( self::expected()['vendored']['abilities'] ?? array() );
		$this->assertNotEmpty( $vendored );
		foreach ( $vendored as $name ) {
			$this->assertTrue( self::found()[ $name ]['bundled'] ?? null, "$name should be reported as bundled." );
		}
	}

	/**
	 * Every ability records the file and line it comes from.
	 */
	public function test_sources_are_recorded(): void {
		foreach ( self::found() as $name => $ability ) {
			$this->assertFileExists( self::DIR . '/' . $ability['file'], $name );
			$this->assertIsInt( $ability['line'], $name );
		}
	}

	/**
	 * Exactly the expected ability categories are found, with their labels and descriptions.
	 */
	public function test_categories_are_detected(): void {
		$found = array();
		foreach ( Ability_Scanner::scan_plugin( self::DIR )['categories'] as $category ) {
			$found[ $category->slug ] = array(
				'label'       => $category->label,
				'description' => $category->description,
			);
		}
		// The complete list: the plugin's own categories, and the bundled library's.
		$expected = self::expected();
		$want     = array();
		foreach ( $expected['categories'] + ( $expected['vendored']['categories'] ?? array() ) as $slug => $category ) {
			$want[ $slug ] = array(
				'label'       => $category['label'],
				'description' => $category['description'],
			);
		}
		ksort( $found );
		ksort( $want );
		$this->assertSame( $want, $found );
	}

	/**
	 * Nothing outside the answer file is reported.
	 */
	public function test_nothing_unexpected_is_reported(): void {
		$expected = self::expected();
		$known    = array_merge(
			array_keys( $expected['abilities'] ),
			array_keys( $expected['vendored']['abilities'] ?? array() ),
			array_keys( $expected['requires_woocommerce'] ?? array() ),
			$expected['runtime_only'] ?? array()
		);
		$this->assertSame( array(), array_values( array_diff( array_keys( self::found() ), $known ) ) );
	}
}
