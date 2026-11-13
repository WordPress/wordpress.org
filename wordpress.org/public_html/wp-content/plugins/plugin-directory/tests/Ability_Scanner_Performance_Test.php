<?php
/**
 * Tests for how much work ability detection does.
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
 * The scanner indexes only the files a scan needs, and the importer only scans when
 * the stable code may have changed.
 *
 * @group import
 */
#[Group( 'import' )]
class Ability_Scanner_Performance_Test extends TestCase {

	/**
	 * The plugin directory being scanned.
	 *
	 * @var string
	 */
	private string $dir;

	/**
	 * Create an empty plugin directory.
	 */
	protected function setUp(): void {
		$this->dir = sys_get_temp_dir() . '/ability-scanner-performance-' . uniqid();
		mkdir( $this->dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- A throwaway directory for the test, not WordPress content.
	}

	/**
	 * Remove the plugin directory.
	 */
	protected function tearDown(): void {
		array_map( 'unlink', glob( $this->dir . '/*' ) );
		rmdir( $this->dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- A throwaway directory for the test, not WordPress content.
	}

	/**
	 * A wrapper whose name is common across the plugin is still followed to its calls.
	 *
	 * Modelled on Formidable Forms, whose abilities go through a register() helper in a
	 * plugin where hundreds of files use the word "register". Reading every one of those
	 * files used up the per-call budget before the helper's calls were reached.
	 */
	public function test_wrapper_with_a_common_name_is_followed_to_its_calls(): void {
		$this->write(
			'a-helper.php',
			"class Lazy_Helper {\n\tpublic static function register( \$name, \$args ) {\n\t\twp_register_ability( \$name, \$args );\n\t}\n}"
		);

		// Files that use the name without calling it: as a property and a hook name.
		$filler = str_repeat( "\$this->register = array( 'a' => 1, 'b' => 2, 'c' => 3, 'd' => 4, 'e' => 5 );\nadd_action( 'register_form', 'noop' );\n", 120 );
		for ( $i = 0; $i < 300; $i++ ) {
			$this->write( sprintf( 'm-filler-%03d.php', $i ), "function lazy_filler_$i() {\n$filler}" );
		}

		$this->write(
			'z-abilities.php',
			"Lazy_Helper::register( 'lazy/found', array( 'label' => 'Found', 'description' => 'Found through the helper.' ) );"
		);

		$this->assertSame(
			array( 'lazy/found' ),
			array_map( static fn( $ability ) => $ability->name, Ability_Scanner::scan( $this->dir ) )
		);
	}

	/**
	 * When the importer needs to scan for abilities.
	 *
	 * @return array[]
	 */
	public static function data_scan_decisions(): array {
		$current = (string) Ability_Scanner::VERSION;
		return array(
			'never scanned'                        => array( false, '1.0', '1.0', '', true ),
			'stable tag changed in this commit'    => array( true, '1.0', '1.0', $current, true ),
			'stable tag now points elsewhere'      => array( false, '1.1', '1.0', $current, true ),
			'scanned with another scanner version' => array( false, '1.0', '1.0', '0', true ),
			'only other tags or trunk changed'     => array( false, '1.0', '1.0', $current, false ),
		);
	}

	/**
	 * The importer scans only when the stable code may have changed, or the scanner has.
	 *
	 * @param bool   $touches_stable_tag Whether the import includes the stable tag.
	 * @param string $stable_tag         The stable tag now.
	 * @param string $current_stable_tag The stable tag as of the last import.
	 * @param string $scanned_with       The scanner version of the last scan.
	 * @param bool   $expected           Whether a scan is needed.
	 */
	#[DataProvider( 'data_scan_decisions' )]
	public function test_scans_only_when_the_stable_code_may_have_changed( bool $touches_stable_tag, string $stable_tag, string $current_stable_tag, string $scanned_with, bool $expected ): void {
		$this->assertSame( $expected, Import::should_scan_abilities( $touches_stable_tag, $stable_tag, $current_stable_tag, $scanned_with ) );
	}

	/**
	 * Write a PHP file into the plugin directory.
	 *
	 * @param string $name File name.
	 * @param string $php  Code, without the opening tag.
	 */
	private function write( string $name, string $php ): void {
		file_put_contents( $this->dir . '/' . $name, "<?php\n" . $php . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- A throwaway directory for the test, not WordPress content.
	}
}
