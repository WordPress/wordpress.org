<?php
/**
 * Tests for ability detection in Import::find_abilities_in_directory().
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\CLI\Import;

/**
 * Runs ability detection over every scenario in tests/fixtures/abilities/.
 *
 * Each scenario is a directory laid out like a plugin, plus an expected.json
 * listing the abilities that should be found. See the README in that
 * directory for the format.
 *
 * @group import
 */
#[Group( 'import' )]
class Import_Abilities_Test extends TestCase {

	private const FIXTURES = __DIR__ . '/fixtures/abilities';

	/**
	 * Each scenario's abilities are found, with the expected labels and descriptions.
	 *
	 * @param string $dir The scenario's directory.
	 *
	 * @dataProvider scenario_provider
	 */
	#[DataProvider( 'scenario_provider' )]
	public function test_scenario( string $dir ): void {
		$expected = json_decode( (string) file_get_contents( "$dir/expected.json" ), true, 512, JSON_THROW_ON_ERROR ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local fixture file.

		$want = self::normalize( $expected['abilities'] ?? array() );
		$got  = self::normalize(
			array_map( 'get_object_vars', Import::find_abilities_in_directory( $dir ) )
		);

		if ( ! empty( $expected['todo'] ) ) {
			if ( $want === $got ) {
				$this->fail( 'This scenario now passes; remove "todo" from its expected.json.' );
			}
			$this->markTestIncomplete( $expected['todo'] );
		}

		$this->assertSame( $want, $got, $expected['description'] ?? '' );
	}

	/**
	 * Abilities record the file and line they're registered at.
	 */
	public function test_source_is_recorded(): void {
		$abilities = Import::find_abilities_in_directory( self::FIXTURES . '/basic-inline-args' );
		$this->assertCount( 1, $abilities );
		$this->assertSame( 'plugin.php', $abilities[0]->file );
		$this->assertSame( 7, $abilities[0]->line );
	}

	/**
	 * Abilities in a vendor/ package are reported and flagged as bundled. Built
	 * in a temporary directory, because the repository ignores vendor/ folders.
	 */
	public function test_vendor_abilities_are_bundled(): void {
		$dir = sys_get_temp_dir() . '/abilities-vendor-' . uniqid();
		mkdir( $dir . '/vendor/acme/tools', 0777, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- A throwaway directory for the test, not WordPress content.
		file_put_contents( $dir . '/plugin.php', "<?php\nwp_register_ability( 'mine/own', array( 'label' => 'Mine' ) );\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- A throwaway directory for the test, not WordPress content.
		file_put_contents( $dir . '/vendor/acme/tools/register.php', "<?php\nwp_register_ability( 'acme/tool', array( 'label' => 'Tool' ) );\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- A throwaway directory for the test, not WordPress content.
		$bundled = array_column( array_map( 'get_object_vars', Import::find_abilities_in_directory( $dir ) ), 'bundled', 'name' );
		unlink( $dir . '/vendor/acme/tools/register.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- A throwaway directory for the test, not WordPress content.
		unlink( $dir . '/plugin.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- A throwaway directory for the test, not WordPress content.
		array_map( 'rmdir', array( "$dir/vendor/acme/tools", "$dir/vendor/acme", "$dir/vendor", $dir ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- A throwaway directory for the test, not WordPress content.

		$this->assertSame(
			array(
				'acme/tool' => true,
				'mine/own'  => false,
			),
			array_merge( array( 'acme/tool' => null ), $bundled )
		);
	}

	/**
	 * One case per scenario directory, named after the directory.
	 */
	public static function scenario_provider(): array {
		$cases = array();
		foreach ( glob( self::FIXTURES . '/*/expected.json' ) as $file ) {
			$dir                       = dirname( $file );
			$cases[ basename( $dir ) ] = array( $dir );
		}
		ksort( $cases );
		return $cases;
	}

	/**
	 * Fill in missing properties and sort by name, so scenarios only need to
	 * list what they care about and file order doesn't matter.
	 *
	 * @param array[] $abilities Abilities, as arrays.
	 */
	private static function normalize( array $abilities ): array {
		$abilities = array_map(
			static fn( array $ability ) => array(
				'name'        => $ability['name'] ?? null,
				'label'       => $ability['label'] ?? null,
				'description' => $ability['description'] ?? null,
				'bundled'     => $ability['bundled'] ?? false,
			),
			$abilities
		);
		usort( $abilities, static fn( $a, $b ) => strcmp( (string) $a['name'], (string) $b['name'] ) );
		return $abilities;
	}
}
