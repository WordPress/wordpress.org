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
	 * Create a temporary directory for block metadata and mock schema requests.
	 */
	protected function setUp(): void {
		parent::setUp();

		$directory = sys_get_temp_dir() . '/import-block-json-' . wp_generate_uuid4();
		wp_mkdir_p( $directory );
		$this->block_file = $directory . '/block.json';

		add_filter( 'pre_http_request', array( $this, 'mock_schema_request' ), 10, 3 );
	}

	/**
	 * Remove temporary files and the schema request mock.
	 */
	protected function tearDown(): void {
		remove_filter( 'pre_http_request', array( $this, 'mock_schema_request' ), 10 );

		if ( file_exists( $this->block_file ) ) {
			wp_delete_file( $this->block_file );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Remove the temporary test directory.
		rmdir( dirname( $this->block_file ) );

		parent::tearDown();
	}

	/**
	 * Serve a fixed copy of https://schemas.wp.org/trunk/block.json.
	 *
	 * @param false|array|WP_Error $preempt Existing short-circuit response.
	 * @param array                $args    HTTP request arguments.
	 * @param string               $url     HTTP request URL.
	 * @return false|array|WP_Error
	 */
	public function mock_schema_request( $preempt, $args, $url ) {
		if ( 'https://schemas.wp.org/trunk/block.json' !== $url ) {
			return $preempt;
		}

		return array(
			'headers'  => array(),
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Read the fixed test schema.
			'body'     => file_get_contents( __DIR__ . '/fixtures/block-schema.json' ),
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => null,
		);
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
	 * A missing required name is rejected even when a script is present.
	 */
	public function test_missing_name_rejects_block(): void {
		$metadata = array(
			'apiVersion' => 3,
			'title'      => 'Example',
			'script'     => 'file:./index.js',
		);
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Write a temporary test fixture.
		file_put_contents( $this->block_file, wp_json_encode( $metadata ) );

		$this->assertCount( 0, Import::find_blocks_in_file( $this->block_file ) );
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
