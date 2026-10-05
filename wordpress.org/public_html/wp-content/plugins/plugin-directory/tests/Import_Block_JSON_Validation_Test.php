<?php
/**
 * Tests for block.json validation during plugin imports.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\CLI\Import;

/**
 * Tests that tolerated schema errors do not hide errors that prevent listing.
 *
 * @group import
 */
#[Group( 'import' )]
class Import_Block_JSON_Validation_Test extends TestCase {

	/**
	 * Temporary block.json path.
	 *
	 * @var string
	 */
	private $block_file;

	/**
	 * Create a temporary directory for block metadata.
	 */
	protected function setUp(): void {
		parent::setUp();

		$directory = sys_get_temp_dir() . '/import-block-json-' . wp_generate_uuid4();
		wp_mkdir_p( $directory );
		$this->block_file = $directory . '/block.json';
	}

	/**
	 * Remove temporary files.
	 */
	protected function tearDown(): void {
		if ( file_exists( $this->block_file ) ) {
			wp_delete_file( $this->block_file );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Remove the temporary test directory.
		rmdir( dirname( $this->block_file ) );

		parent::tearDown();
	}

	/**
	 * A missing apiVersion must not mask the conditional missing-script error.
	 *
	 * @dataProvider block_metadata_provider
	 * @param array $metadata       Block metadata to import.
	 * @param int   $expected_count Expected number of imported blocks.
	 */
	#[DataProvider( 'block_metadata_provider' )]
	public function test_block_json_validation( array $metadata, int $expected_count ): void {
		$metadata = array_merge( array( 'name' => 'test/example' ), $metadata );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Write a temporary test fixture.
		file_put_contents( $this->block_file, wp_json_encode( $metadata ) );

		$blocks = Import::find_blocks_in_file( $this->block_file );

		$this->assertCount( $expected_count, $blocks );
		if ( $expected_count ) {
			$this->assertEquals( (object) $metadata, $blocks[0] );
		}
	}

	/**
	 * Cover the script requirement with and without a tolerated apiVersion error.
	 *
	 * @return array
	 */
	public static function block_metadata_provider(): array {
		return array(
			'missing apiVersion and scripts'       => array( array(), 0 ),
			'apiVersion without scripts'           => array( array( 'apiVersion' => 3 ), 0 ),
			'missing apiVersion with script'       => array( array( 'script' => 'file:./index.js' ), 1 ),
			'missing apiVersion with editorScript' => array( array( 'editorScript' => 'file:./index.js' ), 1 ),
			'valid apiVersion with script'         => array(
				array(
					'apiVersion' => 3,
					'script'     => 'file:./index.js',
				),
				1,
			),
			'valid apiVersion with editorScript'   => array(
				array(
					'apiVersion'   => 3,
					'editorScript' => 'file:./index.js',
				),
				1,
			),
		);
	}
}
