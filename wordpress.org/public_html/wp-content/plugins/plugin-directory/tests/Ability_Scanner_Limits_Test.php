<?php
/**
 * Tests that Tools\Ability_Scanner stays bounded on hostile plugin code.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\Tools\Ability_Scanner;

/**
 * Plugin authors control the code the importer scans, so each case here is
 * built to make a naive scanner run out of time, memory or stack. Each must
 * finish quickly and still find the real ability alongside it.
 *
 * @group import
 */
#[Group( 'import' )]
class Ability_Scanner_Limits_Test extends TestCase {

	private const SECONDS = 10;

	private string $dir;

	protected function setUp(): void {
		$this->dir = sys_get_temp_dir() . '/ability-scanner-limits-' . uniqid();
		mkdir( $this->dir );
	}

	protected function tearDown(): void {
		array_map( 'unlink', glob( $this->dir . '/*' ) );
		rmdir( $this->dir );
	}

	/**
	 * Scan $php as a plugin, next to a file with one real ability, and return
	 * the ability names found, failing if it takes too long.
	 */
	private function scan( string $php ): array {
		file_put_contents( $this->dir . '/hostile.php', "<?php\n" . $php . "\n" );
		file_put_contents( $this->dir . '/real.php', "<?php\nwp_register_ability( 'real/ability', array( 'label' => 'Real', 'description' => 'Real.' ) );\n" );
		$this->assertLessThan( 1048576, filesize( $this->dir . '/hostile.php' ), 'Keep under the 1 MB limit, or the file is skipped unscanned.' );

		$start = microtime( true );
		$names = array_column( array_map( 'get_object_vars', Ability_Scanner::scan( $this->dir ) ), 'name' );
		$this->assertLessThan( self::SECONDS, microtime( true ) - $start, 'Scan took too long.' );

		return $names;
	}

	public function test_huge_sprintf_width_is_not_built(): void {
		$names = $this->scan(
			"wp_register_ability( sprintf( '%999999999s/x', 'a' ), array() );\n"
			. "wp_register_ability( sprintf( '%*s/x', 999999999, 'a' ), array() );\n"
			. "wp_register_ability( sprintf( \"%'a999999999s/x\", 'a' ), array() );\n"
			. "wp_register_ability( sprintf( '%1\$s/%2\$s', 'positional', 'ok' ), array() );"
		);
		sort( $names );
		$this->assertSame( array( 'positional/ok', 'real/ability' ), $names );
	}

	public function test_doubling_concatenation_is_capped(): void {
		$php = "\$s0 = 'aaaaaaaaaaaaaaaa';\n";
		for ( $i = 1; $i <= 40; $i++ ) {
			$php .= sprintf( "\$s%d = \$s%d . \$s%d;\n", $i, $i - 1, $i - 1 );
		}
		$php  .= "wp_register_ability( \$s40 . '/x', array() );\nwp_register_ability( 'same/file', array() );";
		$names = $this->scan( $php );
		sort( $names );
		$this->assertSame( array( 'real/ability', 'same/file' ), $names );
	}

	public function test_doubling_arrays_through_constants_are_capped(): void {
		$php = "class D {\n\tconst A0 = array( 'x', 'x', 'x', 'x' );\n";
		for ( $i = 1; $i <= 40; $i++ ) {
			$php .= sprintf( "\tconst A%d = [ ...self::A%d, ...self::A%d ];\n", $i, $i - 1, $i - 1 );
		}
		$php .= "\tconst M = array_merge( self::A40, self::A40 );\n}\n";
		$php .= "foreach ( D::A40 as \$x ) { wp_register_ability( \$x . '/x', array() ); }\n";
		$php .= "wp_register_ability( count( array_merge( D::M, D::M ) ) . '/x', array() );";
		$this->assertSame( array( 'real/ability' ), $this->scan( $php ) );
	}

	public function test_deeply_nested_arrays(): void {
		$depth = 50000;
		$php   = "wp_register_ability( \$dynamic, array() );\n\$a = " . str_repeat( 'array( ', $depth ) . str_repeat( ' )', $depth ) . ";\n";
		$php  .= "\$b = " . str_repeat( '[', $depth ) . str_repeat( ']', $depth ) . ";";
		$this->assertContains( 'real/ability', $this->scan( $php ) );
	}

	public function test_many_definition_arrays(): void {
		$php = "wp_register_ability( \$dynamic, array() );\n\$defs = array(\n";
		for ( $i = 0; $i < 7000; $i++ ) {
			$php .= "\t'gen/ability-$i' => array( 'label' => 'L', 'description' => 'D', 'nested' => array( array( array( 'x' => 'y' ) ) ) ),\n";
		}
		$php .= ");";
		$this->assertContains( 'real/ability', $this->scan( $php ) );
	}

	public function test_long_chain_of_variables_and_calls(): void {
		$php = "\$v0 = 'a/b';\n";
		for ( $i = 1; $i < 5000; $i++ ) {
			$php .= sprintf( "\$v%d = f%d( \$v%d );\nfunction f%d( \$x ) { return g( \$x ); }\n", $i, $i, $i - 1, $i );
		}
		$php .= "function g( \$x ) { return \$x; }\n";
		for ( $i = 0; $i < 200; $i++ ) {
			$php .= "wp_register_ability( \$v4999, array() );\n";
		}
		$this->assertContains( 'real/ability', $this->scan( $php ) );
	}

	public function test_recursive_functions_and_constants(): void {
		$php = "function loop( \$x ) { return loop( \$x ); }\n"
			. "class A { const X = B::Y; public function m() { return \$this->m(); } }\n"
			. "class B { const Y = A::X; }\n"
			. "define( 'C', C );\n"
			. "wp_register_ability( loop( 'a/b' ), array() );\n"
			. "wp_register_ability( A::X, array() );\n"
			. "wp_register_ability( ( new A() )->m(), array() );\n"
			. "wp_register_ability( C, array() );";
		$this->assertSame( array( 'real/ability' ), $this->scan( $php ) );
	}
}
