<?php
/**
 * Tests for Block_Plugin_Checker::check_for_translation_function().
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\CLI\Block_Plugin_Checker;

/**
 * @group block-validator
 */
class Block_Plugin_Checker_Translation_Test extends TestCase {

	/**
	 * Build a checker with the given recorded PHP function calls and parsed blocks,
	 * run the translation check, and return any warnings it produced for that check.
	 *
	 * @param string[] $function_names Function names to seed into $php_function_calls.
	 * @param array    $blocks         Blocks to seed into $blocks (each may have a textdomain).
	 * @return array
	 */
	private function run_check( array $function_names, array $blocks ): array {
		$reflection = new ReflectionClass( Block_Plugin_Checker::class );
		$checker    = $reflection->newInstanceWithoutConstructor();

		/*
		 * find_called_functions_in_file() records each call as [ name, line, file ];
		 * the check pulls index 0 via wp_list_pluck(), so mirror that shape here.
		 */
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

	public function test_direct_wp_set_script_translations_call_passes() {
		$warnings = $this->run_check(
			array( 'wp_set_script_translations' ),
			array()
		);
		$this->assertEmpty( $warnings );
	}

	public function test_register_block_type_with_textdomain_passes() {
		$warnings = $this->run_check(
			array( 'register_block_type' ),
			array( (object) array( 'name' => 'plugin/main', 'textdomain' => 'plugin' ) )
		);
		$this->assertEmpty( $warnings );
	}

	public function test_register_block_type_from_metadata_with_textdomain_passes() {
		$warnings = $this->run_check(
			array( 'register_block_type_from_metadata' ),
			array( (object) array( 'name' => 'plugin/main', 'textdomain' => 'plugin' ) )
		);
		$this->assertEmpty( $warnings );
	}

	public function test_textdomain_on_any_block_is_sufficient() {
		$warnings = $this->run_check(
			array( 'register_block_type' ),
			array(
				(object) array( 'name' => 'plugin/a' ),
				(object) array( 'name' => 'plugin/b', 'textdomain' => 'plugin' ),
			)
		);
		$this->assertEmpty( $warnings );
	}

	public function test_register_block_type_without_textdomain_warns() {
		$warnings = $this->run_check(
			array( 'register_block_type' ),
			array( (object) array( 'name' => 'plugin/main' ) )
		);
		$this->assertCount( 1, $warnings );
	}

	public function test_textdomain_in_block_json_without_register_call_warns() {
		$warnings = $this->run_check(
			array(),
			array( (object) array( 'name' => 'plugin/main', 'textdomain' => 'plugin' ) )
		);
		$this->assertCount( 1, $warnings );
	}

	public function test_no_translation_signals_warns() {
		$warnings = $this->run_check( array(), array() );
		$this->assertCount( 1, $warnings );
	}
}
