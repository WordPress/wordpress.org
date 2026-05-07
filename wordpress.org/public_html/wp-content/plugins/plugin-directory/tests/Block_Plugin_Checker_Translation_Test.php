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
	 * Build a checker with the given recorded PHP function calls, parsed blocks, and
	 * metadata-registration cache value, run the translation check, and return any
	 * warnings it produced for that check.
	 *
	 * @param string[] $function_names              Function names to seed into $php_function_calls.
	 * @param array    $blocks                      Blocks to seed into $blocks.
	 * @param bool     $registers_block_from_metadata Pre-seeded result for the metadata-registration helper.
	 * @return array
	 */
	private function run_check( array $function_names, array $blocks, bool $registers_block_from_metadata = false ): array {
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

		$cache_prop = $reflection->getProperty( 'registers_block_from_metadata' );
		$cache_prop->setAccessible( true );
		$cache_prop->setValue( $checker, $registers_block_from_metadata );

		$checker->check_for_translation_function();

		return $checker->get_results( 'warning', 'check_for_translation_function' );
	}

	/**
	 * A direct wp_set_script_translations() call satisfies the check (existing behavior).
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
	 * automatic wp_set_script_translations() registration since WP 5.7.
	 */
	public function test_metadata_registration_with_textdomain_passes() {
		$warnings = $this->run_check(
			array(),
			array(
				(object) array(
					'name'       => 'plugin/main',
					'textdomain' => 'plugin',
				),
			),
			true
		);
		$this->assertEmpty( $warnings );
	}

	/**
	 * Multiple blocks: a textdomain on any one of them is enough to satisfy the check.
	 */
	public function test_textdomain_on_any_block_is_sufficient() {
		$warnings = $this->run_check(
			array(),
			array(
				(object) array( 'name' => 'plugin/a' ),
				(object) array(
					'name'       => 'plugin/b',
					'textdomain' => 'plugin',
				),
			),
			true
		);
		$this->assertEmpty( $warnings );
	}

	/**
	 * Metadata registration without any block.json textdomain still warns - core won't
	 * auto-register translations, so the original guidance is correct.
	 */
	public function test_metadata_registration_without_textdomain_warns() {
		$warnings = $this->run_check(
			array(),
			array( (object) array( 'name' => 'plugin/main' ) ),
			true
		);
		$this->assertCount( 1, $warnings );
	}

	/**
	 * The classic register_block_type( 'ns/name', $args ) form does not auto-register
	 * translations, so a stray block.json textdomain elsewhere in the plugin must not
	 * suppress the warning.
	 */
	public function test_classic_register_block_type_with_stray_textdomain_warns() {
		$warnings = $this->run_check(
			array(),
			array(
				(object) array(
					'name'       => 'plugin/main',
					'textdomain' => 'plugin',
				),
			),
			false
		);
		$this->assertCount( 1, $warnings );
	}

	/**
	 * A textdomain in block.json with no register call won't actually load translations,
	 * so the warning should still fire.
	 */
	public function test_textdomain_in_block_json_without_register_call_warns() {
		$warnings = $this->run_check(
			array(),
			array(
				(object) array(
					'name'       => 'plugin/main',
					'textdomain' => 'plugin',
				),
			),
			false
		);
		$this->assertCount( 1, $warnings );
	}

	/**
	 * No translation signals at all: warning expected.
	 */
	public function test_no_translation_signals_warns() {
		$warnings = $this->run_check( array(), array(), false );
		$this->assertCount( 1, $warnings );
	}
}
