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

/**
 * Runs Import::find_abilities_in_directory() over tests/fixtures/abilities-fixture-plugin/,
 * a plugin that registers abilities the way popular plugins do, and checks the
 * result against its expected-abilities.json. That file records what WordPress
 * registers at runtime, and is kept as its author maintains it.
 *
 * Category isn't compared; the directory doesn't list it.
 *
 * Per ability, an optional "static" object overrides runtime values a scanner
 * can't read from the code (e.g. a label the plugin falls back to at runtime).
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
	private const KNOWN_GAPS = array(
		'abilities-fixture/search-orders' => 'wrapper-functions: the description is built at runtime when missing (needs a "static" override in expected-abilities.json).',
	);

	private static ?array $expected = null;
	private static ?array $found    = null;

	private static function expected(): array {
		return self::$expected ??= json_decode( (string) file_get_contents( self::DIR . '/expected-abilities.json' ), true, 512, JSON_THROW_ON_ERROR );
	}

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
	 * @dataProvider ability_provider
	 */
	#[DataProvider( 'ability_provider' )]
	public function test_ability_is_detected( string $name, array $spec ): void {
		$want = array( 'name' => $name );
		foreach ( array( 'label', 'description' ) as $prop ) {
			$want[ $prop ] = array_key_exists( $prop, $spec['static'] ?? array() ) ? $spec['static'][ $prop ] : ( $spec[ $prop ] ?? null );
		}
		$got = self::found()[ $name ] ?? null;

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

	public function test_look_alikes_are_not_reported(): void {
		$reported = array_intersect( self::expected()['must_not_report'], array_keys( self::found() ) );
		$this->assertSame( array(), array_values( $reported ) );
	}

	/**
	 * Everything reported must be in the answer file. Vendored, runtime-only and
	 * WooCommerce-dependent abilities are allowed until we decide whether to list them.
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
