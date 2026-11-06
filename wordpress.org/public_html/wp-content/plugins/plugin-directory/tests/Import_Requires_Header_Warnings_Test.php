<?php
/**
 * Tests for Import::requires_header_warnings().
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\CLI\Import;

/**
 * Covers the import warnings for the Requires at least and Requires PHP plugin headers.
 *
 * @group import
 */
#[Group( 'import' )]
class Import_Requires_Header_Warnings_Test extends TestCase {

	/**
	 * The plugin headers are checked on their own and against the readme.
	 *
	 * @dataProvider headers_provider
	 *
	 * @param array $headers  The RequiresWP and RequiresPHP plugin headers.
	 * @param array $readme   The readme's requires and requires_php fields.
	 * @param array $expected The expected warnings.
	 * @return void
	 */
	#[DataProvider( 'headers_provider' )]
	public function test_requires_header_warnings( array $headers, array $readme, array $expected ): void {
		$this->assertSame( $expected, Import::requires_header_warnings( (object) $headers, (object) $readme ) );
	}

	/**
	 * Supplies plugin headers, readme fields, and the warnings they should produce.
	 *
	 * @return array<string, array<int, array>>
	 */
	public static function headers_provider(): array {
		return array(
			'matching, with trailing .0'  => array(
				array(
					'RequiresWP'  => '6.0',
					'RequiresPHP' => '7.4',
				),
				array(
					'requires'     => '6.0.0',
					'requires_php' => '7.4',
				),
				array(),
			),
			'no readme fields to compare' => array(
				array(
					'RequiresWP'  => '6.0',
					'RequiresPHP' => '7.4',
				),
				array(
					'requires'     => '',
					'requires_php' => '',
				),
				array(),
			),
			'header newer than trunk'     => array(
				array(
					'RequiresWP'  => '99.0',
					'RequiresPHP' => '',
				),
				array(
					'requires'     => '6.0',
					'requires_php' => '',
				),
				array( 'plugin_requires_header_invalid' => '99.0' ),
			),
			'headers not versions'        => array(
				array(
					'RequiresWP'  => '6.0-beta',
					'RequiresPHP' => '8',
				),
				array(
					'requires'     => '',
					'requires_php' => '',
				),
				array(
					'plugin_requires_header_invalid'     => '6.0-beta',
					'plugin_requires_php_header_invalid' => '8',
				),
			),
			'headers differ from readme'  => array(
				array(
					'RequiresWP'  => '6.2',
					'RequiresPHP' => '8.0',
				),
				array(
					'requires'     => '6.0',
					'requires_php' => '7.4',
				),
				array(
					'requires_header_mismatch'     => array(
						'plugin' => '6.2',
						'readme' => '6.0',
					),
					'requires_php_header_mismatch' => array(
						'plugin' => '8.0',
						'readme' => '7.4',
					),
				),
			),
		);
	}
}
