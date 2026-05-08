<?php
/**
 * Tests for Block_Plugin_Checker::check_for_translation_function().
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\CLI\Block_Plugin_Checker;

/**
 * Verifies the rules under which the block validator's translation check fires.
 *
 * @group block-validator
 */
#[CoversMethod( Block_Plugin_Checker::class, 'check_for_translation_function' )]
class Block_Plugin_Checker_Translation_Test extends TestCase {

	/**
	 * Build a checker with the given recorded PHP function calls and parsed blocks,
	 * run the translation check, and return any warnings it produced.
	 *
	 * @param string[] $function_names Function names to seed into $php_function_calls.
	 * @param array    $blocks         Blocks to seed into $blocks.
	 * @return array
	 */
	private function run_check( array $function_names, array $blocks ): array {
		$reflection = new ReflectionClass( Block_Plugin_Checker::class );
		$checker    = $reflection->newInstanceWithoutConstructor();

		$calls = array_map(
			fn( $name ) => array( $name, 1, 'fake.php' ),
			$function_names
		);

		$calls_prop = $reflection->getProperty( 'php_function_calls' );
		$calls_prop->setAccessible( true );
		$calls_prop->setValue( $checker, $calls );

		$blocks_prop = $reflection->getProperty( 'blocks' );
		$blocks_prop->setAccessible( true );
		$blocks_prop->setValue( $checker, $blocks );

		$checker->check_for_translation_function();

		return $checker->get_results( 'warning', 'check_for_translation_function' );
	}

	/**
	 * A direct wp_set_script_translations() call satisfies the check.
	 */
	public function test_direct_wp_set_script_translations_call_passes() {
		$warnings = $this->run_check(
			array( 'wp_set_script_translations' ),
			array()
		);
		$this->assertEmpty( $warnings );
	}

	/**
	 * Metadata-based registration paired with a textdomain in block.json triggers core's
	 * automatic wp_set_script_translations() registration.
	 */
	public function test_block_registration_with_textdomain_passes() {
		$warnings = $this->run_check(
			array( 'register_block_type_from_metadata' ),
			array(
				(object) array(
					'name'       => 'plugin/main',
					'textdomain' => 'plugin',
				),
			)
		);
		$this->assertEmpty( $warnings );
	}

	/**
	 * Block registration without any block.json textdomain still warns — core won't
	 * auto-register translations.
	 */
	public function test_block_registration_without_textdomain_warns() {
		$warnings = $this->run_check(
			array( 'register_block_type_from_metadata' ),
			array( (object) array( 'name' => 'plugin/main' ) )
		);
		$this->assertCount( 1, $warnings );
	}

	/**
	 * No translation signals at all: warning expected.
	 */
	public function test_no_translation_signals_warns() {
		$warnings = $this->run_check( array(), array() );
		$this->assertCount( 1, $warnings );
	}

	/**
	 * End-to-end against a real fixture plugin: a `register_block_type_from_metadata( __DIR__ )`
	 * call plus a `block.json` declaring a `textdomain` should not trigger the warning.
	 */
	public function test_fixture_plugin_with_metadata_textdomain_passes() {
		$fixture_path = __DIR__ . '/fixtures/block-plugin-with-textdomain';

		$reflection = new ReflectionClass( Block_Plugin_Checker::class );
		$checker    = $reflection->newInstanceWithoutConstructor();

		$path_prop = $reflection->getProperty( 'path_to_plugin' );
		$path_prop->setAccessible( true );
		$path_prop->setValue( $checker, $fixture_path );

		$blocks_prop = $reflection->getProperty( 'blocks' );
		$blocks_prop->setAccessible( true );
		$blocks_prop->setValue( $checker, $checker->find_blocks( $fixture_path ) );

		$calls_prop = $reflection->getProperty( 'php_function_calls' );
		$calls_prop->setAccessible( true );
		$calls_prop->setValue( $checker, $checker->find_php_functions( $fixture_path ) );

		$checker->check_for_translation_function();

		$this->assertEmpty( $checker->get_results( 'warning', 'check_for_translation_function' ) );
	}
}
