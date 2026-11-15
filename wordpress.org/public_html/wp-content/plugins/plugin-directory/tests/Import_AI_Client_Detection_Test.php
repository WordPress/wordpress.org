<?php
/**
 * Tests for WP AI Client detection during plugin imports.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\CLI\Import;

/**
 * Tests that plugins calling the WP AI Client are recognized.
 *
 * @group import
 */
#[Group( 'import' )]
class Import_AI_Client_Detection_Test extends TestCase {

	/**
	 * Temporary PHP file path.
	 *
	 * @var string
	 */
	private $file;

	/**
	 * Create a temporary file to scan.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->file = sys_get_temp_dir() . '/import-ai-client-' . wp_generate_uuid4() . '.php';
	}

	/**
	 * Remove the temporary file.
	 */
	protected function tearDown(): void {
		if ( file_exists( $this->file ) ) {
			wp_delete_file( $this->file );
		}

		parent::tearDown();
	}

	/**
	 * Source snippets and whether they use the WP AI Client.
	 *
	 * @return array[]
	 */
	public static function data_sources() {
		return array(
			'prompt call'             => array( "<?php\n\$text = wp_ai_client_prompt( 'Summarize this' )->generate_text();", true ),
			'embedding call'          => array( "<?php\n\$vector = wp_ai_client_embedding( \$content );", true ),
			'namespaced call'         => array( "<?php\nnamespace Foo;\n\\wp_ai_client_prompt();", true ),
			'availability check only' => array( "<?php\nif ( function_exists( 'wp_ai_client_prompt' ) ) {}", false ),
			'mentioned in a comment'  => array( "<?php\n// Could use wp_ai_client_prompt() later.", false ),
			'method of the same name' => array( "<?php\n\$client->wp_ai_client_prompt();", false ),
			'no AI client'            => array( "<?php\nwp_remote_post( 'https://api.example.com/v1/chat' );", false ),
		);
	}

	/**
	 * Detects calls to the WP AI Client functions, and nothing else.
	 *
	 * @dataProvider data_sources
	 *
	 * @param string $source   PHP source.
	 * @param bool   $expected Whether the source uses the WP AI Client.
	 */
	#[DataProvider( 'data_sources' )]
	public function test_file_uses_ai_client( $source, $expected ) {
		file_put_contents( $this->file, $source ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture.

		$this->assertSame( $expected, Import::file_uses_ai_client( $this->file ) );
	}

	/**
	 * Ignores files that are not PHP.
	 */
	public function test_ignores_non_php_files() {
		$this->assertFalse( Import::file_uses_ai_client( __DIR__ . '/fixtures/does-not-exist.js' ) );
	}
}
